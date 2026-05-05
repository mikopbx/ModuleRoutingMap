# ModuleRoutingMap — guide for Claude

Read-only visualization module for MikoPBX. The PHP side reads providers,
incoming/outgoing routes, time conditions, IVR menus, queues, extensions and
conferences from the standard MikoPBX models, builds a directed `{nodes, edges}`
graph and exposes it via REST. The React Flow bundle in `public/` renders it
inside the admin cabinet. The module never modifies the dialplan or any core
table — adding/removing it on a production PBX is safe.

## Layout

```
App/
  Controllers/
    ModuleRoutingMapBaseController.php          shared base (asset registration)
    ModuleRoutingMapController.php              indexAction — passes incoming/outgoing endpoint URLs to volt
  Views/ModuleRoutingMap/
    index.volt                                  tabs (incoming / outgoing) + canvas + legend
Lib/
  RoutingMapConf.php                            ConfigClass — empty body, no hooks
  GraphBuilder/
    GraphBuilderInterface.php                   build(): array{ nodes, edges }
    IncomingGraphBuilder.php                    providers → routes → schedules → IVR/queues/extensions
    OutgoingGraphBuilder.php                    dial-patterns → providers
    NodeFactory.php                             TYPE_* vocabulary + makeNode/makeEdge helpers
  RestAPI/Graph/
    Controller.php                              #[ApiResource] — getIncoming / getOutgoing
    Processor.php                               action dispatch
    DataStructure.php                           ApiParameter definitions
    Actions/{GetIncomingGraphAction,GetOutgoingGraphAction}.php
Messages/{en,ru}.php                            translations (key 'BreadcrumbModuleRoutingMap')
Setup/PbxExtensionSetup.php                     overrides addToSidebar() — group 'maintenance', icon 'project diagram'
public/assets/
  css/module-routing-map.css                    page styling
  js/src/module-routing-map-index.js            SOURCE: fetch JSON, unwrap PBXApiResult, call MikoRoutingMap.mount per tab
  js/module-routing-map-index.js                COMPILED (babel airbnb)
  js/vendor/react-flow.bundle.js                IIFE bundle (~600 KB) — built from react-app/, NOT in git for dev
react-app/                                      React Flow source (Vite + dagre); not deployed to PBX
  src/nodes/{Provider,Route,Schedule,Ivr,Queue,Extension,Application,Dispatcher,Base}Node.jsx
  src/{App,main}.jsx, layout.js, theme.css
```

There is **no** module DB — the module owns no tables and no runtime files
under `db/`. Everything is read-through from MikoPBX core models.

## REST API v3

All endpoints under `/pbxcore/api/v3/module-routing-map/`. Auth: localhost or
Bearer token. Both endpoints are `GET`-only (read-only module by design).

| Method | Path                | Action name (Processor case) |
|--------|---------------------|------------------------------|
| GET    | `/graph:incoming`   | `getIncoming`                |
| GET    | `/graph:outgoing`   | `getOutgoing`                |

Both actions are exposed as **custom collection-level methods** in
`#[HttpMapping]` (`customMethods`, `collectionLevelMethods`), reachable only
via the `:action` URL suffix. The `getList` / `getRecord` defaults from
`BaseRestController::mapHttpMethodToAction()` are intentionally not used here
because the module exposes two distinct projections of the same resource —
neither is a "list of graph objects".

Response shape (consumed by React Flow as-is, after unwrapping `PBXApiResult`):

```json
{
  "nodes": [ { "id": "provider-1", "type": "provider", "data": { "label": "...", "href": "/admin-cabinet/providers/modify/1" } } ],
  "edges": [ { "id": "e-provider-1-route-42", "source": "provider-1", "target": "route-42" } ]
}
```

## Frontend / runtime contract

The compiled bundle exposes exactly one global:

```js
window.MikoRoutingMap.mount(htmlElement, graph, options?)
```

