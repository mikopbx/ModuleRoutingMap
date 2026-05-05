import BaseNode from './BaseNode.jsx';

export default function RouteNode({ id, data, targetPosition, sourcePosition }) {
  const parts = [];
  if (typeof data.priority === 'number') {
    parts.push(`prio ${data.priority}`);
  }
  if (data.pattern) {
    parts.push(data.pattern);
  }
  if (data.timeout) {
    parts.push(`timeout ${data.timeout}s`);
  }
  return (
    <BaseNode
      nodeId={id}
      data={data}
      typeClass="routing-map-node-route"
      icon="sign in icon"
      label={data.label}
      meta={parts.join(' · ') || data.rulename || null}
      href={data.href}
      targetPosition={targetPosition}
      sourcePosition={sourcePosition}
    />
  );
}
