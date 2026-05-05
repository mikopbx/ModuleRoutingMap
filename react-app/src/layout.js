import dagre from 'dagre';
import { Position } from '@xyflow/react';

const DEFAULT_NODE_WIDTH = 220;
const DEFAULT_NODE_HEIGHT = 68;

/**
 * Node types that render extra content (option lists, rule summaries) have a
 * taller footprint than the default card. Dagre gets real heights so neighbor
 * nodes don't overlap. Numbers are conservative — slightly over the actual
 * max height — because dagre only places based on bounding boxes and having
 * a little slack is safer than being off by 5px and getting collisions.
 */
const HEIGHT_BY_TYPE = {
  schedule: 120,
  dispatcher: 80,
};

/**
 * IVR nodes size dynamically based on their option list: a header chunk plus
 * ~22px per option plus a possible timeout row.
 */
function heightForNode(node) {
  if (node.type === 'ivr') {
    const options = Array.isArray(node.data?.options) ? node.data.options : [];
    const timeoutRow = node.data?.timeoutTarget?.label ? 1 : 0;
    const rows = options.length + timeoutRow;
    return 88 + rows * 22;
  }
  if (node.type === 'schedule' && node.data?.kind === 'pivot') {
    const rules = Array.isArray(node.data?.rules) ? node.data.rules : [];
    return 90 + Math.min(rules.length, 5) * 18 + (rules.length > 5 ? 18 : 0);
  }
  return HEIGHT_BY_TYPE[node.type] || DEFAULT_NODE_HEIGHT;
}

/**
 * Density presets control how much breathing room dagre leaves between nodes.
 * `comfortable` is the default; `compact` is for overview of large graphs.
 */
export const DENSITY_PRESETS = {
  comfortable: { nodesep: 40, ranksep: 70, marginx: 30, marginy: 30 },
  compact: { nodesep: 18, ranksep: 38, marginx: 12, marginy: 12 },
};

/**
 * Picks React Flow handle positions matching the dagre direction so edges
 * enter/leave nodes from the axis that matches the layout flow. Hardcoding
 * Top/Bottom in the node component breaks LR layouts — edges loop over the
 * nodes because the handles face the wrong way.
 */
function handlePositionsFor(direction) {
  switch (direction) {
    case 'LR':
      return { target: Position.Left, source: Position.Right };
    case 'RL':
      return { target: Position.Right, source: Position.Left };
    case 'BT':
      return { target: Position.Bottom, source: Position.Top };
    case 'TB':
    default:
      return { target: Position.Top, source: Position.Bottom };
  }
}

/**
 * Runs a dagre layout on a graph shaped like React Flow's expectation and
 * returns a new graph with absolute x/y positions assigned to each node.
 *
 * @param {{nodes: any[], edges: any[]}} graph
 * @param {{direction?: 'TB'|'LR'|'BT'|'RL', density?: 'compact'|'comfortable'}} options
 */
export function applyDagreLayout(graph, options = {}) {
  const direction = options.direction || 'TB';
  const density = DENSITY_PRESETS[options.density] || DENSITY_PRESETS.comfortable;
  const handlePositions = handlePositionsFor(direction);

  const g = new dagre.graphlib.Graph();
  g.setGraph({
    rankdir: direction,
    ...density,
  });
  g.setDefaultEdgeLabel(() => ({}));

  const heights = new Map();
  for (const node of graph.nodes) {
    const height = heightForNode(node);
    heights.set(node.id, height);
    g.setNode(node.id, {
      width: DEFAULT_NODE_WIDTH,
      height,
    });
  }
  for (const edge of graph.edges) {
    g.setEdge(edge.source, edge.target);
  }

  dagre.layout(g);

  const nodes = graph.nodes.map((node) => {
    const positioned = g.node(node.id);
    const height = heights.get(node.id) ?? DEFAULT_NODE_HEIGHT;
    return {
      ...node,
      sourcePosition: handlePositions.source,
      targetPosition: handlePositions.target,
      position: {
        x: positioned ? positioned.x - DEFAULT_NODE_WIDTH / 2 : 0,
        y: positioned ? positioned.y - height / 2 : 0,
      },
    };
  });

  return { nodes, edges: graph.edges };
}
