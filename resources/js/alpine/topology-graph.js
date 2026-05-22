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
    phone_system: '#4f46e5',
    access_control: '#65a30d',
    nvr: '#0284c7',
    camera: '#db2777',
    intercom: '#e11d48',
    other: '#6b7280',
};

const MEDIA_COLOR = {
    copper: '#94a3b8',
    fiber: '#fb923c',
    wireless: '#3b82f6',
    virtual: '#a855f7',
};

export default function topologyGraph({ graph, layout, iconSize, restore }) {
    return {
        cy: null,
        nav: null,
        _layout: layout || 'cose-bilkent',
        _exportHandler: null,
        // Global per-tenant icon size for the topology view. Bound to the
        // slider in the toolbar; on input we apply live, on change we persist.
        globalIconSize: iconSize || 44,
        // Mini-map (navigator) is hidden by default; toggled via the corner button.
        showNavigator: false,

        init(rootEl) {
            registerExtensions();
            this._root = rootEl;

            this.cy = cytoscape({
                container: this.$refs.cy,
                elements: this._toElements(graph),
                style: this._stylesheet(),
                minZoom: 0.1,
                maxZoom: 4,
            });

            const restored = this._applyRestore(restore);
            this._computeIdealLengths();
            if (!restored) {
                this._runLayout(this._layout);
            } else {
                // Positions already applied verbatim; skip the first layout run
                // so it doesn't override them. Mark layoutRanOnce so later
                // user-triggered relayouts don't randomize.
                this._layoutRanOnce = true;
            }
            this._bindEvents();
            this._applyIconSizes();

            // Mini-map (cytoscape-navigator) is created lazily the first time
            // it's shown — see toggleNavigator(). Initialising it here while
            // the container is hidden (display:none, zero size) produces a
            // blank/invisible thumbnail.

            // Expose for the toolbar buttons
            window.cy = this.cy;

            // PNG export listener (toolbar dispatches a window event). We
            // dedupe via a single slot on `window` so re-initialising the
            // component (e.g. after a Livewire navigate) doesn't pile up
            // handlers — that's why two identical files were being saved.
            if (window._tramaTopologyExportHandler) {
                window.removeEventListener('topology:export-png', window._tramaTopologyExportHandler);
            }
            this._exportHandler = () => this._exportPNG();
            window._tramaTopologyExportHandler = this._exportHandler;
            window.addEventListener('topology:export-png', this._exportHandler);

            // Capture-to-Livewire listener for snapshot save. Dedupe the same
            // way as the PNG export handler.
            if (window._tramaTopologySnapshotCaptureHandler) {
                window.removeEventListener('snapshot-capture-png', window._tramaTopologySnapshotCaptureHandler);
            }
            this._snapshotCaptureHandler = (evt) => {
                const componentId = evt && evt.detail ? evt.detail.componentId : null;
                if (!componentId) return;
                const dataUrl = this._buildPng();
                if (!dataUrl) return;
                const cmp = window.Livewire && window.Livewire.find ? window.Livewire.find(componentId) : null;
                if (!cmp) return;
                // Push the data URL directly onto the Livewire property; the
                // server decodes base64 and writes the PNG on save. This
                // bypasses the file-upload pipeline, which doesn't handle
                // very large synthetic blobs reliably.
                cmp.set('snapshotImageBase64', dataUrl);
            };
            window._tramaTopologySnapshotCaptureHandler = this._snapshotCaptureHandler;
            window.addEventListener('snapshot-capture-png', this._snapshotCaptureHandler);

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
            ['siteId', 'roomFilter', 'statusFilter', 'vlanFilter', 'tagFilters', 'filterTypes', 'includeHidden', 'groupByRack', 'groupBySite', 'groupByRoom']
                .forEach((k) => this.$watch('$wire.' + k, refresh));
        },

        destroy() {
            if (this._exportHandler) {
                window.removeEventListener('topology:export-png', this._exportHandler);
                if (window._tramaTopologyExportHandler === this._exportHandler) {
                    window._tramaTopologyExportHandler = null;
                }
            }
            if (this._snapshotCaptureHandler) {
                window.removeEventListener('snapshot-capture-png', this._snapshotCaptureHandler);
                if (window._tramaTopologySnapshotCaptureHandler === this._snapshotCaptureHandler) {
                    window._tramaTopologySnapshotCaptureHandler = null;
                }
            }
            if (this.cy) {
                try { this.cy.destroy(); } catch (e) { /* ignore */ }
                this.cy = null;
            }
            if (window.cy && (window.cy === this.cy || (typeof window.cy.destroyed === 'function' && window.cy.destroyed()))) {
                window.cy = null;
            }
        },

        toggleNavigator() {
            this.showNavigator = !this.showNavigator;
            const el = this.$refs.navigator;
            if (!el) return;

            // Drive visibility directly (don't rely on x-show reactivity): show
            // first so the container has real dimensions, THEN create/resize the
            // navigator thumbnail so it isn't built against a 0×0 box.
            el.style.display = this.showNavigator ? '' : 'none';
            if (!this.showNavigator) return;

            this.$nextTick(() => {
                try {
                    if (!this.nav) {
                        // String selector (not the DOM node) so the extension
                        // reuses our #topology-navigator div instead of creating
                        // its own panel on <body>.
                        this.nav = this.cy.navigator({ container: '#topology-navigator' });
                    }
                    // The navigator only paints its thumbnail on the next cy
                    // *render* (it registers a throttled cy.onRender handler).
                    // After the panel becomes visible the graph is static, so
                    // force a render — otherwise the thumbnail stays a broken
                    // <img> until the user pans. cy.resize() also re-reads the
                    // now-visible panel size via the navigator's resize listener.
                    requestAnimationFrame(() => {
                        try {
                            if (this.nav) this.nav.resize();
                            this.cy.resize();
                            this.cy.emit('render');
                        } catch (e) { /* ignore */ }
                    });
                } catch (e) {
                    console.warn('cytoscape-navigator failed:', e);
                }
            });
        },

        _toElements(g) {
            const nodes = (g && g.nodes) || [];
            const edges = (g && g.edges) || [];
            return [...nodes, ...edges];
        },

        async _refresh() {
            // Ask the Livewire component for fresh data with the current filters.
            const data = await this.$wire.graphData();

            // 1) Snapshot current positions of all visible nodes (by id) so
            //    we can keep them stable across the swap.
            const prevPositions = {};
            // Track whether compound (rack) parents existed before the swap.
            // Toggling group-by-rack changes the meaningful topology of the
            // graph (children must be reclustered into compounds), so we
            // can't just preserve old positions.
            const prevHadCompounds = this.cy.nodes(':parent').length > 0;

            this.cy.nodes().forEach((n) => {
                const p = n.position();
                prevPositions[n.id()] = { x: p.x, y: p.y };
            });

            // 2) Swap elements.
            this.cy.elements().remove();
            this.cy.add(this._toElements(data));

            // 3) Restore positions for nodes that were already on the canvas;
            //    collect the "new" ones (e.g. previously-hidden equipment that
            //    just appeared, or a brand-new device).
            const knownIds = new Set(Object.keys(prevPositions));
            const newNodes = [];
            this.cy.nodes().forEach((n) => {
                const prev = prevPositions[n.id()];
                if (prev) {
                    n.position(prev);
                } else {
                    newNodes.push(n);
                }
            });

            const nowHasCompounds = this.cy.nodes(':parent').length > 0;
            const compoundsToggled = prevHadCompounds !== nowHasCompounds;
            const totalNodes = this.cy.nodes().length;
            const majorChange =
                knownIds.size === 0
                || (totalNodes > 0 && newNodes.length / totalNodes > 0.5)
                || compoundsToggled;

            this._computeIdealLengths();

            if (majorChange) {
                // First refresh, or the dataset changed too much (e.g. site
                // switch) — run a fresh randomized layout.
                this._layoutRanOnce = false;
                this._runLayout(this._layout);
            } else {
                // Minor delta (typical for "Mostra nascosti" toggle, type
                // filter change, etc.): keep existing positions verbatim and
                // place each new node near a known neighbor; fallback to the
                // bbox center of known positions with a small grid jitter.
                if (newNodes.length > 0) {
                    const xs = Object.values(prevPositions).map((p) => p.x);
                    const ys = Object.values(prevPositions).map((p) => p.y);
                    const cx = (Math.min(...xs) + Math.max(...xs)) / 2;
                    const cy_ = (Math.min(...ys) + Math.max(...ys)) / 2;
                    newNodes.forEach((n, i) => {
                        const neighbor = n.neighborhood('node').filter((x) => knownIds.has(x.id())).first();
                        if (neighbor && neighbor.length) {
                            const np = neighbor.position();
                            n.position({ x: np.x + 40, y: np.y + 40 });
                        } else {
                            n.position({
                                x: cx + (i % 5) * 30 - 60,
                                y: cy_ + Math.floor(i / 5) * 30 - 60,
                            });
                        }
                    });
                }
                // Mark layoutRanOnce so a manual layout-change later doesn't
                // randomize from scratch.
                this._layoutRanOnce = true;
            }

            this._applyIconSizes();
            this.cy.style().update();
        },

        /**
         * Apply restored node positions, zoom and pan from a saved snapshot.
         * Returns true if any positions were applied (so caller skips the
         * first auto-layout), false otherwise. Called once in init() — never
         * from _refresh(), because changing filters intentionally relayouts.
         */
        _applyRestore(restore) {
            if (!restore || !restore.nodePositions) return false;
            const positions = restore.nodePositions;
            const knownIds = Object.keys(positions);
            if (knownIds.length === 0) return false;

            const known = new Set(knownIds);
            // 1) place known leaf nodes at saved coordinates. Skip compound
            // parents (rack/room/site groups): their box is derived from their
            // children, and setting a parent's position would drag the children
            // off their restored coordinates.
            this.cy.nodes().forEach((n) => {
                if (n.isParent()) return;
                const p = positions[n.id()];
                if (p && Array.isArray(p) && p.length === 2) {
                    n.position({ x: Number(p[0]), y: Number(p[1]) });
                }
            });

            // 2) compute bbox center of known positions for new-node fallback.
            const xs = knownIds.map((id) => Number(positions[id][0]));
            const ys = knownIds.map((id) => Number(positions[id][1]));
            const cx = (Math.min(...xs) + Math.max(...xs)) / 2;
            const cy_ = (Math.min(...ys) + Math.max(...ys)) / 2;

            // 3) nodes not in the saved set: place near a known neighbor when
            // possible, else near bbox center with a small grid jitter.
            let unknownIdx = 0;
            this.cy.nodes().forEach((n) => {
                if (n.isParent() || known.has(n.id())) return;
                const neighbor = n.neighborhood('node').filter((x) => known.has(x.id())).first();
                if (neighbor && neighbor.length) {
                    const np = neighbor.position();
                    n.position({ x: np.x + 40, y: np.y + 40 });
                } else {
                    const i = unknownIdx++;
                    n.position({
                        x: cx + (i % 5) * 30 - 60,
                        y: cy_ + Math.floor(i / 5) * 30 - 60,
                    });
                }
            });

            // 4) restore viewport faithfully. Children are kept at their exact
            // saved coordinates (no compression), so the saved zoom + pan
            // reproduce the original framing — including when group-by-rack is
            // active and the canvas holds compound parents. Fall back to fit()
            // only for legacy snapshots that stored neither zoom nor pan.
            const hasZoom = typeof restore.zoom === 'number' && isFinite(restore.zoom) && restore.zoom > 0;
            const hasPan = Array.isArray(restore.pan) && restore.pan.length === 2;
            if (hasZoom && hasPan) {
                this.cy.viewport({
                    zoom: restore.zoom,
                    pan: { x: Number(restore.pan[0]), y: Number(restore.pan[1]) },
                });
            } else if (hasPan) {
                this.cy.pan({ x: Number(restore.pan[0]), y: Number(restore.pan[1]) });
            } else {
                this.cy.fit(undefined, 40);
            }
            return true;
        },

        _runLayout(name) {
            const opts = { name, animate: true, fit: true, padding: 20 };
            // cose-bilkent's `idealEdgeLength` is a single number (passing a
            // function crashes `calcGrid` with "Invalid array length"). We
            // pick the longest per-edge ideal so the worst-case label still
            // fits, clamped so a single very long label doesn't inflate the
            // whole graph.
            let maxIdeal = 80;
            this.cy.edges().forEach((e) => {
                const v = parseInt(e.data('idealLength') || 0, 10);
                if (v > maxIdeal) maxIdeal = v;
            });
            const globalIdeal = Math.min(800, Math.max(80, maxIdeal));
            // Spacing factor pads layouts uniformly so even shorter edges
            // inherit some of the largest edge's clearance.
            const spacing = Math.max(1.0, globalIdeal / 200);

            // True when at least one compound (rack) parent is present.
            // Compound layouts need tuned parameters to keep groups apart.
            const hasCompounds = this.cy.nodes(':parent').length > 0;

            if (name === 'cose-bilkent') {
                // Compound (group-by-rack) layout strategy: shrink everything
                // — idealEdgeLength capped low, nodeRepulsion reduced,
                // gravity boosted hard — so rack-compounds nest tightly
                // around the centre and don't get spread across the canvas.
                if (hasCompounds) {
                    Object.assign(opts, {
                        nodeRepulsion: 2500,
                        idealEdgeLength: Math.min(80, globalIdeal),
                        edgeElasticity: 0.45,
                        randomize: !this._layoutRanOnce,
                        numIter: 3500,
                        nestingFactor: 0.1,
                        gravity: 1.2,
                        gravityRange: 1.5,
                        gravityCompound: 2.0,
                        gravityRangeCompound: 1.0,
                        tile: true,
                        tilingPaddingVertical: 6,
                        tilingPaddingHorizontal: 6,
                        initialEnergyOnIncremental: 0.3,
                    });
                } else {
                    Object.assign(opts, {
                        nodeRepulsion: 4500,
                        idealEdgeLength: globalIdeal,
                        edgeElasticity: 0.45,
                        randomize: !this._layoutRanOnce,
                        numIter: 2500,
                    });
                }
            }
            if (name === 'dagre') {
                Object.assign(opts, {
                    rankDir: 'TB',
                    // Modest widening of separators when compounds are present
                    // so rack boxes don't visually overlap, without exploding
                    // the overall canvas.
                    nodeSep: (hasCompounds ? 90 : 50) * spacing,
                    rankSep: (hasCompounds ? 130 : 100) * spacing,
                    edgeSep: 30,
                });
            }
            if (name === 'breadthfirst') Object.assign(opts, { spacingFactor: spacing * (hasCompounds ? 1.3 : 1) });
            if (name === 'circle') Object.assign(opts, { spacingFactor: spacing * (hasCompounds ? 1.3 : 1) });
            this.cy.layout(opts).run();
            this._layoutRanOnce = true;
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
                if (evt.target === this.cy) {
                    // Tap on empty canvas: drop selection AND restore full
                    // opacity on every node and edge.
                    this.cy.elements(':selected').unselect();
                    this._clearHighlight();
                }
            });

            // Safety net: whenever the user (or Cytoscape's default bg-tap
            // behaviour) deselects everything, the highlight has no anchor
            // left → restore full opacity on the entire graph.
            this.cy.on('unselect', () => {
                if (this.cy.$(':selected').length === 0) {
                    this._clearHighlight();
                }
            });

            // Icons scale with the zoom level (Cytoscape's default behaviour).
            // We only apply per-node sizes once when the slider value changes
            // or when the graph is (re)loaded; the renderer handles zoom.

            // Re-evaluate edge label offsets whenever something that may
            // affect overlap changes. Style functions read from
            // source/target nodes and edge label length, but Cytoscape only
            // re-runs them on demand → we trigger restyle here. Debounced
            // to avoid running on every zoom frame.
            let restyleTimer = null;
            const scheduleRestyle = () => {
                if (restyleTimer) return;
                restyleTimer = setTimeout(() => {
                    restyleTimer = null;
                    this.cy.style().update();
                }, 50);
            };
            this.cy.on('zoom', scheduleRestyle);
            this.cy.on('layoutstop', scheduleRestyle);
            this.cy.on('add remove data', 'edge', scheduleRestyle);
        },

        /**
         * Apply the per-node iconSize (in graph units) to width/height.
         * Called when the global slider changes or after a graph reload;
         * Cytoscape's zoom handles visual scaling automatically.
         */
        _applyIconSizes() {
            this.cy.nodes().forEach((n) => {
                const u = parseInt(n.data('iconSize') || 44, 10);
                n.style({ width: u, height: u });
            });
        },

        /**
         * Recompute per-edge `idealLength` so the line is long enough to
         * hold source port + center label + target port without any text
         * overlap. Depends on the *current* iconSize (which drives font
         * size) and on the actual label lengths.
         */
        _computeIdealLengths() {
            this.cy.edges().forEach((edge) => {
                const srcSize = parseInt(edge.source().data('iconSize') || 44, 10);
                const tgtSize = parseInt(edge.target().data('iconSize') || 44, 10);
                const avgSize = (srcSize + tgtSize) / 2;
                const fontPx = Math.max(6, avgSize * 0.20);
                const nodeFontPx = Math.max(6, avgSize * 0.25);
                const nodeLabelH = nodeFontPx + Math.max(2, avgSize * 0.12);
                const charWidth = fontPx * 0.6; // monospace
                const srcChars = (edge.data('fromIface') || '').length;
                const tgtChars = (edge.data('toIface') || '').length;
                const ctrChars = (edge.data('label') || '').length;
                // total port + cable text + both icons + both node-label
                // clearances + extra margin.
                const text = (srcChars + tgtChars + ctrChars) * charWidth;
                const icons = srcSize + tgtSize; // full diameter of both
                const ideal = Math.round(text + icons + 2 * nodeLabelH + 40);
                edge.data('idealLength', Math.max(80, ideal));
            });
        },

        /**
         * Called by the global size slider in the toolbar. Updates every
         * node's `iconSize` data attribute and applies the new size live
         * (counter-scaled to current zoom).
         */
        applyGlobalSize(size) {
            const px = Math.max(16, Math.min(200, parseInt(size, 10)));
            this.globalIconSize = px;
            this.cy.nodes().forEach((n) => n.data('iconSize', px));
            this._applyIconSizes();
            // Style functions that read source/target.data('iconSize') are
            // not automatically re-evaluated when a neighbour's data changes,
            // so force a full restyle to update edge font + text offsets.
            this.cy.style().update();
        },

        persistGlobalSize() {
            this.$wire.setTopologyIconSize(this.globalIconSize);
            // Edge ideal lengths depend on iconSize-driven font size, so
            // recompute and reflow once the user releases the slider to
            // avoid mid-drag layout thrashing.
            this._computeIdealLengths();
            this._runLayout(this._layout);
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
                        // Node label scales with the node's iconSize.
                        'font-size': (ele) => Math.max(6, (ele.data('iconSize') || 44) * 0.25),
                        'text-valign': 'bottom',
                        'text-margin-y': (ele) => Math.max(2, (ele.data('iconSize') || 44) * 0.12),
                        'width': 36,
                        'height': 36,
                    },
                },
                {
                    selector: 'edge',
                    style: {
                        // Prefer per-connection color (hex from cable_color
                        // picker) when set; fall back to media-based palette.
                        'line-color': (ele) => ele.data('color') || MEDIA_COLOR[ele.data('media')] || '#94a3b8',
                        'line-style': (ele) => (ele.data('media') === 'wireless' ? 'dashed' : 'solid'),
                        'width': (ele) => Math.max(1.5, Math.log10((ele.data('speed') || 100)) - 0.5),
                        'curve-style': 'bezier',
                        'target-arrow-shape': 'none',

                        // Edge text scales with the source node's iconSize
                        // (all nodes share the same global size, so this is
                        // effectively the global icon size).
                        'font-size': (ele) => Math.max(6, (ele.source().data('iconSize') || 44) * 0.20),
                        'color': '#475569',
                        'text-background-color': '#fff',
                        'text-background-opacity': 0.85,
                        'text-background-padding': 2,
                        'font-family': 'ui-monospace, SFMono-Regular, Menlo, monospace',
                        // Keep labels readable when the canvas is zoomed out.
                        'min-zoomed-font-size': 6,
                    },
                },
                {
                    // Center label = cable label, only when present on the edge.
                    selector: 'edge[label]',
                    style: {
                        'label': 'data(label)',
                        'text-rotation': 'autorotate',
                    },
                },
                {
                    // Source port name, only when present on the edge.
                    selector: 'edge[fromIface]',
                    style: {
                        'source-label': 'data(fromIface)',
                        'source-text-offset': (ele) => {
                            const size = ele.source().data('iconSize') || 44;
                            const fontPx = Math.max(6, size * 0.20);
                            const nodeFontPx = Math.max(6, size * 0.25);
                            const nodeLabelHeight = nodeFontPx + Math.max(2, size * 0.12);
                            const chars = (ele.data('fromIface') || '').length;
                            // Monospace ratio ≈ 0.6 → half-width = chars * font * 0.30.
                            const halfLabel = chars * fontPx * 0.30;
                            return size / 2 + nodeLabelHeight + halfLabel + 6;
                        },
                        'source-text-rotation': 'autorotate',
                    },
                },
                {
                    selector: 'edge[toIface]',
                    style: {
                        'target-label': 'data(toIface)',
                        'target-text-offset': (ele) => {
                            const size = ele.target().data('iconSize') || 44;
                            const fontPx = Math.max(6, size * 0.20);
                            const nodeFontPx = Math.max(6, size * 0.25);
                            const nodeLabelHeight = nodeFontPx + Math.max(2, size * 0.12);
                            const chars = (ele.data('toIface') || '').length;
                            const halfLabel = chars * fontPx * 0.30;
                            return size / 2 + nodeLabelHeight + halfLabel + 6;
                        },
                        'target-text-rotation': 'autorotate',
                    },
                },
                {
                    // When the server resolved an icon URL for this node,
                    // render it as a background image instead of a flat color.
                    selector: 'node[icon]',
                    style: {
                        'background-image': (ele) => `url(${ele.data('icon')})`,
                        'background-fit': 'contain',
                        'background-color': '#ffffff',
                        'background-opacity': 1,
                        'border-color': '#94a3b8',
                        'width': 44,
                        'height': 44,
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
                {
                    // Generic compound fallback — covers any future kind and
                    // ensures background-image: none so nothing leaks from
                    // the node[icon] rule.
                    selector: 'node:parent',
                    style: {
                        'shape': 'round-rectangle',
                        'background-image': 'none',
                        'padding': 10,
                        'label': 'data(label)',
                        'text-valign': 'top',
                        'text-halign': 'center',
                        'text-margin-y': -6,
                        'font-size': 11,
                        'font-weight': 600,
                    },
                },
                {
                    // Rack compound — solid grey, dashed slate border.
                    selector: 'node:parent[kind = "rack"]',
                    style: {
                        'background-color': '#f1f5f9',
                        'background-opacity': 0.45,
                        'border-color': '#64748b',
                        'border-width': 1.5,
                        'border-style': 'dashed',
                        'color': '#334155',
                    },
                },
                {
                    // Room compound (unracked devices grouped by location):
                    // straw-yellow background with a dotted amber border so
                    // it reads visually distinct from rack compounds.
                    selector: 'node:parent[kind = "room"]',
                    style: {
                        'background-color': '#fefce8',
                        'background-opacity': 0.5,
                        'border-color': '#a16207',
                        'border-width': 1.5,
                        'border-style': 'dotted',
                        'color': '#713f12',
                    },
                },
                {
                    // Site compound — solid indigo border, lighter fill.
                    // Sits above rack/room compounds when both group flags
                    // are active (3-level nesting).
                    selector: 'node:parent[kind = "site"]',
                    style: {
                        'background-color': '#eef2ff',
                        'background-opacity': 0.35,
                        'border-color': '#4f46e5',
                        'border-width': 2,
                        'border-style': 'solid',
                        'shape': 'round-rectangle',
                        'padding': 14,
                        'text-valign': 'top',
                        'text-halign': 'center',
                        'font-size': 11,
                        'font-weight': 'bold',
                        'color': '#312e81',
                        'text-margin-y': -6,
                    },
                },
            ];
        },

        _buildPng() {
            if (!this.cy) return null;
            try {
                if (typeof this.cy.destroyed === 'function' && this.cy.destroyed()) return null;
                return this.cy.png({ full: true, scale: 2, bg: '#ffffff' });
            } catch (e) {
                console.warn('cy.png failed:', e);
                return null;
            }
        },

        _exportPNG() {
            const png = this._buildPng();
            if (!png) return;
            const a = document.createElement('a');
            a.href = png;
            a.download = `topology-${Date.now()}.png`;
            a.click();
        },
    };
}
