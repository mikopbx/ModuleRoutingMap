import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import cssInjectedByJs from 'vite-plugin-css-injected-by-js';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));

// Builds a single IIFE bundle consumed by MikoPBX as a classic <script> tag.
// React, ReactDOM and @xyflow/react are all inlined — the PBX has no Node
// runtime and ships the pre-built artifact directly under public/assets/js/vendor.
// React, ReactDOM and @xyflow/react embed development-only checks guarded by
// `if (process.env.NODE_ENV !== 'production')`. Vite's library build mode does
// NOT auto-replace those references (unlike the normal app build), so without
// the `define` block below the bundle throws `ReferenceError: process is not
// defined` on load. We replace both the nested key and the bare `process.env`
// object so any other `process.env.X` access degrades to `undefined` instead
// of throwing.
export default defineConfig({
  plugins: [react(), cssInjectedByJs()],
  define: {
    'process.env.NODE_ENV': JSON.stringify('production'),
    'process.env': JSON.stringify({ NODE_ENV: 'production' }),
  },
  build: {
    emptyOutDir: false,
    lib: {
      entry: path.resolve(__dirname, 'src/main.jsx'),
      name: 'MikoRoutingMap',
      formats: ['iife'],
      fileName: () => 'react-flow.bundle.js',
    },
    outDir: path.resolve(__dirname, '../public/assets/js/vendor'),
    rollupOptions: {
      output: {
        extend: true,
        globals: {},
        inlineDynamicImports: true,
        assetFileNames: 'react-flow.bundle.[ext]',
      },
    },
    cssCodeSplit: false,
    minify: 'esbuild',
    sourcemap: false,
    target: 'es2019',
  },
});
