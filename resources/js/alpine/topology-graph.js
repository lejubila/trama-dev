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
    wall_outlet: '#78716c',
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
    wifi_network: '#0ea5e9',
    vpn_remote_access: '#7c3aed',
    vpn_site_to_site: '#7c3aed',
    other: '#6b7280',
};

const MEDIA_COLOR = {
    copper: '#94a3b8',
    fiber: '#fb923c',
    wireless: '#3b82f6',
    virtual: '#a855f7',
};

// VPN pictogram (padlock) embedded as a data URL. The <g translate(0,-2)>
// shifts the content up so the padlock is geometrically centered inside
// the 24×24 viewBox; explicit width/height = 256 raise the raster
// resolution so Cytoscape's zoom doesn't show jagged scaling steps.
const VPN_NODE_ICON = 'data:image/svg+xml;utf8,'
    + encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="256" height="256" fill="none" '
        + 'stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
        + '<g transform="translate(0,-2)">'
        + '<rect x="6" y="11" width="12" height="9" rx="1.5"/>'
        + '<path d="M8.5 11V8a3.5 3.5 0 0 1 7 0v3"/>'
        + '<circle cx="12" cy="15.5" r="1.2"/>'
        + '<path d="M12 16.7v1.6"/>'
        + '</g>'
        + '</svg>'
    );

// Wi-Fi pictogram (Heroicons "wifi") embedded as a data URL. As above:
// content wrapped in a translate to vertically center inside the 24×24
// viewBox, plus a high explicit raster resolution.
const WIFI_NODE_ICON = 'data:image/svg+xml;utf8,'
    + encodeURIComponent(
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="256" height="256" fill="none" '
        + 'stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">'
        + '<g transform="translate(0,-1.8)">'
        + '<path d="M8.288 15.038a5.25 5.25 0 0 1 7.424 0"/>'
        + '<path d="M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0"/>'
        + '<path d="M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0"/>'
        + '<path d="M12.53 18.22l-.53.53-.53-.53a.75.75 0 0 1 1.06 0Z"/>'
        + '</g>'
        + '</svg>'
    );

