/**
 * Alpine factory for the room-map drag & drop + icon resize.
 *
 * Coordinates note: `data-x`/`data-y` on each `<g.room-map-node>` are the
 * rack's **center** in meters. The `<g transform="translate(cx, cy)">`
 * positions that center; inner shapes are drawn centered on (0, 0) so they
 * grow symmetrically around the center when resized.
 *
 * Size note: `data-icon-size-px` is stored in **SVG units** of the room's
 * viewBox (e.g. 40 SVG units = 40/SCALE meters = 0.8 m at SCALE=50). The
 * icon is rendered with `width`/`height` directly in those units, so it
 * scales with the planimetria (bigger room canvas → bigger icon). The
 * name kept "*_px" for backward compatibility with the column/property,
 * but no per-screen-pixel computation happens anymore.
 *
 * On the wrapper `<div x-data="roomMapDnD" x-init="init($el)">` it:
 *  - on `pointerdown` over `<g.room-map-node>` either drags (moves the
 *    center), resizes (handle), or resets (small × button when override
 *    is active).
 *  - on `pointerup` persists move via `$wire.moveRack(id, x, y)` (center
 *    in meters) and resize via `$wire.resizeRackIcon(id, sizeSvgUnits)`.
 */
export default function roomMapDnD() {
    return {
        // Drag (move) state
        dragging: false,
        _mode: null, // 'move' | 'resize' | null
        _svg: null,
        _node: null,
        _id: null,
        _kind: null,
        _href: null,
        _scale: 50,
        _roomW: 12,
        _roomH: 8,
        _minIconPx: 16,
        _maxIconPx: 200,
        _canEdit: false,
        _offsetX: 0,
        _offsetY: 0,
        _startClientX: 0,
        _startClientY: 0,
        _moved: false,
        _DRAG_THRESHOLD: 4,
        // Resize state (in SVG units)
        _centerSvgX: 0,
        _centerSvgY: 0,
        _resizePx: 40,

        init(root) {
            this._svg = root.querySelector('svg.room-map');
            if (!this._svg) return;

            this._scale = parseFloat(this._svg.dataset.scale || '50');
            this._roomW = parseFloat(this._svg.dataset.roomWM || '12');
            this._roomH = parseFloat(this._svg.dataset.roomHM || '8');
            this._minIconPx = parseInt(this._svg.dataset.minIconPx || '16', 10);
            this._maxIconPx = parseInt(this._svg.dataset.maxIconPx || '200', 10);
            this._canEdit = this._svg.dataset.canEdit === '1';

            this._onDown = (e) => this.onDown(e);
            this._onMove = (e) => this.onMove(e);
            this._onUp = (e) => this.onUp(e);

            this._svg.addEventListener('pointerdown', this._onDown);
            window.addEventListener('pointermove', this._onMove);
            window.addEventListener('pointerup', this._onUp);

            // Apply sizes once. Icons live in SVG units now and scale with
            // the canvas automatically; no need to recompute on resize.
            this._applyAllIconSizes();

            // Room-level slider: update default size on every rack WITHOUT
            // a per-record override.
            root.addEventListener('room-default-size', (e) => {
                const size = parseInt(e.detail && e.detail.size, 10);
                if (!size) return;
                this._svg.querySelectorAll('g.room-map-node').forEach((g) => {
                    if (g.dataset.iconOverride === '1') return;
                    g.dataset.iconSizePx = String(size);
                    this._applyIconSize(g);
                });
            });

            // "Reset tutti": drop every per-rack override locally.
            root.addEventListener('room-reset-all', () => {
                const defaultPx = parseInt(this._svg.dataset.roomIconSizePx || '40', 10);
                this._svg.querySelectorAll('g.room-map-node').forEach((g) => {
                    g.dataset.iconOverride = '0';
                    g.dataset.iconSizePx = String(defaultPx);
                    const reset = g.querySelector('.rack-icon-reset');
                    if (reset) reset.style.display = 'none';
                    this._applyIconSize(g);
                });
            });
        },

        // ---- icon sizing -----------------------------------------------

        _applyAllIconSizes() {
            this._svg.querySelectorAll('g.room-map-node').forEach((g) => this._applyIconSize(g));
        },

        _applyIconSize(g) {
            const u = parseInt(g.dataset.iconSizePx || '40', 10); // SVG units
            const half = u / 2;

            const icon = g.querySelector('.rack-icon');
            if (icon) {
                icon.setAttribute('x', -half);
                icon.setAttribute('y', -half);
                icon.setAttribute('width', u);
                icon.setAttribute('height', u);
            }
            const label = g.querySelector('.rack-label');
            if (label) {
                // Font + gap proportional to the icon, all in SVG units.
                const fontU = u * 0.22;
                const gapU = u * 0.10;
                label.setAttribute('x', 0);
                label.setAttribute('y', half + gapU + fontU);
                label.setAttribute('font-size', fontU);
                label.setAttribute('stroke-width', fontU * 0.25);
            }
            // Handle and reset stay at a fixed size in SVG units (their
            // visual size scales with the planimetria too).
            const handleSize = Math.max(4, u * 0.20);
            const handle = g.querySelector('.rack-icon-resize');
            if (handle) {
                handle.setAttribute('x', half - handleSize / 2);
                handle.setAttribute('y', half - handleSize / 2);
                handle.setAttribute('width', handleSize);
                handle.setAttribute('height', handleSize);
            }
            const reset = g.querySelector('.rack-icon-reset');
            if (reset) {
                const r = Math.max(3, u * 0.12);
                const circle = reset.querySelector('circle');
                const text = reset.querySelector('text');
                if (circle) {
                    circle.setAttribute('cx', half - r * 0.4);
                    circle.setAttribute('cy', -half + r * 0.4);
                    circle.setAttribute('r', r);
                }
                if (text) {
                    text.setAttribute('x', half - r * 0.4);
                    text.setAttribute('y', -half + r * 0.4 + r * 0.35);
                    text.setAttribute('font-size', r * 1.4);
                }
            }
        },

        _halfUnits(g) {
            const u = parseInt(g.dataset.iconSizePx || '40', 10);
            return u / 2;
        },

        // ---- pointer interactions --------------------------------------

        onDown(e) {
            const g = e.target.closest('g.room-map-node');
            if (!g) return;

            // Reset-to-room-default button: short-circuit drag/resize.
            const onReset = !!e.target.closest('.rack-icon-reset');
            if (onReset && this._canEdit) {
                e.preventDefault();
                e.stopPropagation();
                const id = parseInt(g.dataset.nodeId || g.dataset.rackId, 10);
                const kind = g.dataset.kind || 'rack';
                const defaultPx = parseInt(this._svg.dataset.roomIconSizePx || '40', 10);
                g.dataset.iconOverride = '0';
                g.dataset.iconSizePx = String(defaultPx);
                const reset = g.querySelector('.rack-icon-reset');
                if (reset) reset.style.display = 'none';
                this._applyIconSize(g);
                if (kind === 'equipment') {
                    this.$wire.resetEquipmentIcon(id);
                } else {
                    this.$wire.resetRackIcon(id);
                }
                return;
            }

            const onHandle = !!e.target.closest('.rack-icon-resize');

            e.preventDefault();
            this._node = g;
            this._id = parseInt(g.dataset.nodeId || g.dataset.rackId, 10);
            this._kind = g.dataset.kind || 'rack';
            this._href = g.dataset.href || null;
            this._startClientX = e.clientX;
            this._startClientY = e.clientY;
            this._moved = false;
            this.dragging = true;

            if (onHandle && this._canEdit) {
                this._mode = 'resize';
                // Resize gesture operates in SVG units. Center of the
                // rack is data-x/data-y meters * scale.
                this._centerSvgX = parseFloat(g.dataset.x) * this._scale;
                this._centerSvgY = parseFloat(g.dataset.y) * this._scale;
                this._resizePx = parseInt(g.dataset.iconSizePx || '40', 10);
                return;
            }

            this._mode = 'move';
            if (this._canEdit) {
                const pt = this._toSvg(e);
                const curX = parseFloat(g.dataset.x); // CENTER in meters
                const curY = parseFloat(g.dataset.y);
                this._offsetX = pt.x - curX * this._scale;
                this._offsetY = pt.y - curY * this._scale;
                g.style.cursor = 'grabbing';
            }
        },

        onMove(e) {
            if (!this.dragging) return;

            if (!this._moved) {
                const dx = e.clientX - this._startClientX;
                const dy = e.clientY - this._startClientY;
                if (Math.hypot(dx, dy) < this._DRAG_THRESHOLD) return;
                this._moved = true;
            }

            if (this._mode === 'resize') {
                if (!this._canEdit || !this._node) return;
                // New size in SVG units = 2× the largest axis-distance
                // (in SVG units) from the center to the pointer.
                const pt = this._toSvg(e);
                const dxSvg = Math.abs(pt.x - this._centerSvgX);
                const dySvg = Math.abs(pt.y - this._centerSvgY);
                let newU = Math.round(2 * Math.max(dxSvg, dySvg));
                newU = Math.max(this._minIconPx, Math.min(this._maxIconPx, newU));
                this._resizePx = newU;
                this._node.dataset.iconSizePx = String(newU);
                this._applyIconSize(this._node);
                return;
            }

            // move mode
            if (!this._canEdit || !this._node) return;

            const pt = this._toSvg(e);
            let xPx = pt.x - this._offsetX;
            let yPx = pt.y - this._offsetY;

            // Clamp the CENTER inside [halfU, roomSize - halfU].
            const halfU = this._halfUnits(this._node);
            const maxPxX = this._roomW * this._scale - halfU;
            const maxPxY = this._roomH * this._scale - halfU;
            xPx = Math.max(halfU, Math.min(maxPxX, xPx));
            yPx = Math.max(halfU, Math.min(maxPxY, yPx));

            this._node.setAttribute('transform', `translate(${xPx},${yPx})`);
            this._node.dataset.x = (xPx / this._scale).toFixed(2);
            this._node.dataset.y = (yPx / this._scale).toFixed(2);
        },

        onUp(e) {
            if (!this.dragging) return;

            const node = this._node;
            const moved = this._moved;
            const id = this._id;
            const kind = this._kind || 'rack';
            const href = this._href;
            const mode = this._mode;
            const resizePx = this._resizePx;

            this.dragging = false;
            this._node = null;
            this._id = null;
            this._kind = null;
            this._href = null;
            this._moved = false;
            this._mode = null;

            if (node && mode === 'move') {
                node.style.cursor = this._canEdit ? 'grab' : 'pointer';
            }

            if (mode === 'resize' && moved && this._canEdit && node) {
                // Mark the node as having a per-record override so the
                // room-level default slider stops affecting it; also reveal
                // the reset-to-default button.
                node.dataset.iconOverride = '1';
                const reset = node.querySelector('.rack-icon-reset');
                if (reset) reset.style.display = 'inline';
                if (kind === 'equipment') {
                    this.$wire.resizeEquipmentIcon(id, resizePx);
                } else {
                    this.$wire.resizeRackIcon(id, resizePx);
                }
                return;
            }

            if (mode === 'move' && moved && this._canEdit && node) {
                const x = parseFloat(node.dataset.x);
                const y = parseFloat(node.dataset.y);
                if (kind === 'equipment') {
                    this.$wire.moveEquipment(id, x, y);
                } else {
                    this.$wire.moveRack(id, x, y);
                }
                return;
            }

            // Click (no drag) → open the rack page.
            if (!moved && href) {
                window.location.href = href;
            }
        },

        _toSvg(e) {
            const pt = this._svg.createSVGPoint();
            pt.x = e.clientX;
            pt.y = e.clientY;
            return pt.matrixTransform(this._svg.getScreenCTM().inverse());
        },
    };
}
