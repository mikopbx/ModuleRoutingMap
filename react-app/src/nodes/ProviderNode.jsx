import BaseNode from './BaseNode.jsx';

export default function ProviderNode({ id, data, targetPosition, sourcePosition }) {
  const kind = data.kind || 'sip';
  const disabled = data.disabled === true;
  const typeTag = kind === 'default' || kind === 'source' || kind === 'missing'
    ? null
    : kind.toUpperCase();
  const meta = disabled
    ? `${typeTag ? `${typeTag} · ` : ''}Disabled`
    : typeTag;
  const icon = kind === 'source' ? 'phone icon' : 'server icon';
  const typeClass = disabled
    ? 'routing-map-node-provider routing-map-node-provider-disabled'
    : 'routing-map-node-provider';
  return (
    <BaseNode
      nodeId={id}
      data={data}
      typeClass={typeClass}
      icon={icon}
      label={data.label}
      meta={meta}
      href={data.href}
      targetPosition={targetPosition}
      sourcePosition={sourcePosition}
    />
  );
}
