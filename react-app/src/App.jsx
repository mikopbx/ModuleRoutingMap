import { useMemo, useCallback, useEffect, useRef, useState } from 'react';
import {
  ReactFlow,
  Background,
  Controls,
  MiniMap,
  ReactFlowProvider,
} from '@xyflow/react';
import '@xyflow/react/dist/style.css';

import { applyDagreLayout } from './layout.js';
import ProviderNode from './nodes/ProviderNode.jsx';
import DispatcherNode from './nodes/DispatcherNode.jsx';
import RouteNode from './nodes/RouteNode.jsx';
import ScheduleNode from './nodes/ScheduleNode.jsx';
import IvrNode from './nodes/IvrNode.jsx';
import QueueNode from './nodes/QueueNode.jsx';
import ExtensionNode from './nodes/ExtensionNode.jsx';
import ApplicationNode from './nodes/ApplicationNode.jsx';
import './theme.css';

const nodeTypes = {
  root: ProviderNode,
  provider: ProviderNode,
  dispatcher: DispatcherNode,
  route: RouteNode,
  schedule: ScheduleNode,
  ivr: IvrNode,
  queue: QueueNode,
  extension: ExtensionNode,
  external: ExtensionNode,
  conference: ApplicationNode,
  application: ApplicationNode,
  voicemail: ApplicationNode,
  unknown: ApplicationNode,
};

const EXTERNAL_ROOT_ID = 'caller:external';
const GLOBAL_PIVOT_ID = 'schedule-pivot:global';

/**
 * Per-branch visual presets. Colours are paired with dash patterns so the
 * graph stays legible in monochrome / colour-blind scenarios (the dash shape
 * is the accessibility-safe channel). Chip styling is derived from the same
 * palette so that edge label text reads as a pill coloured to match its line.
 */
const BRANCH_STYLES = {
  work:       { stroke: '#21ba45', dash: null,     bg: '#e3f9df', fg: '#116627' },
  nonwork:    { stroke: '#db2828', dash: '8 4',    bg: '#fdecea', fg: '#9a1b1b' },
  timeout:    { stroke: '#f2711c', dash: '12 4',   bg: '#fdf1e4', fg: '#8a3b04' },
  empty:      { stroke: '#767676', dash: '2 4',    bg: '#eeeeee', fg: '#444444' },
  unanswered: { stroke: '#b58105', dash: '2 4',    bg: '#fff8e1', fg: '#6b4a00' },
  repeat:     { stroke: '#444444', dash: '2 4',    bg: '#e4e4e4', fg: '#222222' },
  gate:       { stroke: '#6435c9', dash: '4 2',    bg: '#ede4fc', fg: '#3a1a7a' },
  default:    { stroke: '#9aa0a6', dash: null,     bg: '#ffffff', fg: '#5b6269' },
};

function coloriseEdge(edge) {
  const preset = BRANCH_STYLES[edge.data?.branch] || BRANCH_STYLES.default;
  const strokeWidth = edge.data?.branch === 'gate' ? 1.5 : 2;
  const style = { stroke: preset.stroke, strokeWidth };
  if (preset.dash) style.strokeDasharray = preset.dash;

  const hasLabel = typeof edge.label === 'string' && edge.label !== '';

  return {
    ...edge,
    type: 'smoothstep',
    animated: false,
    style,
    // Chip-style label: solid background matching branch palette + subtle
    // border. React Flow 12 supports labelBgStyle / labelStyle natively.
    ...(hasLabel
      ? {
          labelStyle: { fill: preset.fg, fontWeight: 600, fontSize: 11 },
          labelBgStyle: { fill: preset.bg, stroke: preset.stroke, strokeWidth: 1 },
          labelBgPadding: [6, 3],
          labelBgBorderRadius: 10,
        }
      : {}),
  };
}

/**
 * Builds an adjacency list { source → [targets] } from an edge list.
 */
