import BaseNode from './BaseNode.jsx';

const CLASS_BY_TYPE = {
  application: 'routing-map-node-application',
  voicemail: 'routing-map-node-voicemail',
  conference: 'routing-map-node-conference',
  unknown: 'routing-map-node-unknown',
};

const ICON_BY_TYPE = {
  application: 'code icon',
  voicemail: 'volume up icon',
  conference: 'comments icon',
  unknown: 'question circle outline icon',
};

const ICON_BY_KIND = {
  playback: 'volume up icon',
  module: 'puzzle piece icon',
  system: 'power off icon',
  sink: 'power off icon',
  dialplan: 'code icon',
};

/**
 * System sinks (kind='system') share one Application node component but
 * represent very different semantic actions. Give each a distinct icon so the
 * call flow reads clearly at a glance.
 */
const ICON_BY_SYSTEM_EXTENSION = {
  hangup: 'phone slash icon',
  busy: 'ban icon',
  voicemail: 'envelope icon',
  did2user: 'user tag icon',
};

const CLASS_BY_SYSTEM_EXTENSION = {
  hangup: 'routing-map-node-application routing-map-node-sink-hangup',
  busy: 'routing-map-node-application routing-map-node-sink-busy',
  voicemail: 'routing-map-node-application routing-map-node-sink-voicemail',
  did2user: 'routing-map-node-application routing-map-node-sink-did2user',
};

export default function ApplicationNode({ id, type, data, targetPosition, sourcePosition }) {
  const isSystemSink = data.kind === 'system' && typeof data.extension === 'string';
  const className = isSystemSink
    ? (CLASS_BY_SYSTEM_EXTENSION[data.extension] || CLASS_BY_TYPE[type] || 'routing-map-node-application')
    : (CLASS_BY_TYPE[type] || 'routing-map-node-application');
  const icon = isSystemSink
    ? (ICON_BY_SYSTEM_EXTENSION[data.extension] || ICON_BY_KIND[data.kind] || 'power off icon')
    : (ICON_BY_KIND[data.kind] || ICON_BY_TYPE[type] || 'code icon');

  let meta;
  if (data.kind === 'playback' && data.audio_message_id) {
    meta = `sound #${data.audio_message_id}`;
  } else if (data.extension) {
    meta = `ext ${data.extension}`;
  } else {
    meta = data.kind || null;
  }

  return (
    <BaseNode
      nodeId={id}
      data={data}
      typeClass={className}
      icon={icon}
      label={data.label}
      meta={meta}
      href={data.href}
      showBottomHandle={false}
      targetPosition={targetPosition}
      sourcePosition={sourcePosition}
    />
  );
}
