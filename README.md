[![CI](https://github.com/mikopbx/ModuleRoutingMap/actions/workflows/build.yml/badge.svg)](https://github.com/mikopbx/ModuleRoutingMap/actions/workflows/build.yml) [![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0) [![GitHub Release](https://img.shields.io/github/v/release/mikopbx/ModuleRoutingMap)](https://github.com/mikopbx/ModuleRoutingMap/releases) [![PHP 8.4](https://img.shields.io/badge/PHP-8.4-777BB4.svg)](https://www.php.net/) [![React Flow](https://img.shields.io/badge/React%20Flow-12-FF0072.svg)](https://reactflow.dev/) [![MikoPBX 2025.1.1+](https://img.shields.io/badge/MikoPBX-2025.1.1%2B-1DBF73.svg)](https://www.mikopbx.com/) [![Issues](https://img.shields.io/github/issues/mikopbx/ModuleRoutingMap)](https://github.com/mikopbx/ModuleRoutingMap/issues)

[English](README.md) | [Русский](README.ru.md)

# ModuleRoutingMap

Interactive read-only diagram of incoming and outgoing call paths for MikoPBX, rendered with [React Flow](https://reactflow.dev/). Providers, DID routes, time conditions, IVR menus, queues and extensions are auto-collected from the current PBX configuration and laid out as a clickable graph — so an admin can audit a complex dialplan in seconds instead of clicking through six configuration pages.

![Routing Map — incoming tab](docs/images/routing-map.en.png)

## Why Routing Map

- **See the whole call path on one screen.** Provider → DID → time condition → IVR → queue → extension is rendered as a single graph. No more "which IVR does this number actually hit on weekends?".
- **Zero configuration.** The module reads MikoPBX's own configuration tables — there is nothing to set up, no extra database, no manual diagram editing. Click *Refresh* and the picture matches reality.
- **Click-through navigation.** Every node is a hyperlink to the corresponding admin-cabinet edit page (provider settings, route, IVR menu, queue, extension), so the diagram doubles as a navigator.
- **Read-only by design.** The module does not generate dialplan, does not modify Asterisk, does not write to MikoPBX tables. Safe to install on production.
- **Two views, two purposes.** The *Incoming* tab shows how external calls reach internal destinations; the *Outgoing* tab shows which dial patterns leave through which provider.

## Features

- Two graph tabs: **Incoming** (providers → routes → schedules → IVR/queues/extensions/conferences/apps) and **Outgoing** (dial-pattern rules → providers)
- Auto-layout via [dagre](https://github.com/dagrejs/dagre) — nodes never overlap, even on PBXes with hundreds of routes
- Distinct visual style per node type: provider, route/DID, time condition, IVR, queue, extension, conference, application, voicemail, external number
- Click any node to jump straight to its admin page (`node.data.href`)
- Pan, zoom, fit-view and minimap controls out of the box
- Refresh button per tab — re-fetches the live graph without reloading the page
- Built-in legend showing every node type used on the current diagram
- Read-only REST API v3 returning the raw `{ nodes, edges }` payload — feed it into your own audit tooling
- Self-contained IIFE bundle (~600 KB, React + ReactDOM + React Flow + dagre inlined) — no external CDN, works on air-gapped installations

## Screenshots

### Incoming — providers, DIDs, time conditions, destinations

![Incoming routing tab](docs/images/routing-map-incoming.en.png)

### Outgoing — patterns and providers

![Outgoing routing tab](docs/images/routing-map-outgoing.en.png)

## Installation

### From the MikoPBX Marketplace

1. Open the MikoPBX web interface.
2. Navigate to **Modules** → **Marketplace**.
3. Find **Routing Map** and click **Install**.
4. Enable the module on **Modules** → **Installed**.

### Manual installation

1. Download the latest `.zip` from the [Releases](https://github.com/mikopbx/ModuleRoutingMap/releases) page.
2. In MikoPBX, go to **Modules** → **Installed** → **Upload module** and pick the `.zip`.
3. Enable the module.

## Usage

1. Open **Modules → Routing Map**.
2. The **Incoming** tab loads automatically — you'll see the directed graph of every external entry point and where it lands. Drag the canvas to pan, scroll to zoom, double-click a node to focus it.
3. Switch to the **Outgoing** tab to see how internal extensions reach each provider via dial patterns.
4. Click any node to open the matching admin page (provider, route, IVR, queue, extension, …) in the same tab.
5. Hit **Refresh** after editing the dialplan elsewhere in the cabinet — the graph re-fetches without a full page reload.

> **Note.** The diagram is built **only from the data you see in the MikoPBX UI**. Custom dialplan files, `extensions_custom.conf` overrides, third-party module hooks and runtime-generated contexts will not appear. For very custom setups always cross-check via the Asterisk CLI.

## REST API v3

Auto-discovered via PHP 8 attributes. All endpoints under `/pbxcore/api/v3/module-routing-map/`. Auth: localhost or Bearer token.

| Method | Endpoint | Description |
|---|---|---|
| GET | `graph:incoming` | Directed graph of incoming routing (providers → routes → time conditions → IVR/queues/extensions) as `{ nodes, edges }` |
| GET | `graph:outgoing` | Directed graph of outbound routing (patterns → providers) as `{ nodes, edges }` |

Response shape:

```json
{
  "nodes": [
    { "id": "provider-1", "type": "provider", "data": { "label": "SIP-Trunk-Megafon", "href": "/admin-cabinet/providers/modify/1" } },
    { "id": "route-42",   "type": "route",    "data": { "label": "+74951234567",      "href": "/admin-cabinet/incoming-routes/modify/42" } }
  ],
  "edges": [
    { "id": "e-provider-1-route-42", "source": "provider-1", "target": "route-42" }
  ]
}
```

Example — fetch the incoming graph from a script:

```bash
curl -H "Authorization: Bearer $TOKEN" \
  https://pbx.example.com/pbxcore/api/v3/module-routing-map/graph:incoming
```

## Architecture

```
PHP — graph builders            — Lib/GraphBuilder/  (Incoming/Outgoing + NodeFactory)
PHP — REST controller           — Lib/RestAPI/Graph/ (auto-discovered, attribute-driven)
PHP — admin web controller      — App/Controllers/   (renders the index.volt page)
React Flow source               — react-app/         (NOT shipped to PBX)
Pre-built IIFE bundle           — public/assets/js/vendor/react-flow.bundle.js  (~600 KB)
Glue / fetch / mount            — public/assets/js/src/module-routing-map-index.js
Stylesheet                      — public/assets/css/module-routing-map.css
```

The PHP side reads providers, incoming routes, outgoing routes, time conditions, IVR menus, queues, extensions and conferences from MikoPBX's standard models, then `NodeFactory` turns each row into a typed graph node. The React bundle is a single self-contained IIFE that exposes exactly one global:

```js
window.MikoRoutingMap.mount(htmlElement, graph, options?)
```

The glue code fetches the JSON from the REST endpoint, unwraps the `PBXApiResult` envelope and calls `mount` once per tab. Custom node components for every node type live under `react-app/src/nodes/`.

## Building the React bundle

The marketplace artefact ships the pre-built bundle, so end users do **not** need Node.js. To rebuild it after editing the React source:

```bash
cd react-app
npm install
npm run build
```

`npm run build` produces `../public/assets/js/vendor/react-flow.bundle.js` — a single IIFE with React, ReactDOM, `@xyflow/react` and `dagre` inlined. Commit the resulting bundle alongside your source changes.

## Requirements

- MikoPBX **2025.1.1+**
- PHP **8.4** (server-side; bundled with MikoPBX)
- A modern browser with ES2017 support — bundle is shipped untranspiled below that level

## Limitations

- The diagram reflects the **UI configuration only**. `extensions_custom.conf`, hand-written contexts, and dialplan generated by other modules at runtime are not visualised.
- The graph is a snapshot — there is no live call overlay. Use MikoPBX's CDR / live calls page for that.
- Read-only: editing the dialplan from the diagram is intentionally out of scope.

## Support

- **Issues:** [GitHub Issues](https://github.com/mikopbx/ModuleRoutingMap/issues)
- **Telegram:** [@mikopbx_dev](https://t.me/mikopbx_dev)

## License

GPL-3.0-or-later. React, React Flow and dagre are MIT-licensed.