- `htmlElement` — container; `mount` clears its children before rendering.
- `graph` — `{ nodes, edges }` returned by the REST endpoint.
- `options.onNodeClick(node)` — optional; the glue uses it to navigate to
  `node.data.href` (admin-cabinet edit page for provider / route / IVR / …).

The glue (`public/assets/js/src/module-routing-map-index.js`) fetches both
endpoints once on page load and calls `mount` per tab. The `Refresh` button
re-fetches and re-mounts that tab only.

## Node type vocabulary — frozen contract

`NodeFactory::TYPE_*` constants (`root`, `provider`, `dispatcher`, `route`,
`schedule`, `ivr`, `queue`, `extension`, `conference`, `application`,
`voicemail`, `external`, `unknown`) map 1:1 to React components in
`react-app/src/nodes/`. **Adding a new node type requires changes on both
sides** — emit it from PHP, write a matching `<NewType>Node.jsx`, register
it in the React Flow `nodeTypes` map, and rebuild the bundle. The legend in
`index.volt` and the translation keys (`module_routing_map_Node*`) also
need updating.

## React bundle build

The marketplace artefact ships the pre-built IIFE — end users do not need
Node.js. To rebuild after editing React sources:

```bash
cd react-app
npm install
npm run build         # writes ../public/assets/js/vendor/react-flow.bundle.js
```

The bundle inlines React, ReactDOM, `@xyflow/react` and `dagre` into one
self-contained IIFE so the module works on air-gapped installations.

Glue JS build (the small fetch/mount layer, separate from the React bundle):

```bash
../../MikoPBXUtils/node_modules/.bin/babel \
  public/assets/js/src/module-routing-map-index.js \
  --out-dir public/assets/js \
  --source-maps inline \
  --presets airbnb
```

## Sidebar placement

`Setup/PbxExtensionSetup::addToSidebar()` overrides the default — the menu
item lands in the **maintenance** group with icon `project diagram` instead
of the default `modules` group, because the module is an audit/visualization
tool rather than a feature module.

## Workflows after editing PHP

`WorkerApiCommands` keeps a long-living PHP worker. After changing any class
the worker loads (Processor, Action, GraphBuilder, NodeFactory), the running
worker still holds the old bytecode. **Disable + enable the module in the
admin cabinet** to recycle workers and flush APCu. Editing only the volt
view, glue JS or CSS does not require a worker restart
(opcache `validate_timestamps=1`).

## Translations

Breadcrumb / page title key is `BreadcrumbModuleRoutingMap` (no underscore —
older `Breadcrumb_*` style does not work). Per-node-type labels follow the
`module_routing_map_Node{Type}` pattern. REST swagger tags are in
`rest_tag_ModuleRoutingMapGraph` and the per-action `rest_routing_map_*`
keys.

## Limitations the module deliberately accepts

- The graph is built from **UI-visible configuration only**. Custom dialplan
  files (`extensions_custom.conf`), hand-written contexts and dialplan
  generated by other modules at runtime are not visualised. The disclaimer
  banner in the volt view (`module_routing_map_Disclaimer`) tells users so.
- No live call overlay — this is a static snapshot, not a real-time monitor.
- Read-only by design. Editing the dialplan from the diagram is intentionally
  out of scope; nodes are click-throughs to existing admin-cabinet edit pages.

## CI / release

- `.github/workflows/build.yml` — thin wrapper that delegates to the org
  reusable workflow `mikopbx/.github-workflows/.github/workflows/extension-publish.yml@master`
  (`initial_version: "1.0"`).
- `module.json.version` is `%ModuleVersion%` — the workflow substitutes the
  real version derived from the latest release tag.
- `module.json.release_settings.{publish_release,changelog_enabled,create_github_release}`
  are all `true` — without this block the workflow builds the zip but never
  creates a GitHub release. The first published release was `v1.1`.
- Push to `master` / `main` / `develop` → CI bumps version, builds
  `ModuleRoutingMap.<ver>.zip`, generates changelog, creates the release and
  publishes to the MikoPBX marketplace.

`min_pbx_version` is currently `2025.1.1`. Bump only when the module starts
relying on newer core APIs.
