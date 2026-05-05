import BaseNode from './BaseNode.jsx';

/**
 * Priority dispatcher — sits between a provider and its incoming routes.
 * Makes Asterisk's sequential "first DID match wins by priority" evaluation
 * explicit in the graph; without this node, parallel routes hanging off a
 * provider imply a fan-out that doesn't match runtime behaviour.
 */
export default function DispatcherNode({ id, data, targetPosition, sourcePosition }) {
  const range = Array.isArray(data.priorityRange) ? data.priorityRange : [];
  const count = data.rulesCount ?? 0;
  const rulesWord = count === 1 ? 'rule' : 'rules';
  const meta = range.length === 2 && range[0] !== range[1]
    ? `${count} ${rulesWord} · priority ${range[0]}–${range[1]}`
    : `${count} ${rulesWord}${range.length === 2 ? ` · priority ${range[0]}` : ''}`;

  return (
    <BaseNode
      nodeId={id}
      data={data}
      typeClass="routing-map-node-dispatcher"
      icon="filter icon"
      label={data.label || 'Priority dispatcher'}
      meta={meta}
      href={null}
      showTopHandle
      showBottomHandle
      targetPosition={targetPosition}
      sourcePosition={sourcePosition}
    />
  );
}
