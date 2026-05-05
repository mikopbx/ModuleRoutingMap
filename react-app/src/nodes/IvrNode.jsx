import BaseNode from './BaseNode.jsx';

// Fomantic icon class per IVR option target kind. Mirrors ICON_BY_TYPE in
// ApplicationNode so the inline option chips visually match what the edge
// would render if we'd spawned separate nodes for the same target.
const OPTION_ICON = {
  queue: 'users',
  ivr: 'sitemap',
  extension: 'user',
  external: 'mobile alternate',
  conference: 'comments',
  system: 'power off',
  unknown: 'question circle outline',
};

function IvrOption({ digit, label, kind }) {
  const icon = OPTION_ICON[kind] || 'chevron right';
  return (
    <li className="routing-map-ivr-option">
      <span className="routing-map-ivr-digit">{digit}</span>
      <i className={`${icon} icon`} aria-hidden="true" />
      <span className="routing-map-ivr-option-label">{label || `ext ${digit}`}</span>
    </li>
  );
}

export default function IvrNode({ id, data, targetPosition, sourcePosition }) {
  const parts = [];
  if (data.extension) parts.push(`ext ${data.extension}`);
  if (data.timeout) parts.push(`timeout ${data.timeout}s`);

  const options = Array.isArray(data.options) ? data.options : [];
  const timeoutTarget = data.timeoutTarget;

  return (
    <BaseNode
      nodeId={id}
      data={data}
      typeClass="routing-map-node-ivr routing-map-node-ivr-menu"
      icon="sitemap icon"
      label={data.label || 'IVR'}
      meta={parts.join(' · ') || null}
      href={data.href}
      targetPosition={targetPosition}
      sourcePosition={sourcePosition}
    >
      {options.length > 0 && (
        <ul className="routing-map-ivr-options">
          {options.map((opt, idx) => (
            <IvrOption
              key={`${opt.digit}-${idx}`}
              digit={opt.digit}
              label={opt.label}
              kind={opt.kind}
            />
          ))}
          {timeoutTarget && timeoutTarget.label && (
            <li className="routing-map-ivr-option routing-map-ivr-option-timeout">
              <span className="routing-map-ivr-digit routing-map-ivr-digit-timeout">
                <i className="clock outline icon" aria-hidden="true" />
              </span>
              <span className="routing-map-ivr-option-label">
                Timeout → {timeoutTarget.label}
              </span>
            </li>
          )}
        </ul>
      )}
    </BaseNode>
  );
}