function buildAdjacency(edges) {
  const adj = new Map();
  for (const e of edges) {
    if (!adj.has(e.source)) adj.set(e.source, []);
    adj.get(e.source).push(e.target);
  }
  return adj;
}

/**
 * Given the full graph and a set of collapsed node IDs, returns the subset of
 * node IDs that should remain visible. A node is visible iff at least one path
 * from a graph root to it does not pass through a collapsed node.
 *
 * In a DAG with shared children this correctly keeps a node visible if any of
 * its parents are not collapsed.
 */
function computeVisibleNodeIds(graph, collapsed) {
  if (collapsed.size === 0) {
    return new Set(graph.nodes.map((n) => n.id));
  }
  const hasIncoming = new Set(graph.edges.map((e) => e.target));
  const roots = graph.nodes
    .filter((n) => !hasIncoming.has(n.id))
    .map((n) => n.id);
  const adj = buildAdjacency(graph.edges);

  const visible = new Set();
  const queue = [...roots];
  while (queue.length > 0) {
    const id = queue.shift();
    if (visible.has(id)) continue;
    visible.add(id);
    if (collapsed.has(id)) continue; // stop descending past a collapsed node
    for (const child of adj.get(id) || []) {
      queue.push(child);
    }
  }
  return visible;
}

/**
 * Counts descendants (reachable nodes excluding self) past each node — used
 * to render "+N hidden" badges on collapsed nodes.
 */
function countDescendantsForAll(graph) {
  const adj = buildAdjacency(graph.edges);
  const cache = new Map();
  const visit = (id, seen) => {
    if (cache.has(id)) return cache.get(id);
    if (seen.has(id)) return new Set(); // cycle guard (shouldn't happen, defensive)
    seen.add(id);
    const reachable = new Set();
    for (const child of adj.get(id) || []) {
      reachable.add(child);
      for (const c of visit(child, seen)) reachable.add(c);
    }
    seen.delete(id);
    cache.set(id, reachable);
    return reachable;
  };
  const counts = new Map();
  for (const node of graph.nodes) {
    counts.set(node.id, visit(node.id, new Set()).size);
  }
  return counts;
}

/**
 * Toolbar with layout direction, density toggle and collapse-all control.
 * Sits floating in the top-left corner of the canvas so it never overlaps
 * the MiniMap (bottom-right) or Controls (bottom-left).
 */
