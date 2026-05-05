import BaseNode from './BaseNode.jsx';

export default function QueueNode({ id, data, targetPosition, sourcePosition }) {
  const parts = [];
  if (data.extension) parts.push(`ext ${data.extension}`);
  if (data.strategy) parts.push(data.strategy);
  if (typeof data.members === 'number') parts.push(`${data.members} members`);
  return (
    <BaseNode
      nodeId={id}
      data={data}
      typeClass="routing-map-node-queue"
      icon="users icon"
      label={data.label || 'Queue'}
      meta={parts.join(' · ') || null}
      href={data.href}
      targetPosition={targetPosition}
      sourcePosition={sourcePosition}
    />
  );
}
