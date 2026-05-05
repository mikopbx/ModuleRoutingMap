import { Handle, Position } from '@xyflow/react';

/**
 * Shared frame for every typed node in the routing map.
 *
 * Layout:
 *   [icon]  [label / meta]                           [▸ collapse toggle]
 *
 * The collapse toggle is only rendered for nodes that actually have children
 * in the full graph — passed in as `data.__hasChildren` by App.jsx. Clicking
 * it stops event propagation so React Flow doesn't also fire onNodeClick
 * (which would navigate away via the node's href).
 */
export default function BaseNode({
  nodeId,
  typeClass,
  icon,
  label,
  meta,
  href,
  data,
  children,
  showTopHandle = true,
  showBottomHandle = true,
  targetPosition = Position.Top,
  sourcePosition = Position.Bottom,
}) {
  const hasChildren = data?.__hasChildren;
  const isCollapsed = data?.__isCollapsed;
  const hiddenCount = data?.__hiddenCount || 0;
  const onToggle = data?.__onToggleCollapse;

  const body = (
    <div className="routing-map-node-body">
      {icon && <i className={`${icon} routing-map-node-icon`} aria-hidden="true" />}
      <div className="routing-map-node-text">
        <div className="routing-map-node-label">{label}</div>
        {meta && <div className="routing-map-node-meta">{meta}</div>}
        {isCollapsed && hiddenCount > 0 && (
          <div className="routing-map-node-hidden-badge">
            +{hiddenCount} hidden
          </div>
        )}
        {children}
      </div>
    </div>
  );

  const handleToggle = (event) => {
    event.preventDefault();
    event.stopPropagation();
    if (typeof onToggle === 'function' && nodeId) {
      onToggle(nodeId);
    }
  };

  return (
    <div className={`routing-map-node ${typeClass}${isCollapsed ? ' routing-map-node-collapsed' : ''}`}>
      {showTopHandle && <Handle type="target" position={targetPosition} />}
      {href ? (
        <a
          className="routing-map-node-link"
          href={href}
          onClick={(event) => {
            event.stopPropagation();
            // Focus mode: provider/root nodes inject __interceptClick to hijack
            // navigation and toggle focus selection instead of following href.
            if (typeof data?.__interceptClick === 'function') {
              event.preventDefault();
              data.__interceptClick(nodeId);
            }
          }}
        >
          {body}
        </a>
      ) : (
        body
      )}
      {hasChildren && typeof onToggle === 'function' && (
        <button
          type="button"
          className="routing-map-node-toggle"
          onClick={handleToggle}
          onMouseDown={(e) => e.stopPropagation()}
          title={isCollapsed ? 'Expand subtree' : 'Collapse subtree'}
          aria-label={isCollapsed ? 'Expand subtree' : 'Collapse subtree'}
        >
          {isCollapsed ? '+' : '−'}
        </button>
      )}
      {showBottomHandle && <Handle type="source" position={sourcePosition} />}
    </div>
  );
}
