import BaseNode from './BaseNode.jsx';

export default function ExtensionNode({ id, type, data, targetPosition, sourcePosition }) {
  const isExternal = type === 'external';
  let meta = null;
  if (isExternal && data.dialstring) {
    meta = `→ ${data.dialstring}`;
  } else if (data.extension) {
    meta = `ext ${data.extension}`;
  }

  return (
    <BaseNode
      nodeId={id}
      data={data}
      typeClass="routing-map-node-extension"
      icon={isExternal ? 'mobile alternate icon' : 'user icon'}
      label={data.label || 'Extension'}
      meta={meta}
      href={data.href}
      showBottomHandle={false}
      targetPosition={targetPosition}
      sourcePosition={sourcePosition}
    />
  );
}
