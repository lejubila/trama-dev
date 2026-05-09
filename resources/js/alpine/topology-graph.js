/**
 * Alpine factory wrapping a Cytoscape.js instance for the topology page.
 *
 * Wired with `x-data="topologyGraph({ graph, layout })"` `x-init="init($el)"`
 * on the wire:ignore container; the inner #cy/navigator <div>s are addressed
 * via $refs.
 *
 * The Livewire component re-renders the Blade with new props when filters
 * or layout change. We watch those server-rendered values via $watch and
 * tell Cytoscape to swap elements / replay the layout in place — never
 * re-instantiate, since that would tear down event listeners and pan/zoom
 * state.
 */
import cytoscape from 'cytoscape';
import coseBilkent from 'cytoscape-cose-bilkent';
import dagre from 'cytoscape-dagre';
import navigator from 'cytoscape-navigator';

let extensionsRegistered = false;
function registerExtensions() {
    if (extensionsRegistered) return;
    cytoscape.use(coseBilkent);
    cytoscape.use(dagre);
    cytoscape.use(navigator);
    extensionsRegistered = true;
}

const TYPE_COLOR = {
    switch: '#0891b2',
    router: '#7c3aed',
    firewall: '#dc2626',
    access_point: '#059669',
    controller: '#d97706',
    patch_panel: '#64748b',
    server: '#2563eb',
    ups: '#ca8a04',
    pdu: '#ca8a04',
    media_converter: '#c026d3',
    nas: '#0d9488',
    kvm: '#ea580c',
    other: '#6b7280',
};

const MEDIA_COLOR = {
    copper: '#94a3b8',
    fiber: '#fb923c',
    wireless: '#3b82f6',
    virtual: '#a855f7',
};

export default function topologyGraph({ graph, layout }) {
    return {
        cy: null,
        nav: null,
        _layout: layout || 'cose-bilkent',
        _exportHandler: null,

        init(rootEl) {
            registerExtensions();
            this._root = rootEl;

            this.cy = cytoscape({
                container: this.$refs.cy,
                elements: this._toElements(graph),
                style: this._stylesheet(),
                wheelSensitivity: 0.2,
                minZoom: 0.1,
                maxZoom: 4,
            });

            this._runLayout(this._layout);
            this._bindEvents();

            // Mini-map (cytoscape-navigator)
            try {
                this.nav = this.cy.navigator({ container: this.$refs.navigator });
            } catch (e) {
                // Navigator extension is best-effort; failures shouldn't break the graph.
                console.warn('cytoscape-navigator failed:', e);
            }

            // Expose for the toolbar buttons
            window.cy = this.cy;

            // PNG export listener (toolbar dispatches a window event)
            this._exportHandler = () => this._exportPNG();
            window.addEventListener('topology:export-png', this._exportHandler);

            // React to Livewire-driven prop changes coming through Alpine
            this.$watch('$wire.layout', (val) => {
                if (val && val !== this._layout) {
                    this._layout = val;
                    this._runLayout(val);
                }
            });

            // Refresh the graph elements whenever the Blade re-renders. The
            // simplest hook is to listen to Livewire's morph completed event
            // and re-read the props passed in via x-data — but the data prop
            // itself was captured at init. Instead, we listen for a custom
            // event the server can dispatch, plus poll on filter changes via
            // $wire helpers. Simpler: re-query the API whenever filters change.
            const refresh = () => this._refresh();
            ['siteId', 'statusFilter', 'vlanFilter', 'filterTypes']
                .forEach((k) => this.$watch('$wire.' + k, refresh));
        },

        destroy() {
            if (this._exportHandler) {
                window.removeEventListener('topology:export-png', this._exportHandler);
            }
            if (this.cy) this.cy.destroy();
        },

        _toElements(g) {
            const nodes = (g && g.nodes) || [];
            const edges = (g && g.edges) || [];
            return [...nodes, ...edges];
        },

        async _refresh() {
            // Ask the Livewire component for fresh data with the current filters.
            const data = await this.$wire.graphData();
            this.cy.elements().remove();
            this.cy.add(this._toElements(data));
            this._runLayout(this._layout);
        },

        _runLayout(name) {
            const opts = { name, animate: true, fit: true, padding: 20 };
            if (name === 'cose-bilkent') Object.assign(opts, { nodeRepulsion: 4500, idealEdgeLength: 80 });
            if (name === 'dagre') Object.assign(opts, { rankDir: 'TB', nodeSep: 40, rankSep: 60 });
            this.cy.layout(opts).run();
        },

        _bindEvents() {
            this.cy.on('tap', 'node', (evt) => {
                const id = String(evt.target.data('id') || '').replace('eq-', '');
                const num = parseInt(id, 10);
                if (!Number.isNaN(num)) {
                    window.Livewire.dispatch('equipment-clicked', { id: num });
                }
                this._highlightNeighborhood(evt.target);
            });

            this.cy.on('dbltap', 'node', (evt) => {
                const rackId = evt.target.data('rackId');
                if (rackId) window.location.href = `/racks/${rackId}`;
            });

            this.cy.on('tap', (evt) => {
                if (evt.target === this.cy) this._clearHighlight();
            });
        },

        _highlightNeighborhood(node) {
            const keep = node.closedNeighborhood();
            this.cy.elements().not(keep).addClass('faded');
            keep.removeClass('faded');
        },

        _clearHighlight() {
            this.cy.elements().removeClass('faded');
        },

        _stylesheet() {
            return [
                {
                    selector: 'node',
                    style: {
                        'label': 'data(label)',
                        'background-color': (ele) => TYPE_COLOR[ele.data('type')] ?? TYPE_COLOR.other,
                        'border-width': 1.5,
                        'border-color': '#1f2937',
                        'color': '#111827',
                        'font-size': 11,
                        'text-valign': 'bottom',
                        'text-margin-y': 6,
                        'width': 36,
                        'height': 36,
                    },
                },
                {
                    selector: 'edge',
                    style: {
                        'line-color': (ele) => MEDIA_COLOR[ele.data('media')] ?? '#94a3b8',
                        'line-style': (ele) => (ele.data('media') === 'wireless' ? 'dashed' : 'solid'),
                        'width': (ele) => Math.max(1.5, Math.log10((ele.data('speed') || 100)) - 0.5),
                        'curve-style': 'bezier',
                        'target-arrow-shape': 'none',
                        'label': 'data(label)',
                        'font-size': 9,
                        'color': '#475569',
                        'text-rotation': 'autorotate',
                        'text-background-color': '#fff',
                        'text-background-opacity': 0.8,
                        'text-background-padding': 1,
                    },
                },
                {
                    selector: 'node:selected',
                    style: { 'border-width': 4, 'border-color': '#6366f1' },
                },
                {
                    selector: 'edge:selected',
                    style: { 'line-color': '#6366f1', 'width': 4 },
                },
                {
                    selector: '.faded',
                    style: { 'opacity': 0.2 },
                },
            ];
        },

        _exportPNG() {
            if (!this.cy) return;
            const png = this.cy.png({ full: true, scale: 2, bg: '#ffffff' });
            const a = document.createElement('a');
            a.href = png;
            a.download = `topology-${Date.now()}.png`;
            a.click();
        },
    };
}