function Toolbar({
  direction,
  onDirectionChange,
  density,
  onDensityChange,
  collapsedCount,
  onResetCollapse,
  focusMode,
  onFocusModeChange,
}) {
  const btn = (active) => ({
    padding: '4px 10px',
    fontSize: '12px',
    border: '1px solid #c0c4c8',
    background: active ? '#2185d0' : '#ffffff',
    color: active ? '#ffffff' : '#444',
    cursor: 'pointer',
    lineHeight: 1.4,
  });
  const group = {
    display: 'inline-flex',
    borderRadius: '4px',
    overflow: 'hidden',
    boxShadow: '0 1px 3px rgba(0,0,0,0.08)',
    marginRight: '8px',
  };
  return (
    <div
      className="routing-map-toolbar-float"
      style={{
        position: 'absolute',
        top: 12,
        left: 12,
        zIndex: 10,
        display: 'flex',
        alignItems: 'center',
        flexWrap: 'wrap',
        gap: '6px',
      }}
    >
      <div style={group} role="group" aria-label="Layout direction">
        <button
          type="button"
          style={btn(direction === 'TB')}
          onClick={() => onDirectionChange('TB')}
          title="Top → bottom"
        >
          ↓ TB
        </button>
        <button
          type="button"
          style={btn(direction === 'LR')}
          onClick={() => onDirectionChange('LR')}
          title="Left → right"
        >
          → LR
        </button>
      </div>
      <div style={group} role="group" aria-label="Density">
        <button
          type="button"
          style={btn(density === 'comfortable')}
          onClick={() => onDensityChange('comfortable')}
          title="More spacing"
        >
          Comfortable
        </button>
        <button
          type="button"
          style={btn(density === 'compact')}
          onClick={() => onDensityChange('compact')}
          title="Less spacing"
        >
          Compact
        </button>
      </div>
      <div style={group} role="group" aria-label="Focus mode">
        <span
          style={{
            padding: '4px 8px',
            fontSize: '11px',
            color: '#666',
            background: '#f7f8fa',
            border: '1px solid #c0c4c8',
            borderRight: 'none',
            lineHeight: 1.4,
          }}
        >
          Focus
        </span>
        <button
          type="button"
          style={btn(focusMode === 'off')}
          onClick={() => onFocusModeChange('off')}
          title="Focus off — clicking providers navigates (default)"
        >
          Off
        </button>
        <button
          type="button"
          style={btn(focusMode === 'dim')}
          onClick={() => onFocusModeChange('dim')}
          title="Click a provider to dim other subtrees while keeping them visible"
        >
          Dim
        </button>
        <button
          type="button"
          style={btn(focusMode === 'collapse')}
          onClick={() => onFocusModeChange('collapse')}
          title="Click a provider to auto-collapse sibling providers"
        >
          Collapse
        </button>
      </div>
      {collapsedCount > 0 && (
        <button
          type="button"
          style={{
            ...btn(false),
            borderRadius: '4px',
            background: '#fff8e6',
            borderColor: '#e0b84c',
          }}
          onClick={onResetCollapse}
          title="Expand all collapsed subtrees"
        >
          Expand all ({collapsedCount})
        </button>
      )}
    </div>
  );
}

/**
 * Rendered when the graph is empty — keeps the canvas useful on fresh PBXes
 * that have no providers/routes configured yet.
 */
function EmptyState({ direction }) {
  const isIncoming = direction === 'incoming';
  return (
    <div
      style={{
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        height: '100%',
        padding: '40px',
        textAlign: 'center',
        color: '#5b6269',
      }}
    >
      <i className="sitemap icon" style={{ fontSize: '48px', opacity: 0.35, margin: '0 0 16px' }} />
      <h3 style={{ margin: '0 0 8px', color: '#1b1c1d' }}>
        No {isIncoming ? 'incoming' : 'outgoing'} routing configured
      </h3>
      <p style={{ margin: '0 0 16px', maxWidth: '420px' }}>
        {isIncoming
          ? 'Add providers and incoming routes to see calls flow through your PBX.'
          : 'Add outbound routing rules to see how internal calls reach external destinations.'}
      </p>
      <a
        className="ui small primary button"
        href={
          isIncoming
            ? '/admin-cabinet/providers/index'
            : '/admin-cabinet/outbound-routes/index'
        }
      >
        Open {isIncoming ? 'Providers' : 'Outbound routes'}
      </a>
    </div>
  );
}

/**
 * Fullscreen toggle mirroring the .fullscreen-toggle-btn pattern used by the
 * System Diagnostic log viewer. Targets the nearest `.routing-map-canvas`
 * ancestor so the entire canvas (including toolbar, controls and minimap)
 * goes fullscreen, and triggers a React Flow refit on state change so the
 * graph re-centers into the new viewport size.
 */
