import { createRoot } from 'react-dom/client';
import App from './App.jsx';

// Mount registry keyed by DOM element. We always tear down and recreate the
// React root on every mount() call — this is intentional:
//
// React Flow's `fitView` prop only runs on *initial* render. If we reused the
// root and just called render() again, subsequent mount calls would push new
// node/edge props in but leave the viewport wherever it was (frequently at
// 0,0 zoom 1 because the first mount happened before the tab's layout was
// settled and React Flow measured a 0x0 parent). Tearing down and recreating
// the root makes every mount a fresh initial render → fitView always runs
// against the current, correctly-measured container.
const roots = new WeakMap();

function mount(container, graph, options = {}) {
  if (!(container instanceof Element)) {
    throw new TypeError('MikoRoutingMap.mount: first argument must be a DOM element');
  }
  if (!graph || !Array.isArray(graph.nodes) || !Array.isArray(graph.edges)) {
    throw new TypeError('MikoRoutingMap.mount: graph must be { nodes: [], edges: [] }');
  }

  unmount(container);
  container.innerHTML = '';
  const root = createRoot(container);
  roots.set(container, root);

  root.render(
    <App
      graph={graph}
      direction={options.direction || 'incoming'}
      onNodeClick={options.onNodeClick}
    />
  );
}

function unmount(container) {
  const root = roots.get(container);
  if (root) {
    try {
      root.unmount();
    } catch (err) {
      // Ignore teardown errors — the next mount will replace the DOM anyway.
    }
    roots.delete(container);
    container.innerHTML = '';
  }
}

export default { mount, unmount };
