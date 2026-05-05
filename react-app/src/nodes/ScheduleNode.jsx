import BaseNode from './BaseNode.jsx';

export default function ScheduleNode({ id, data, targetPosition, sourcePosition }) {
  const isPivot = data.kind === 'pivot';

  if (isPivot) {
    // Render a summary list of the global time rules inside the pivot so the
    // user sees what's being checked without needing to expand the subtree.
    const rules = Array.isArray(data.rules) ? data.rules : [];
    return (
      <BaseNode
        nodeId={id}
        data={data}
        typeClass="routing-map-node-schedule routing-map-node-schedule-pivot"
        icon="clock outline icon"
        label={data.label || 'Global time policy'}
        meta={data.description || null}
        href={data.href}
        targetPosition={targetPosition}
        sourcePosition={sourcePosition}
      >
        {rules.length > 0 && (
          <ul className="routing-map-node-rule-list">
            {rules.slice(0, 5).map((r, idx) => (
              <li key={idx}>{r}</li>
            ))}
            {rules.length > 5 && (
              <li className="routing-map-node-rule-more">
                +{rules.length - 5} more
              </li>
            )}
          </ul>
        )}
      </BaseNode>
    );
  }

  return (
    <BaseNode
      nodeId={id}
      data={data}
      typeClass="routing-map-node-schedule"
      icon="calendar times outline icon"
      label={data.label || 'Schedule'}
      meta={data.scope === 'global' ? 'Global rule' : (data.description || null)}
      href={data.href}
      targetPosition={targetPosition}
      sourcePosition={sourcePosition}
    />
  );
}