export default function topologyGraph({ graph, layout, iconSize, restore }) {
    return {
        cy: null,
        nav: null,
        _layout: layout || 'cose-bilkent',
        _exportHandler: null,
        // Persistent cache of node positions across refreshes. When a node
        // is filtered out and later filtered back in, we restore it to its
        // last known coordinates instead of treating it as a new node that
        // needs to be placed near a neighbour. Without this, repeated
        // filter toggles drift child positions inside compound (rack)
        // parents, making the compound bboxes inflate over time.
        _positionsCache: {},
        // Global per-tenant icon size for the topology view. Bound to the
        // slider in the toolbar; on input we apply live, on change we persist.
        globalIconSize: iconSize || 44,
        // Mini-map (navigator) is hidden by default; toggled via the corner button.
        showNavigator: false,
        // Right-click context menu on equipment nodes. View transitions:
        // 'root' → 'ports' (list of interfaces) → 'port-detail' (checkboxes
        // for the selected interface). Coordinates are container-local so
        // the overlay can be placed with absolute positioning.
        contextMenu: { open: false, x: 0, y: 0, nodeId: null, nodeFullId: null, nodeName: '', nodeKind: null, nodeIsHiddenDb: false, nodeIsHiddenSession: false, view: 'root', interfaces: null, loading: false, currentInterfaceId: null },
        // Per-node position of the device name label relative to the icon.
        // Allowed values: 'top' | 'bottom' (default) | 'left' | 'right'.
        // Shape: { [equipmentId]: 'top'|'bottom'|'left'|'right' }
        nodeLabelPositions: {},
        // Equipment IDs hidden "Solo ora" — purely client-side, cleared on
        // page refresh, but persisted in topology snapshots.
        sessionHiddenNodeIds: [],
        // Per-interface display toggles. Persisted in localStorage so the
        // chosen labels survive refresh; also restorable from a snapshot.
        // Shape: { [interfaceId]: { ip: bool, mac: bool, vlan: bool, description: bool } }
        portSettings: {},
        // Per-VPN-remote-access node toggles for the optional detail rows
        // rendered under the node name (routing mode, client network CIDR).
        // Shape: { 'vpn-ra-<id>': { routing: bool, cidr: bool } }
        vpnNodeDetails: {},
        // Cache of interface payloads fetched lazily from the server.
        // Shape: { [equipmentId]: [ { id, name, ip_address, ... } ] }
        _interfacesCache: {},
        // Flattened cache by interface id for O(1) edge label refresh.
        _interfacesById: {},

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

            // Hydrate port-label preferences from localStorage BEFORE
            // applying restore so a snapshot's settings can override the
            // local copy.
            this._loadPortSettingsFromLocal();
            this._loadNodeLabelPositionsFromLocal();
            this._loadVpnNodeDetailsFromLocal();
            const restored = this._applyRestore(restore);
            this._applyNodeLabelPositions();
            this._applySessionHidden();
            this._applyVpnLabels();
            window._topologyVpnNodeDetails = this.vpnNodeDetails;
            // Reflect the persisted/local settings into every edge label
            // already in the cy instance.
            this._refreshEdgeLabels();
            this._publishPortSettings();
            // Some snapshots may carry portSettings for interfaces whose
            // details (ip/mac/vlan) aren't yet loaded client-side; pre-fetch
            // them so the labels show up immediately instead of on first
            // context-menu open.
            this._prefetchInterfacesForSettings();
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
            const refreshNoLayout = () => this._refresh({ skipLayout: true });
            // Scope filters: a big delta (e.g. site switch) can legitimately
            // trigger a fresh layout when there are no compound parents.
            ['siteId', 'roomFilter', 'statusFilter', 'vlanFilter', 'tagFilters', 'filterTypes']
                .forEach((k) => this.$watch('$wire.' + k, refresh));
            // Purely visual flags: must never reposition existing nodes,
            // regardless of dataset delta. Compounds appear/disappear around
            // their children, hidden devices fade in/out, patch-panel
            // passthrough flips — never a relayout.
            ['includeHidden', 'hidePatchPanels', 'hideWifi', 'hideVpn', 'groupByRack', 'groupBySite', 'groupByRoom', 'groupByHypervisor']
                .forEach((k) => this.$watch('$wire.' + k, refreshNoLayout));
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

        async _refresh({ skipLayout = false } = {}) {
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
                this._positionsCache[n.id()] = { x: p.x, y: p.y };
            });

            // 2) Swap elements.
            this.cy.elements().remove();
            this.cy.add(this._toElements(data));

            // 3) Restore positions for nodes that were already on the canvas;
            //    fall back to the persistent cache for nodes that were
            //    visible at some earlier point (e.g. filtered out then back
            //    in); only truly never-seen nodes go to the newNodes branch.
            const knownIds = new Set(Object.keys(prevPositions));
            const newNodes = [];
            this.cy.nodes().forEach((n) => {
                const id = n.id();
                const prev = prevPositions[id];
                if (prev) {
                    n.position(prev);
                } else if (this._positionsCache[id]) {
                    n.position(this._positionsCache[id]);
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

            // With compound parents on the canvas (group-by-rack/room/site),
            // re-running cose-bilkent on successive filter changes destabilises
            // inter-compound spacing — each fresh run pushes compounds slightly
            // further apart, accumulating into the "rack drift" reported by the
            // user. Skip the relayout in that case: cache restore + neighbour
            // placement of brand-new nodes give a stable result. The first run
            // (knownIds empty) still needs a real layout to place everything,
            // otherwise compound members end up stacked at the canvas centre.
            const firstRun = knownIds.size === 0;
            const shouldRelayout = firstRun
                || (!skipLayout && majorChange && !nowHasCompounds);
            if (shouldRelayout) {
                this._layoutRanOnce = false;
                this._runLayout(this._layout);
            } else {
                // Minor delta OR major-with-compounds: keep existing positions
                // verbatim and place each new node near a known neighbor;
                // fallback to the bbox center of known positions with jitter.
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

                // Re-fit ONLY when the filter trimmed the dataset hard and
                // there are no new nodes appearing. Refitting on every
                // additive change would re-zoom the viewport each click
                // (bbox grows ⇒ zoom drops), giving the impression that
                // rack compounds drift apart. Skip fit when adding nodes.
                const prevCount = knownIds.size;
                const removedCount = prevCount - (totalNodes - newNodes.length);
                const dropRatio = prevCount > 0 ? removedCount / prevCount : 0;
                if (newNodes.length === 0 && totalNodes > 0 && dropRatio >= 0.3) {
                    this.cy.fit(undefined, 40);
                }
            }

            this._applyIconSizes();
            // The graph swap blew away per-edge fromLabel/toLabel; reapply the
            // persisted port-label settings so the user's chosen IP/MAC/VLAN
            // overlays survive a filter/grouping change.
            this._refreshEdgeLabels();
            this._applyNodeLabelPositions();
            this._applySessionHidden();
            this._applyVpnLabels();
            this.cy.style().update();
        },

        /**
         * Apply restored node positions, zoom and pan from a saved snapshot.
         * Returns true if any positions were applied (so caller skips the
         * first auto-layout), false otherwise. Called once in init() — never
         * from _refresh(), because changing filters intentionally relayouts.
         */
        _applyRestore(restore) {
            if (!restore) return false;
            // Port label settings persist independently of node positions —
            // an old snapshot may carry only one of the two. Apply them
            // first; positions may still be skipped further down.
            if (restore.portSettings && typeof restore.portSettings === 'object') {
                this.portSettings = { ...restore.portSettings };
                this._savePortSettingsToLocal();
            }
            if (restore.nodeLabelPositions && typeof restore.nodeLabelPositions === 'object') {
                this.nodeLabelPositions = { ...restore.nodeLabelPositions };
                this._saveNodeLabelPositionsToLocal();
            }
            if (Array.isArray(restore.sessionHiddenIds)) {
                this.sessionHiddenNodeIds = restore.sessionHiddenIds.slice();
                this._publishSessionHidden();
            }
            if (restore.vpnNodeDetails && typeof restore.vpnNodeDetails === 'object') {
                this.vpnNodeDetails = { ...restore.vpnNodeDetails };
                this._saveVpnNodeDetailsToLocal();
            }
            if (!restore.nodePositions) return false;
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
                // Left-click anywhere closes the context menu (the user has
                // clearly moved on to another interaction).
                this.closeContextMenu();
            });

            this.cy.on('dbltap', 'node', (evt) => {
                const rackId = evt.target.data('rackId');
                if (rackId) window.location.href = `/racks/${rackId}`;
            });

            // Right-click on a node → context menu. We swallow the browser's
            // native menu via a one-shot contextmenu handler on the cy canvas
            // wrapper, since Cytoscape does not capture it for us.
            this.cy.on('cxttap', 'node', (evt) => {
                if (evt.target.isParent()) return;
                const rawId = String(evt.target.data('id') || '');
                let kind = String(evt.target.data('kind') || '');
                if (!kind) {
                    kind = rawId.startsWith('wifi-') ? 'wifi'
                        : rawId.startsWith('vpn-') ? 'vpn'
                        : 'equipment';
                }
                // Trailing numeric id — works for eq-N, wifi-N, vpn-ra-N,
                // vpn-stos-N alike.
                const m = rawId.match(/(\d+)$/);
                const numericId = m ? parseInt(m[1], 10) : NaN;
                if (Number.isNaN(numericId)) return;
                const pos = evt.renderedPosition || evt.position;
                const name = String(evt.target.data('name') || evt.target.data('label') || '');
                const hiddenDb = !!evt.target.data('hidden');
                const hiddenSession = kind === 'equipment' && this.sessionHiddenNodeIds.includes(numericId);
                const vpnKind = String(evt.target.data('vpnKind') || '');
                this.openContextMenu(numericId, name, pos.x, pos.y, hiddenDb, hiddenSession, kind, rawId, vpnKind);
            });
            // Native browser context menu suppression on the cy canvas.
            if (this.$refs && this.$refs.cy) {
                this.$refs.cy.addEventListener('contextmenu', (e) => e.preventDefault());
            }

            this.cy.on('tap', (evt) => {
                if (evt.target === this.cy) {
                    // Tap on empty canvas: drop selection AND restore full
                    // opacity on every node and edge.
                    this.cy.elements(':selected').unselect();
                    this._clearHighlight();
                    this.closeContextMenu();
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
            this.cy.on('layoutstop', () => {
                // Refresh the persistent positions cache after every layout
                // so a subsequent filter-then-refilter cycle restores nodes
                // to their post-layout coordinates rather than stale ones.
                this.cy.nodes().forEach((n) => {
                    if (n.isParent()) return;
                    const p = n.position();
                    this._positionsCache[n.id()] = { x: p.x, y: p.y };
                });
                scheduleRestyle();
            });
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

        // --- context menu + port labels ----------------------------------

        openContextMenu(eqId, name, x, y, hiddenDb = false, hiddenSession = false, kind = 'equipment', fullId = null, vpnKind = '') {
            this.contextMenu = {
                open: true,
                x,
                y,
                nodeId: eqId,
                nodeFullId: fullId || (kind === 'wifi' ? 'wifi-' : kind === 'vpn' ? 'vpn-ra-' : 'eq-') + eqId,
                nodeName: name,
                nodeKind: kind,
                nodeVpnKind: vpnKind,
                nodeIsHiddenDb: hiddenDb,
                nodeIsHiddenSession: hiddenSession,
                view: 'root',
                interfaces: this._interfacesCache[eqId] || null,
                loading: false,
                currentInterfaceId: null,
            };
        },

        openVpnDetailsView() {
            this.contextMenu = { ...this.contextMenu, view: 'vpn-details' };
        },

        isVpnDetailOn(attr) {
            const key = this.contextMenu.nodeFullId;
            if (!key) return false;
            const s = this.vpnNodeDetails[key];
            return !!(s && s[attr]);
        },

        toggleVpnDetail(attr) {
            const key = this.contextMenu.nodeFullId;
            if (!key) return;
            const prev = this.vpnNodeDetails[key] || {};
            const next = { ...prev, [attr]: !prev[attr] };
            const hasAny = ['routing', 'cidr', 'netA', 'netB'].some((k) => next[k]);
            const merged = { ...this.vpnNodeDetails };
            if (hasAny) merged[key] = next; else delete merged[key];
            this.vpnNodeDetails = merged;
            this._saveVpnNodeDetailsToLocal();
            window._topologyVpnNodeDetails = this.vpnNodeDetails;
            this._applyVpnLabels();
        },

        _applyVpnLabels() {
            if (!this.cy) return;
            this.cy.nodes('[kind = "vpn"]').forEach((n) => {
                const id = n.id();
                const name = String(n.data('name') || n.data('label') || '');
                const cfg = this.vpnNodeDetails[id] || {};
                const vpnKind = String(n.data('vpnKind') || '');
                const lines = [name];
                if (vpnKind === 'remote') {
                    if (cfg.routing) {
                        const mode = String(n.data('routingMode') || '').toLowerCase();
                        if (mode) lines.push(mode === 'bridged' ? 'Bridged' : 'Routed');
                    }
                    if (cfg.cidr) {
                        const cidr = n.data('networkCidr');
                        if (cidr) lines.push(String(cidr));
                    }
                } else if (vpnKind === 'site') {
                    if (cfg.netA) {
                        const nets = n.data('routedNetworksA');
                        if (Array.isArray(nets) && nets.length > 0) lines.push('A: ' + nets.join(', '));
                    }
                    if (cfg.netB) {
                        const nets = n.data('routedNetworksB');
                        if (Array.isArray(nets) && nets.length > 0) lines.push('B: ' + nets.join(', '));
                    }
                }
                n.data('label', lines.join('\n'));
            });
        },

        _saveVpnNodeDetailsToLocal() {
            try {
                window.localStorage.setItem(this._localStorageKey() + ':vpnNodeDetails', JSON.stringify(this.vpnNodeDetails));
            } catch (e) { /* ignore quota / private mode */ }
        },

        _loadVpnNodeDetailsFromLocal() {
            try {
                const raw = window.localStorage.getItem(this._localStorageKey() + ':vpnNodeDetails');
                if (!raw) return;
                const parsed = JSON.parse(raw);
                if (parsed && typeof parsed === 'object') this.vpnNodeDetails = parsed;
            } catch (e) { /* ignore */ }
        },

        closeContextMenu() {
            if (!this.contextMenu.open) return;
            this.contextMenu = { ...this.contextMenu, open: false };
        },

        async openPortsView() {
            const eqId = this.contextMenu.nodeId;
            if (!eqId) return;
            this.contextMenu = { ...this.contextMenu, view: 'ports' };
            if (this._interfacesCache[eqId]) {
                this.contextMenu = { ...this.contextMenu, interfaces: this._interfacesCache[eqId] };
                return;
            }
            this.contextMenu = { ...this.contextMenu, loading: true };
            try {
                const list = await this.$wire.fetchInterfaces(eqId);
                this._interfacesCache[eqId] = Array.isArray(list) ? list : [];
                this._interfacesCache[eqId].forEach((i) => {
                    this._interfacesById[i.id] = i;
                });
                this.contextMenu = { ...this.contextMenu, interfaces: this._interfacesCache[eqId], loading: false };
                // Once we know the interface payload, edges anchored to those
                // ports may have richer labels we haven't drawn yet (settings
                // could have been restored from a snapshot before the data
                // was loaded). Refresh now.
                this._refreshEdgeLabels();
            } catch (e) {
                this.contextMenu = { ...this.contextMenu, loading: false, interfaces: [] };
            }
        },

        openPortDetail(intId) {
            this.contextMenu = { ...this.contextMenu, view: 'port-detail', currentInterfaceId: intId };
        },

        backToPorts() {
            this.contextMenu = { ...this.contextMenu, view: 'ports', currentInterfaceId: null };
        },

        backToRoot() {
            this.contextMenu = { ...this.contextMenu, view: 'root', currentInterfaceId: null };
        },

        isPortAttrOn(intId, attr) {
            const s = this.portSettings[intId];
            return !!(s && s[attr]);
        },

        togglePortAttr(intId, attr) {
            const prev = this.portSettings[intId] || {};
            const next = { ...prev, [attr]: !prev[attr] };
            // Drop the entry entirely when no attribute is on, keeping the
            // localStorage payload small.
            const hasAny = ['ip', 'mac', 'vlan', 'description'].some((k) => next[k]);
            const merged = { ...this.portSettings };
            if (hasAny) merged[intId] = next; else delete merged[intId];
            this.portSettings = merged;
            this._savePortSettingsToLocal();
            this._publishPortSettings();
            this._refreshEdgeLabels(intId);
        },

        _publishPortSettings() {
            // Bridge for the snapshot save button (lives outside this Alpine
            // scope on the Blade — see graph.blade.php).
            window._topologyPortSettings = this.portSettings;
        },

        // --- node label position -----------------------------------------

        openNamePositionView() {
            this.contextMenu = { ...this.contextMenu, view: 'name-position' };
        },

        _contextMenuNodeKey() {
            // Prefer the full cy node id (already prefixed by the server).
            // Falls back to the legacy "eq-N" / "wifi-N" composition for
            // tests / programmatic callers that don't supply fullId.
            if (this.contextMenu.nodeFullId) return this.contextMenu.nodeFullId;
            const prefix = this.contextMenu.nodeKind === 'wifi' ? 'wifi-'
                : this.contextMenu.nodeKind === 'vpn' ? 'vpn-ra-'
                : 'eq-';
            return prefix + this.contextMenu.nodeId;
        },

        currentNodeLabelPosition() {
            return this.nodeLabelPositions[this._contextMenuNodeKey()] || 'bottom';
        },

        setNodeLabelPosition(pos) {
            if (!this.contextMenu.nodeId) return;
            const key = this._contextMenuNodeKey();
            const merged = { ...this.nodeLabelPositions };
            if (pos === 'bottom') {
                // 'bottom' is the default — keep the entry out of the persisted
                // payload so the map stays small and forward-compat.
                delete merged[key];
            } else {
                merged[key] = pos;
            }
            this.nodeLabelPositions = merged;
            this._saveNodeLabelPositionsToLocal();
            this._publishNodeLabelPositions();
            this._applyNodeLabelPositions();
        },

        _publishNodeLabelPositions() {
            window._topologyNodeLabelPositions = this.nodeLabelPositions;
        },

        _localStorageKeyForNodeLabels() {
            return 'trama:topology:node-label-positions';
        },

        _loadNodeLabelPositionsFromLocal() {
            try {
                const raw = window.localStorage.getItem(this._localStorageKeyForNodeLabels());
                if (!raw) return;
                const parsed = JSON.parse(raw);
                if (parsed && typeof parsed === 'object') {
                    this.nodeLabelPositions = parsed;
                }
            } catch (e) { /* ignore */ }
        },

        _saveNodeLabelPositionsToLocal() {
            try {
                window.localStorage.setItem(this._localStorageKeyForNodeLabels(), JSON.stringify(this.nodeLabelPositions));
            } catch (e) { /* ignore */ }
        },

        /**
         * Stamp the chosen position onto each node as a data attribute so
         * the Cytoscape style functions for text-valign/halign/margin can
         * read it. Called on init, on every refresh, and after each toggle.
         */
        _applyNodeLabelPositions() {
            if (!this.cy) return;
            this.cy.nodes().forEach((n) => {
                if (n.isParent()) return;
                const pos = this.nodeLabelPositions[n.id()] || 'bottom';
                n.data('labelPos', pos);
            });
            this.cy.style().update();
            this._publishNodeLabelPositions();
        },

        // --- hide / show (session-only + persistent) ---------------------

        openHideView() {
            this.contextMenu = { ...this.contextMenu, view: 'hide' };
        },

        hideNodeSessionOnly() {
            const eqId = this.contextMenu.nodeId;
            if (!eqId) return;
            if (!this.sessionHiddenNodeIds.includes(eqId)) {
                this.sessionHiddenNodeIds = [...this.sessionHiddenNodeIds, eqId];
            }
            this.closeContextMenu();
            this._applySessionHidden();
            this._publishSessionHidden();
        },

        async hideNodeAlways() {
            const eqId = this.contextMenu.nodeId;
            if (!eqId) return;
            this.closeContextMenu();
            try {
                await this.$wire.hideAlways(eqId);
                await this._refresh();
            } catch (e) { /* toast surfaces failure */ }
        },

        async showNodeAlways() {
            const eqId = this.contextMenu.nodeId;
            if (!eqId) return;
            this.closeContextMenu();
            try {
                await this.$wire.showAlways(eqId);
                await this._refresh();
            } catch (e) { /* toast surfaces failure */ }
        },

        /**
         * Walk every node, tag those in sessionHiddenNodeIds (plus their
         * connected edges) with the `session-hidden` class so they get
         * display:none from the stylesheet. Idempotent: removes the class
         * everywhere first, then re-applies.
         *
         * Special case: when the user enables the "Includi nascosti" filter
         * we make the session-hidden devices appear too (faded), so they
         * can be inspected and the "Solo ora" hide undone from their
         * context menu. Otherwise a user could not bring back a Solo-ora
         * hidden device without a full page refresh.
         */
        _applySessionHidden() {
            if (!this.cy) return;
            this.cy.elements().removeClass('session-hidden').removeClass('session-hidden-shown');
            if (this.sessionHiddenNodeIds.length === 0) return;
            const includeHidden = !!(this.$wire && this.$wire.includeHidden);
            const idSet = new Set(this.sessionHiddenNodeIds.map((id) => 'eq-' + id));
            this.cy.nodes().forEach((n) => {
                if (!idSet.has(n.id())) return;
                if (includeHidden) {
                    // Reveal but fade so the user can tell which nodes are
                    // currently "Solo ora" hidden.
                    n.addClass('session-hidden-shown');
                    n.connectedEdges().addClass('session-hidden-shown');
                } else {
                    n.addClass('session-hidden');
                    n.connectedEdges().addClass('session-hidden');
                }
            });
        },

        unhideNodeSessionOnly() {
            const eqId = this.contextMenu.nodeId;
            if (!eqId) return;
            this.sessionHiddenNodeIds = this.sessionHiddenNodeIds.filter((id) => id !== eqId);
            this.closeContextMenu();
            this._applySessionHidden();
            this._publishSessionHidden();
        },

        _publishSessionHidden() {
            window._topologySessionHiddenIds = this.sessionHiddenNodeIds.slice();
        },

        async _prefetchInterfacesForSettings() {
            const want = new Set(Object.keys(this.portSettings).map(Number).filter(Number.isFinite));
            if (want.size === 0) return;
            const eqIds = new Set();
            this.cy.edges().forEach((edge) => {
                const fromId = parseInt(edge.data('fromIfaceId') || 0, 10) || null;
                const toId = parseInt(edge.data('toIfaceId') || 0, 10) || null;
                if (fromId && want.has(fromId)) {
                    const srcEqId = parseInt(String(edge.data('source')).replace('eq-', ''), 10);
                    if (Number.isFinite(srcEqId)) eqIds.add(srcEqId);
                }
                if (toId && want.has(toId)) {
                    const tgtEqId = parseInt(String(edge.data('target')).replace('eq-', ''), 10);
                    if (Number.isFinite(tgtEqId)) eqIds.add(tgtEqId);
                }
            });
            for (const eqId of eqIds) {
                if (this._interfacesCache[eqId]) continue;
                try {
                    const list = await this.$wire.fetchInterfaces(eqId);
                    this._interfacesCache[eqId] = Array.isArray(list) ? list : [];
                    this._interfacesCache[eqId].forEach((i) => { this._interfacesById[i.id] = i; });
                } catch (e) { /* ignore single-node failures */ }
            }
            this._refreshEdgeLabels();
        },

        _localStorageKey() {
            // Coarse, per-browser key. Topology snapshot persistence handles
            // the cross-device case explicitly.
            return 'trama:topology:port-labels';
        },

        _loadPortSettingsFromLocal() {
            try {
                const raw = window.localStorage.getItem(this._localStorageKey());
                if (!raw) return;
                const parsed = JSON.parse(raw);
                if (parsed && typeof parsed === 'object') {
                    this.portSettings = parsed;
                }
            } catch (e) { /* ignore quota / disabled storage */ }
        },

        _savePortSettingsToLocal() {
            try {
                window.localStorage.setItem(this._localStorageKey(), JSON.stringify(this.portSettings));
            } catch (e) { /* ignore quota / disabled storage */ }
        },

        /**
         * Recompute `fromLabel` / `toLabel` on edges. When intId is given we
         * touch only edges anchored to that interface; otherwise we sweep
         * everything (used at init and on snapshot restore).
         */
        _refreshEdgeLabels(intId) {
            if (!this.cy) return;
            const intIdNum = typeof intId === 'number' ? intId : null;
            this.cy.edges().forEach((edge) => {
                const fromId = parseInt(edge.data('fromIfaceId') || 0, 10) || null;
                const toId = parseInt(edge.data('toIfaceId') || 0, 10) || null;
                if (intIdNum !== null && fromId !== intIdNum && toId !== intIdNum) return;
                if (fromId) {
                    edge.data('fromLabel', this._composePortLabel(fromId, edge.data('fromIface')));
                }
                if (toId) {
                    edge.data('toLabel', this._composePortLabel(toId, edge.data('toIface')));
                }
            });
            // Force a style re-evaluation so the new labels and offsets
            // appear immediately.
            this.cy.style().update();
        },

        /**
         * Build the multi-line label for one port. The first line is always
         * the port name; configured attributes follow, one per line.
         */
        _composePortLabel(intId, fallbackName) {
            const s = this.portSettings[intId];
            const iface = this._interfacesById[intId] || null;
            const name = (iface && iface.name) || fallbackName || '';
            if (!s || !iface) return name;
            const lines = [name];
            if (s.ip) lines.push(iface.ip_address ? String(iface.ip_address) : '—');
            if (s.mac) lines.push(iface.mac_address ? String(iface.mac_address) : '—');
            if (s.vlan) lines.push(this._formatVlan(iface));
            if (s.description) lines.push(iface.description ? String(iface.description) : '');
            return lines.filter((l) => l !== '').join('\n');
        },

        /**
         * Format: "VLAN {mode} {default} ({allowed_list})".
         * - "VLAN" is always the leading word.
         * - {default} is omitted when null.
         * - The parenthesised list is omitted when there are no allowed VLANs.
         */
        _formatVlan(iface) {
            const mode = iface.vlan_mode || 'none';
            const def = iface.vlan_default;
            const allowed = Array.isArray(iface.vlans_allowed) ? iface.vlans_allowed : [];
            const head = ['VLAN', mode];
            if (def != null) head.push(String(def));
            let s = head.join(' ');
            if (allowed.length) s += ` (${this._compressVlans(allowed)})`;
            return s;
        },

        _compressVlans(list) {
            const sorted = [...new Set(list.map(Number).filter((n) => Number.isFinite(n)))].sort((a, b) => a - b);
            if (sorted.length === 0) return '';
            const ranges = [];
            let start = sorted[0];
            let prev = start;
            for (let i = 1; i < sorted.length; i++) {
                if (sorted[i] === prev + 1) { prev = sorted[i]; continue; }
                ranges.push(start === prev ? String(start) : `${start}-${prev}`);
                start = sorted[i];
                prev = start;
            }
            ranges.push(start === prev ? String(start) : `${start}-${prev}`);
            return ranges.join(',');
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
                        // Position of the device name relative to the icon.
                        // Per-node override via data('labelPos'); default is
                        // 'bottom' so existing layouts don't move. Mapping
                        // covers the four cardinal positions selectable from
                        // the context menu.
                        'text-valign': (ele) => {
                            const p = ele.data('labelPos') || 'bottom';
                            return p === 'top' ? 'top' : p === 'bottom' ? 'bottom' : 'center';
                        },
                        'text-halign': (ele) => {
                            const p = ele.data('labelPos') || 'bottom';
                            return p === 'left' ? 'left' : p === 'right' ? 'right' : 'center';
                        },
                        'text-margin-y': (ele) => {
                            const p = ele.data('labelPos') || 'bottom';
                            const m = Math.max(2, (ele.data('iconSize') || 44) * 0.12);
                            return p === 'top' ? -m : p === 'bottom' ? m : 0;
                        },
                        'text-margin-x': (ele) => {
                            const p = ele.data('labelPos') || 'bottom';
                            const m = Math.max(2, (ele.data('iconSize') || 44) * 0.12);
                            return p === 'left' ? -m : p === 'right' ? m : 0;
                        },
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
                        'line-style': (ele) => (ele.data('cableType') === 'vpn' || ele.data('media') === 'wireless' ? 'dashed' : 'solid'),
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
                    // Source-side port info: defaults to the port name alone
                    // (set by the server as data.fromIface). When the user
                    // toggles extra attributes via the context menu, the
                    // factory writes a multi-line string into data.fromLabel;
                    // the style function below prefers it when present.
                    selector: 'edge[fromIface]',
                    style: {
                        'source-label': (ele) => ele.data('fromLabel') || ele.data('fromIface') || '',
                        'text-wrap': 'wrap',
                        'source-text-offset': (ele) => {
                            const size = ele.source().data('iconSize') || 44;
                            const fontPx = Math.max(6, size * 0.20);
                            const text = ele.data('fromLabel') || ele.data('fromIface') || '';
                            // For multi-line labels the offset has to clear
                            // the WIDEST line; otherwise a long IP would
                            // overlap the icon while the short port name
                            // would look fine.
                            const chars = text.split('\n').reduce((m, l) => Math.max(m, l.length), 0);
                            const halfLabel = chars * fontPx * 0.30;
                            return size / 2 + halfLabel + 3;
                        },
                        'source-text-rotation': 'autorotate',
                        // Lift the whole port label block above the cable
                        // line so it doesn't sit on top of the cable label
                        // (the center label uses autorotate too and would
                        // overlap otherwise). Negative y = above in the
                        // rotated local frame, regardless of cable angle.
                        'source-text-margin-y': (ele) => {
                            const size = ele.source().data('iconSize') || 44;
                            const fontPx = Math.max(6, size * 0.20);
                            const text = ele.data('fromLabel') || ele.data('fromIface') || '';
                            const lines = text.split('\n').length;
                            return -(fontPx * lines * 0.6 + 3);
                        },
                    },
                },
                {
                    selector: 'edge[toIface]',
                    style: {
                        'target-label': (ele) => ele.data('toLabel') || ele.data('toIface') || '',
                        'text-wrap': 'wrap',
                        'target-text-offset': (ele) => {
                            const size = ele.target().data('iconSize') || 44;
                            const fontPx = Math.max(6, size * 0.20);
                            const text = ele.data('toLabel') || ele.data('toIface') || '';
                            const chars = text.split('\n').reduce((m, l) => Math.max(m, l.length), 0);
                            const halfLabel = chars * fontPx * 0.30;
                            return size / 2 + halfLabel + 3;
                        },
                        'target-text-rotation': 'autorotate',
                        'target-text-margin-y': (ele) => {
                            const size = ele.target().data('iconSize') || 44;
                            const fontPx = Math.max(6, size * 0.20);
                            const text = ele.data('toLabel') || ele.data('toIface') || '';
                            const lines = text.split('\n').length;
                            return -(fontPx * lines * 0.6 + 3);
                        },
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
                    // Synthetic Wi-Fi SSID node: cyan disc with the white
                    // Wi-Fi pictogram embedded. Wins over node[icon] when no
                    // custom kind icon was uploaded — and keeps a recognizable
                    // shape regardless of tenant assets.
                    selector: 'node[kind = "wifi"]',
                    style: {
                        'background-image': WIFI_NODE_ICON,
                        'background-fit': 'contain',
                        'background-clip': 'none',
                        'background-image-smoothing': 'yes',
                        'background-color': '#0ea5e9',
                        'background-opacity': 1,
                        'border-color': '#0369a1',
                    },
                },
                {
                    // Synthetic VPN node (remote-access OR site-to-site):
                    // violet disc with a white padlock pictogram. Both VPN
                    // node types share the same shape — the topology label
                    // and the routedVlans data tell them apart.
                    selector: 'node[kind = "vpn"]',
                    style: {
                        'background-image': VPN_NODE_ICON,
                        'background-fit': 'contain',
                        'background-clip': 'none',
                        'background-image-smoothing': 'yes',
                        'background-color': '#7c3aed',
                        'background-opacity': 1,
                        'border-color': '#4c1d95',
                        // Multi-line label (name + optional routing mode +
                        // optional CIDR) needs explicit wrap so embedded \n
                        // chars actually break the text.
                        'text-wrap': 'wrap',
                        'text-justification': 'center',
                    },
                },
                {
                    // vNIC backing edge: thin dashed violet line, no arrows.
                    // Encodes "this VM's vNIC is carried by this hypervisor's
                    // physical NIC" — many vNICs may map to the same pNIC, so
                    // we expect multiple parallel edges between the same pair.
                    selector: 'edge[kind = "vnic"]',
                    style: {
                        'line-color': '#7c3aed',
                        'line-style': 'dashed',
                        'width': 1.2,
                        'target-arrow-shape': 'none',
                        'source-arrow-shape': 'none',
                        'curve-style': 'bezier',
                        'font-size': 8,
                        'color': '#5b21b6',
                        'text-background-color': '#ffffff',
                        'text-background-opacity': 0.85,
                        'text-background-padding': 2,
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
                    // Client-side "Solo ora" hide: drops the element from the
                    // canvas without touching the DB flag. Reapplied on every
                    // _refresh() (see graph data swap).
                    selector: '.session-hidden',
                    style: { 'display': 'none' },
                },
                {
                    // "Solo ora" hidden but revealed because the
                    // "Includi nascosti" filter is on — show it faded so it
                    // is recognisable as a Solo-ora hide.
                    selector: '.session-hidden-shown',
                    style: { 'opacity': 0.35 },
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
                    // Hypervisor host compound — dashed cyan border, light cyan fill.
                    // Holds the hypervisor node together with its VMs so the
                    // host/guest relationship is visually obvious.
                    selector: 'node:parent[kind = "host"]',
                    style: {
                        'background-color': '#ecfeff',
                        'background-opacity': 0.55,
                        'border-color': '#0891b2',
                        'border-width': 2,
                        'border-style': 'dashed',
                        'shape': 'round-rectangle',
                        'padding': 10,
                        'text-valign': 'top',
                        'text-halign': 'center',
                        'font-size': 10,
                        'font-weight': 'bold',
                        'color': '#155e75',
                        'text-margin-y': -4,
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
                        'text-wrap': 'wrap',
                        'text-justification': 'center',
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