function FullscreenButton({ onRequestRefit }) {
  const [isFullscreen, setIsFullscreen] = useState(false);
  const anchorRef = useRef(null);

  useEffect(() => {
    const onChange = () => {
      setIsFullscreen(Boolean(document.fullscreenElement));
      // Refit after the browser finishes resizing. Two RAFs because the first
      // fires before the native fullscreen layout has stabilised on some WebKit
      // builds.
      requestAnimationFrame(() => {
        requestAnimationFrame(() => onRequestRefit?.());
      });
    };
    document.addEventListener('fullscreenchange', onChange);
    return () => document.removeEventListener('fullscreenchange', onChange);
  }, [onRequestRefit]);

  const toggle = useCallback((event) => {
    event.preventDefault();
    event.stopPropagation();

    const canvas = anchorRef.current?.closest('.routing-map-canvas')
      ?? anchorRef.current?.parentElement;
    if (!canvas) return;

    if (!document.fullscreenElement) {
      canvas.requestFullscreen?.().catch((err) => {
        // Fullscreen can be blocked by iframes or browser policies — fail quietly.
        console.warn('Fullscreen request denied:', err?.message || err);
      });
    } else {
      document.exitFullscreen?.();
    }
  }, []);

  return (
    <div
      ref={anchorRef}
      className="fullscreen-toggle-btn routing-map-fullscreen-btn"
      role="button"
      tabIndex={0}
      onClick={toggle}
      onKeyDown={(e) => {
        if (e.key === 'Enter' || e.key === ' ') toggle(e);
      }}
      title={isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'}
      aria-label={isFullscreen ? 'Exit fullscreen' : 'Enter fullscreen'}
    >
      <i className={`inverted ${isFullscreen ? 'compress' : 'expand'} icon`} />
    </div>
  );
}

/**
 * Inner flow component — must live inside ReactFlowProvider so the instance
 * can be captured via onInit for manual fitView calls.
 */
function Flow({ nodes, edges, onNodeClick, onPaneClick }) {
  const instanceRef = useRef(null);

  const fit = useCallback(() => {
    if (instanceRef.current) {
      try {
        instanceRef.current.fitView({ padding: 0.2, duration: 0 });
      } catch (err) {
        // fitView can fail on an empty viewport; ignore
      }
    }
  }, []);

  const handleInit = useCallback(
    (instance) => {
      instanceRef.current = instance;
      requestAnimationFrame(() => fit());
    },
    [fit]
  );

  useEffect(() => {
    if (instanceRef.current) {
      requestAnimationFrame(() => fit());
    }
  }, [nodes, edges, fit]);

  const handleNodeClick = useCallback(
    (_event, node) => {
      if (typeof onNodeClick === 'function') {
        onNodeClick(node);
      }
    },
    [onNodeClick]
  );

  return (
    <>
      <ReactFlow
        nodes={nodes}
        edges={edges}
        nodeTypes={nodeTypes}
        onNodeClick={handleNodeClick}
        onPaneClick={onPaneClick}
        onInit={handleInit}
        fitView
        fitViewOptions={{ padding: 0.2, minZoom: 0.1 }}
        minZoom={0.1}
        maxZoom={2}
        proOptions={{ hideAttribution: true }}
        nodesDraggable
        nodesConnectable={false}
        elementsSelectable
      >
        <Background gap={18} size={1} color="#e1e4e8" />
        <Controls showInteractive={false} />
        <MiniMap pannable zoomable nodeStrokeWidth={3} />
      </ReactFlow>
      <FullscreenButton onRequestRefit={fit} />
    </>
  );
}

function App({ graph, direction, onNodeClick }) {
  const [layoutDirection, setLayoutDirection] = useState('TB');
  const [density, setDensity] = useState('comfortable');
  // User-initiated collapse. Focus-driven auto-collapse is merged in later via
  // effectiveCollapsed; keeping the two separate lets us cleanly unwind
  // focus-triggered collapses without nuking what the user clicked themselves.
  const [collapsed, setCollapsed] = useState(() => new Set());
  const [focusMode, setFocusMode] = useState('off');
  const [focusedProviderId, setFocusedProviderId] = useState(null);

  // Full-graph derivations — these don't depend on collapse state so they
  // only recompute when the underlying graph object changes.
  const fullAdjacency = useMemo(() => buildAdjacency(graph.edges), [graph.edges]);
  const hiddenCounts = useMemo(() => countDescendantsForAll(graph), [graph]);
  const nodeIdSet = useMemo(
    () => new Set(graph.nodes.map((n) => n.id)),
    [graph.nodes]
  );

  const toggleCollapse = useCallback((nodeId) => {
    setCollapsed((prev) => {
      const next = new Set(prev);
      if (next.has(nodeId)) {
        next.delete(nodeId);
      } else {
        next.add(nodeId);
      }
      return next;
    });
  }, []);

  const resetCollapse = useCallback(() => {
    setCollapsed(new Set());
  }, []);

  const clearFocus = useCallback(() => setFocusedProviderId(null), []);

  const handleFocusModeChange = useCallback((mode) => {
    setFocusMode(mode);
    if (mode === 'off') {
      setFocusedProviderId(null);
    }
  }, []);

  // Toggle focus on provider/root click. Clicking the focused provider again
  // clears focus (toggle behaviour, matches the expand/collapse convention).
  const handleFocusIntercept = useCallback((nodeId) => {
    setFocusedProviderId((prev) => (prev === nodeId ? null : nodeId));
  }, []);

  // Reset collapse + focus whenever the underlying graph changes. Disabled
  // providers are collapsed by default — their subtrees are still in the data
  // model (so Expand-all brings them back), but they don't pollute the
  // initial view with content the operator has explicitly turned off.
  useEffect(() => {
    const initialCollapse = new Set();
    for (const n of graph.nodes) {
      if (n.type === 'provider' && n.data?.disabled === true) {
        initialCollapse.add(n.id);
      }
    }
    setCollapsed(initialCollapse);
    setFocusedProviderId(null);
  }, [graph]);

  // ESC clears focus. Only listen while a focus is actually active to avoid
  // swallowing Escape in unrelated parts of the admin cabinet.
  useEffect(() => {
    if (focusedProviderId === null) return undefined;
    const onKey = (event) => {
      if (event.key === 'Escape') {
        clearFocus();
      }
    };
    document.addEventListener('keydown', onKey);
    return () => document.removeEventListener('keydown', onKey);
  }, [focusedProviderId, clearFocus]);

  // Dim-mode bright set: focused provider's subtree ∪ global pivot's subtree
  // ∪ external root. Returned as `null` when Dim is not active so downstream
  // consumers can cheaply skip the dim-path entirely.
  const focusBrightSet = useMemo(() => {
    if (focusMode !== 'dim' || focusedProviderId === null) return null;
    const bright = new Set();
    const bfs = (startId) => {
      if (!nodeIdSet.has(startId)) return;
      const queue = [startId];
      while (queue.length > 0) {
        const id = queue.shift();
        if (bright.has(id)) continue;
        bright.add(id);
        for (const child of fullAdjacency.get(id) || []) {
          queue.push(child);
        }
      }
    };
    bfs(focusedProviderId);
    // Global pivot + its descendants stay bright: they apply globally and
    // dimming them misrepresents behaviour.
    bfs(GLOBAL_PIVOT_ID);
    // Keep the external caller root visible for visual continuity with
    // the focused provider hanging off it.
    if (nodeIdSet.has(EXTERNAL_ROOT_ID)) {
      bright.add(EXTERNAL_ROOT_ID);
    }
    return bright;
  }, [focusMode, focusedProviderId, fullAdjacency, nodeIdSet]);

  // Collapse-mode: auto-collapse sibling providers (children of the external
  // root that aren't the focused one). Tracked separately so clearing focus
  // drops exactly these additions and leaves user-initiated collapses alone.
  const autoCollapsed = useMemo(() => {
    if (focusMode !== 'collapse' || focusedProviderId === null) return null;
    const siblings = new Set();
    for (const e of graph.edges) {
      if (e.source === EXTERNAL_ROOT_ID && e.target !== focusedProviderId) {
        siblings.add(e.target);
      }
    }
    return siblings;
  }, [focusMode, focusedProviderId, graph.edges]);

  const effectiveCollapsed = useMemo(() => {
    if (!autoCollapsed || autoCollapsed.size === 0) return collapsed;
    const merged = new Set(collapsed);
    for (const id of autoCollapsed) merged.add(id);
    return merged;
  }, [collapsed, autoCollapsed]);

  const visibleIds = useMemo(
    () => computeVisibleNodeIds(graph, effectiveCollapsed),
    [graph, effectiveCollapsed]
  );

  const filteredGraph = useMemo(() => {
    if (effectiveCollapsed.size === 0) {
      return graph;
    }
    return {
      nodes: graph.nodes.filter((n) => visibleIds.has(n.id)),
      edges: graph.edges.filter(
        (e) => visibleIds.has(e.source) && visibleIds.has(e.target)
      ),
    };
  }, [graph, visibleIds, effectiveCollapsed]);

  const laidOut = useMemo(
    () => applyDagreLayout(filteredGraph, { direction: layoutDirection, density }),
    [filteredGraph, layoutDirection, density]
  );

  // Inject collapse + focus metadata into every node's data so BaseNode can
  // render the toggle affordance and intercept provider clicks for focus
  // toggling. We route callbacks through data rather than context because
  // React Flow already passes node.data into the custom node component.
  const nodes = useMemo(
    () =>
      laidOut.nodes.map((n) => {
        const hasChildren = (fullAdjacency.get(n.id) || []).length > 0;
        const isCollapsed = effectiveCollapsed.has(n.id);
        const isFocusable = n.type === 'provider' || n.type === 'root';
        const interceptClick =
          focusMode !== 'off' && isFocusable ? handleFocusIntercept : undefined;
        const isFocused = focusedProviderId === n.id;
        const isDimmed = focusBrightSet !== null && !focusBrightSet.has(n.id);

        const classes = [];
        if (isDimmed) classes.push('routing-map-node-dimmed');
        if (isFocused) classes.push('routing-map-node-focused');

        return {
          ...n,
          type: nodeTypes[n.type] ? n.type : 'unknown',
          className: classes.length > 0 ? classes.join(' ') : undefined,
          data: {
            ...n.data,
            __hasChildren: hasChildren,
            __isCollapsed: isCollapsed,
            __hiddenCount: isCollapsed ? hiddenCounts.get(n.id) || 0 : 0,
            __onToggleCollapse: hasChildren ? toggleCollapse : undefined,
            __interceptClick: interceptClick,
            __isDimmed: isDimmed,
            __isFocused: isFocused,
          },
        };
      }),
    [
      laidOut.nodes,
      fullAdjacency,
      effectiveCollapsed,
      hiddenCounts,
      toggleCollapse,
      focusMode,
      focusedProviderId,
      focusBrightSet,
      handleFocusIntercept,
    ]
  );

  const edges = useMemo(
    () =>
      laidOut.edges.map((e) => {
        const coloured = coloriseEdge(e);
        if (focusBrightSet === null) return coloured;
        const dim = !focusBrightSet.has(e.source) || !focusBrightSet.has(e.target);
        if (!dim) return coloured;
        return {
          ...coloured,
          className: `${coloured.className || ''} routing-map-edge-dimmed`.trim(),
        };
      }),
    [laidOut.edges, focusBrightSet]
  );

  const isEmpty = graph.nodes.length === 0;

  return (
    <ReactFlowProvider>
      <div className="routing-map-react-root" data-direction={direction}>
        {isEmpty ? (
          <EmptyState direction={direction} />
        ) : (
          <>
            <Toolbar
              direction={layoutDirection}
              onDirectionChange={setLayoutDirection}
              density={density}
              onDensityChange={setDensity}
              collapsedCount={collapsed.size}
              onResetCollapse={resetCollapse}
              focusMode={focusMode}
              onFocusModeChange={handleFocusModeChange}
            />
            <Flow
              nodes={nodes}
              edges={edges}
              onNodeClick={onNodeClick}
              onPaneClick={clearFocus}
            />
          </>
        )}
      </div>
    </ReactFlowProvider>
  );
}

export default App;
