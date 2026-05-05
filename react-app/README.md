# ModuleRoutingMap — React Flow bundle

This folder contains the React source for the interactive graph viewer. It is
**not** deployed on the PBX — only the pre-built IIFE bundle at
`../public/assets/js/vendor/react-flow.bundle.js` is.

## Build

```bash
cd react-app
npm install
npm run build
```

`npm run build` produces a single self-contained IIFE file that inlines React,
ReactDOM, `@xyflow/react`, `dagre` and the app entry. The file is written to
`../public/assets/js/vendor/react-flow.bundle.js` and committed with the module.

## Runtime contract

The bundle exposes exactly one global symbol:

```js
window.MikoRoutingMap.mount(htmlElement, graph, options?)
```

- `htmlElement` — container for the canvas; the function clears its contents.
- `graph` — `{ nodes: [...], edges: [...] }` as returned by the module's REST endpoints.
- `options.onNodeClick(node)` — optional handler invoked on node clicks (receives the
  raw node descriptor). Used by the glue code to navigate to admin pages via
  `node.data.href`.

The glue code in `../public/assets/js/src/module-routing-map-index.js` is
responsible for fetching the JSON, unwrapping the PBXApiResult envelope, and
invoking `mount` once for each tab.

## Node types

The frontend recognises the following `node.type` values emitted by PHP
`NodeFactory`: `root`, `provider`, `route`, `schedule`, `ivr`, `queue`,
`extension`, `conference`, `application`, `voicemail`, `external`, `unknown`.
Custom React components for these types live in `src/nodes/`.
