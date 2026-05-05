/* global $, window, document */

/**
 * ModuleRoutingMap — page controller.
 *
 * Wires Fomantic UI tabs to the React Flow bundle exposed as window.MikoRoutingMap.
 * For each direction (incoming / outgoing) the controller:
 *   1. fetches graph JSON from the v3 REST endpoint via $.api (Semantic UI's API
 *      plugin) so Bearer-token/CSRF handling stays identical to the rest of the
 *      admin cabinet,
 *   2. unwraps the { result: true, data: { nodes, edges } } envelope, and
 *   3. hands the graph to MikoRoutingMap.mount().
 *
 * The page stays usable without React (loader spinner and error message) if the
 * bundle failed to load — the glue code reports the failure as a visible status.
 */
const ModuleRoutingMapIndex = {
    loaded: {
        incoming: false,
        outgoing: false,
    },

    initialize() {
        $('#routing-map-tabs .item').tab({
            onVisible(tabName) {
                ModuleRoutingMapIndex.ensureLoaded(tabName);
            },
        });

        $('#routing-map-refresh-incoming').on('click', (event) => {
            event.preventDefault();
            ModuleRoutingMapIndex.loadGraph('incoming', true);
        });
        $('#routing-map-refresh-outgoing').on('click', (event) => {
            event.preventDefault();
            ModuleRoutingMapIndex.loadGraph('outgoing', true);
        });

        // First render — the incoming tab is active on load.
        ModuleRoutingMapIndex.ensureLoaded('incoming');
    },

    ensureLoaded(direction) {
        if (ModuleRoutingMapIndex.loaded[direction]) {
            return;
        }
        ModuleRoutingMapIndex.loadGraph(direction, false);
    },

    loadGraph(direction, force) {
        const canvas = document.getElementById(`routing-map-${direction}`);
        const statusEl = document.getElementById(`routing-map-status-${direction}`);
        if (canvas === null) {
            return;
        }
        if (!force && ModuleRoutingMapIndex.loaded[direction]) {
            return;
        }

        ModuleRoutingMapIndex.setStatus(statusEl, 'loading');
        ModuleRoutingMapIndex.renderLoader(canvas);

        const endpoint = canvas.getAttribute('data-endpoint');

        // WHY: $.api (Semantic UI's jQuery API plugin) automatically attaches the
        // Bearer token and CSRF headers MikoPBX expects on authenticated v3
        // endpoints, exactly like the reference ModuleExampleRestAPIv3 module.
        // Raw fetch() would bypass this and get HTTP 401.
        $.api({
            url: endpoint,
            method: 'GET',
            on: 'now',
            onSuccess(response) {
                const graph = ModuleRoutingMapIndex.extractGraph(response);
                if (graph === null) {
                    ModuleRoutingMapIndex.handleError(canvas, statusEl, 'Empty graph payload');
                    return;
                }
                if (typeof window.MikoRoutingMap === 'undefined'
                    || typeof window.MikoRoutingMap.mount !== 'function') {
                    ModuleRoutingMapIndex.handleError(canvas, statusEl, 'React Flow bundle not loaded');
                    return;
                }
                try {
                    window.MikoRoutingMap.mount(canvas, graph, {
                        direction,
                        onNodeClick: ModuleRoutingMapIndex.handleNodeClick,
                    });
                    ModuleRoutingMapIndex.loaded[direction] = true;
                    ModuleRoutingMapIndex.setStatus(
                        statusEl,
                        'ready',
                        graph.nodes.length,
                        graph.edges.length
                    );
                } catch (error) {
                    ModuleRoutingMapIndex.handleError(canvas, statusEl, error.message || String(error));
                }
            },
            onFailure(response) {
                const message = ModuleRoutingMapIndex.formatFailure(response);
                ModuleRoutingMapIndex.handleError(canvas, statusEl, message);
            },
            onError(errorMessage) {
                ModuleRoutingMapIndex.handleError(
                    canvas,
                    statusEl,
                    errorMessage || 'Network error'
                );
            },
        });
    },

    /**
     * Accepts the PBXApiResult envelope used by all v3 endpoints:
     *   { result: true, data: { nodes: [...], edges: [...] }, messages: [...] }
     * and also tolerates a raw { nodes, edges } shape for defensive parsing.
     */
    extractGraph(payload) {
        if (payload === null || typeof payload !== 'object') {
            return null;
        }
        if (payload.data
            && Array.isArray(payload.data.nodes)
            && Array.isArray(payload.data.edges)) {
            return payload.data;
        }
        if (Array.isArray(payload.nodes) && Array.isArray(payload.edges)) {
            return payload;
        }
        return null;
    },

    formatFailure(response) {
        if (response && response.messages) {
            if (Array.isArray(response.messages)) {
                return response.messages.join(', ');
            }
            if (response.messages.error) {
                return Array.isArray(response.messages.error)
                    ? response.messages.error.join(', ')
                    : String(response.messages.error);
            }
        }
        if (response && response.error) {
            return String(response.error);
        }
        return 'Request failed';
    },

    handleError(canvas, statusEl, message) {
        ModuleRoutingMapIndex.renderError(canvas, message);
        ModuleRoutingMapIndex.setStatus(statusEl, 'error', message);
    },

    handleNodeClick(node) {
        if (node && node.data && typeof node.data.href === 'string' && node.data.href !== '') {
            window.location.href = node.data.href;
        }
    },

    renderLoader(canvas) {
        canvas.innerHTML = '<div class="ui active centered inline loader"></div>';
    },

    renderError(canvas, message) {
        const safeMessage = $('<div>').text(String(message)).html();
        canvas.innerHTML = `
            <div class="ui negative message">
                <div class="header">Failed to load routing graph</div>
                <p>${safeMessage}</p>
            </div>
        `;
    },

    setStatus(statusEl, state, ...details) {
        if (statusEl === null) {
            return;
        }
        statusEl.classList.remove('blue', 'green', 'red', 'grey');
        switch (state) {
            case 'loading':
                statusEl.classList.add('blue');
                statusEl.textContent = 'Loading…';
                break;
            case 'ready':
                statusEl.classList.add('green');
                statusEl.textContent = `${details[0]} nodes / ${details[1]} edges`;
                break;
            case 'error':
                statusEl.classList.add('red');
                statusEl.textContent = `Error: ${details[0] || 'unknown'}`;
                break;
            default:
                statusEl.classList.add('grey');
                statusEl.textContent = 'Idle';
        }
    },
};

$(document).ready(() => {
    ModuleRoutingMapIndex.initialize();
});
