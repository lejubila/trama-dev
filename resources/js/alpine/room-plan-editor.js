/**
 * Alpine factory for the floor-plan editor (hand-rolled SVG).
 *
 * Coordinate system: the SVG viewBox is sized to room (width_m × depth_m) ×
 * SCALE units/m. All shape coordinates we persist are in METERS so they share
 * the reference frame of rack.position_x/y and equipment.position_x/y; on
 * screen we multiply by `scale` for layout, and divide back on input.
 *
 * Tools (mode):
 *   - select   : pick / drag walls (move whole polyline), doors, windows,
 *                labels. Click empty space to clear.
 *   - wall     : click to lay down vertices of a polyline; double-click or
 *                Enter to commit; Esc to cancel.
 *   - door     : click on a wall segment to anchor a door at that param t.
 *   - window   : ditto for a window.
 *   - label    : click on empty space, type text in a prompt-like input.
 *   - erase    : click on a shape to delete it.
 *
 * Snap: 0.1 m grid is always on; new wall vertices also snap to the nearest
 *       existing wall vertex within 0.15 m.
 *
 * History: a single shape-array snapshot is pushed on every mutation; Cmd/Ctrl+Z
 *          undoes, Cmd/Ctrl+Y or Shift+Cmd+Z redoes.
 */

const SNAP_M = 0.1;
const VERTEX_SNAP_M = 0.15;
const DEFAULT_THICKNESS_M = 0.15;
const DEFAULT_DOOR_M = 0.9;
const DEFAULT_WINDOW_M = 1.2;
const HISTORY_LIMIT = 50;

function uid(prefix) {
    return prefix + '-' + Math.random().toString(36).slice(2, 10);
}

function clamp(v, lo, hi) {
    return Math.max(lo, Math.min(hi, v));
}

function dist(ax, ay, bx, by) {
    return Math.hypot(ax - bx, ay - by);
}

function snap(v) {
    return Math.round(v / SNAP_M) * SNAP_M;
}

// Segment of wall `w` between indices i and i+1.
function wallSegment(w, i) {
    return [w.points[i], w.points[i + 1]];
}

// Linear length of a polyline wall (meters).
function wallLength(w) {
    let total = 0;
    for (let i = 0; i < w.points.length - 1; i++) {
        const [a, b] = wallSegment(w, i);
        total += dist(a[0], a[1], b[0], b[1]);
    }
    return total;
}

// Resolve t∈[0,1] of a polyline wall into a (segIndex, localT, point) tuple.
function wallParam(w, t) {
    const total = wallLength(w);
    if (total === 0) return { seg: 0, lt: 0, pt: w.points[0] };
    let target = clamp(t, 0, 1) * total;
    for (let i = 0; i < w.points.length - 1; i++) {
        const [a, b] = wallSegment(w, i);
        const segLen = dist(a[0], a[1], b[0], b[1]);
        if (target <= segLen || i === w.points.length - 2) {
            const lt = segLen === 0 ? 0 : target / segLen;
            return {
                seg: i,
                lt,
                pt: [a[0] + (b[0] - a[0]) * lt, a[1] + (b[1] - a[1]) * lt],
            };
        }
        target -= segLen;
    }
    return { seg: 0, lt: 0, pt: w.points[0] };
}

// Inverse: given a point on a wall, return the closest (t, distance).
function closestOnWall(w, x, y) {
    let best = { t: 0, d: Infinity, seg: 0 };
    let lenAccum = 0;
    const total = wallLength(w);
    for (let i = 0; i < w.points.length - 1; i++) {
        const [a, b] = wallSegment(w, i);
        const segLen = dist(a[0], a[1], b[0], b[1]);
        if (segLen === 0) continue;
        const dx = b[0] - a[0];
        const dy = b[1] - a[1];
        const u = clamp(((x - a[0]) * dx + (y - a[1]) * dy) / (segLen * segLen), 0, 1);
        const px = a[0] + dx * u;
        const py = a[1] + dy * u;
        const d = dist(x, y, px, py);
        if (d < best.d) {
            const t = total === 0 ? 0 : (lenAccum + u * segLen) / total;
            best = { t, d, seg: i };
        }
        lenAccum += segLen;
    }
    return best;
}

export default function roomPlanEditor() {
    return {
        // --- public reactive state -------------------------------------------
        mode: 'select',
        snapAxis: false, // toggle for orthogonal snap (0/45/90°)
        wallThickness: DEFAULT_THICKNESS_M,
        doorWidth: DEFAULT_DOOR_M,
        windowWidth: DEFAULT_WINDOW_M,
        selectedId: null,
        selectedKind: null,
        labelText: '',
        showDevices: true, // toggle for the rack/equipment overlay markers
        // --- internal --------------------------------------------------------
        _svg: null,
        _scale: 50,
        _roomW: 12,
        _roomH: 8,
        _drawing: { version: 1, walls: [], doors: [], windows: [], labels: [] },
        _draftWall: null,   // { points: [...] } while drawing a polyline
        _hoverPt: null,     // [x,y] preview vertex
        _drag: null,        // { kind, id, ... } while moving a shape
        _lastClickAt: 0,    // for manual double-click detection in wall mode
        _lastClickPt: null,
        _undo: [],
        _redo: [],

        init(root) {
            this._svg = root.querySelector('svg.plan-editor');
            if (!this._svg) return;

            this._scale = parseFloat(this._svg.dataset.scale || '50');
            this._roomW = parseFloat(this._svg.dataset.roomWM || '12');
            this._roomH = parseFloat(this._svg.dataset.roomHM || '8');

            const initial = JSON.parse(this._svg.dataset.initial || 'null');
            if (initial) {
                this._drawing = this._normalize(initial);
            }

            this._renderAll();

            this._svg.addEventListener('pointerdown', (e) => this.onDown(e));
            this._svg.addEventListener('pointermove', (e) => this.onMove(e));
            window.addEventListener('pointerup', (e) => this.onUp(e));
            this._svg.addEventListener('dblclick', (e) => this.onDoubleClick(e));
            window.addEventListener('keydown', (e) => this.onKey(e));
        },

        // --- helpers ---------------------------------------------------------

        _push() {
            this._undo.push(JSON.stringify(this._drawing));
            if (this._undo.length > HISTORY_LIMIT) this._undo.shift();
            this._redo = [];
        },

        _normalize(payload) {
            const walls = (payload.walls || [])
                .filter((w) => Array.isArray(w.points) && w.points.length >= 2)
                .map((w) => ({
                    id: w.id || uid('w'),
                    points: w.points.map((p) => [Number(p[0]) || 0, Number(p[1]) || 0]),
                    thickness_m: Number(w.thickness_m) || DEFAULT_THICKNESS_M,
                }));
            const wallIds = new Set(walls.map((w) => w.id));
            const anchored = (a) => a && a.wall_id && wallIds.has(a.wall_id);
            return {
                version: 1,
                walls,
                doors: (payload.doors || [])
                    .filter(anchored)
                    .map((d) => ({
                        id: d.id || uid('d'),
                        wall_id: d.wall_id,
                        t: clamp(Number(d.t) || 0, 0, 1),
                        width_m: Number(d.width_m) || DEFAULT_DOOR_M,
                        swing: ['left_in', 'left_out', 'right_in', 'right_out'].includes(d.swing) ? d.swing : 'left_in',
                    })),
                windows: (payload.windows || [])
                    .filter(anchored)
                    .map((w) => ({
                        id: w.id || uid('win'),
                        wall_id: w.wall_id,
                        t: clamp(Number(w.t) || 0, 0, 1),
                        width_m: Number(w.width_m) || DEFAULT_WINDOW_M,
                    })),
                labels: (payload.labels || [])
                    .filter((l) => l && Array.isArray(l.pos) && l.pos.length === 2 && typeof l.text === 'string')
                    .map((l) => ({
                        id: l.id || uid('l'),
                        pos: [Number(l.pos[0]) || 0, Number(l.pos[1]) || 0],
                        text: String(l.text).slice(0, 120),
                    })),
            };
        },

        _toMeters(e) {
            const pt = this._svg.createSVGPoint();
            pt.x = e.clientX;
            pt.y = e.clientY;
            const sp = pt.matrixTransform(this._svg.getScreenCTM().inverse());
            return [sp.x / this._scale, sp.y / this._scale];
        },

        _snapVertex(x, y, ignoreDraftLast = false) {
            // Snap to existing wall vertices first.
            let best = null;
            for (const w of this._drawing.walls) {
                for (const [vx, vy] of w.points) {
                    const d = dist(x, y, vx, vy);
                    if (d < VERTEX_SNAP_M && (best === null || d < best.d)) {
                        best = { x: vx, y: vy, d };
                    }
                }
            }
            if (this._draftWall && !ignoreDraftLast) {
                for (const [vx, vy] of this._draftWall.points) {
                    const d = dist(x, y, vx, vy);
                    if (d < VERTEX_SNAP_M && (best === null || d < best.d)) {
                        best = { x: vx, y: vy, d };
                    }
                }
            }
            if (best) return [best.x, best.y];

            // Otherwise: orthogonal snap to last draft vertex if enabled.
            if (this.snapAxis && this._draftWall && this._draftWall.points.length > 0) {
                const [lx, ly] = this._draftWall.points[this._draftWall.points.length - 1];
                const dx = x - lx;
                const dy = y - ly;
                if (Math.abs(dx) > Math.abs(dy) * 2) y = ly;
                else if (Math.abs(dy) > Math.abs(dx) * 2) x = lx;
                else if (Math.sign(dx) === Math.sign(dy)) {
                    const m = (Math.abs(dx) + Math.abs(dy)) / 2;
                    x = lx + Math.sign(dx) * m;
                    y = ly + Math.sign(dy) * m;
                } else {
                    const m = (Math.abs(dx) + Math.abs(dy)) / 2;
                    x = lx + Math.sign(dx) * m;
                    y = ly - Math.sign(dx) * m;
                }
            }

            // Then clamp to room and snap to grid.
            x = clamp(snap(x), 0, this._roomW);
            y = clamp(snap(y), 0, this._roomH);
            return [x, y];
        },

        // --- pointer ---------------------------------------------------------

        onDown(e) {
            if (e.button !== 0) return;
            const [mx, my] = this._toMeters(e);

            if (this.mode === 'wall') {
                const [x, y] = this._snapVertex(mx, my);
                const now = Date.now();
                // Manual double-click detection: if the second click lands on
                // the same snapped point within 400 ms AND the draft already
                // has ≥ 2 points, commit instead of adding a duplicate.
                // This is more robust than relying on the browser's `dblclick`
                // event, which can be flaky on SVG inside complex DOM.
                const isDouble = this._draftWall
                    && this._draftWall.points.length >= 2
                    && this._lastClickPt
                    && now - this._lastClickAt < 400
                    && dist(this._lastClickPt[0], this._lastClickPt[1], x, y) < 0.05;
                this._lastClickAt = now;
                this._lastClickPt = [x, y];

                if (isDouble) {
                    this.commitWall();
                    return;
                }

                if (!this._draftWall) {
                    this._draftWall = { points: [[x, y]] };
                } else {
                    const last = this._draftWall.points[this._draftWall.points.length - 1];
                    if (dist(last[0], last[1], x, y) > 1e-6) {
                        this._draftWall.points.push([x, y]);
                    }
                }
                this._renderDraft();
                return;
            }

            if (this.mode === 'door' || this.mode === 'window') {
                const hit = this._hitWall(mx, my, 0.3);
                if (!hit) return;
                this._push();
                const item = {
                    id: uid(this.mode === 'door' ? 'd' : 'win'),
                    wall_id: hit.wall.id,
                    t: hit.t,
                    width_m: this.mode === 'door' ? this.doorWidth : this.windowWidth,
                };
                if (this.mode === 'door') {
                    item.swing = 'left_in';
                    this._drawing.doors.push(item);
                } else {
                    this._drawing.windows.push(item);
                }
                this._renderAll();
                return;
            }

            if (this.mode === 'label') {
                const text = (this.labelText || '').trim() || prompt('Testo etichetta:', '');
                if (!text) return;
                this._push();
                this._drawing.labels.push({
                    id: uid('l'),
                    pos: [clamp(snap(mx), 0, this._roomW), clamp(snap(my), 0, this._roomH)],
                    text: String(text).slice(0, 120),
                });
                this._renderAll();
                return;
            }

            if (this.mode === 'erase') {
                const hit = this._hitAny(mx, my);
                if (hit) {
                    this._push();
                    this._remove(hit.kind, hit.id);
                    this._renderAll();
                }
                return;
            }

            // select mode (default): clicking on a shape selects it; an
            // explicit pointer drag (button held + movement past threshold)
            // moves it. Clicking on empty space deselects.
            const hit = this._hitAny(mx, my);
            if (hit) {
                this.selectedId = hit.id;
                this.selectedKind = hit.kind;
                this._drag = { ...hit, originX: mx, originY: my, started: false };
            } else {
                this.selectedId = null; this.selectedKind = null;
                this.selectedKind = null;
                this._drag = null;
            }
            this._renderAll();
        },

        onMove(e) {
            const [mx, my] = this._toMeters(e);

            if (this.mode === 'wall' && this._draftWall) {
                this._hoverPt = this._snapVertex(mx, my);
                this._renderDraft();
                return;
            }

            // Only treat pointermove as a drag while the primary button is
            // actually held. Without this, `_drag` left over from a click
            // would cause the selected shape to follow the cursor.
            if (!this._drag || this.mode !== 'select' || (e.buttons & 1) === 0) return;

            if (!this._drag.started) {
                if (dist(mx, my, this._drag.originX, this._drag.originY) < 0.05) return;
                this._drag.started = true;
                this._push();
            }
            const dx = mx - this._drag.originX;
            const dy = my - this._drag.originY;
            this._drag.originX = mx;
            this._drag.originY = my;

            if (this._drag.kind === 'wall') {
                const w = this._drawing.walls.find((x) => x.id === this._drag.id);
                if (w) {
                    // Translate the wall RIGIDLY: compute the wall's bbox,
                    // clamp the translation against the room boundaries so
                    // the shape never gets deformed when it hits an edge.
                    let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
                    for (const [x, y] of w.points) {
                        if (x < minX) minX = x;
                        if (y < minY) minY = y;
                        if (x > maxX) maxX = x;
                        if (y > maxY) maxY = y;
                    }
                    const cdx = clamp(dx, -minX, this._roomW - maxX);
                    const cdy = clamp(dy, -minY, this._roomH - maxY);
                    if (cdx !== 0 || cdy !== 0) {
                        w.points = w.points.map(([x, y]) => [x + cdx, y + cdy]);
                    }
                }
            } else if (this._drag.kind === 'label') {
                const l = this._drawing.labels.find((x) => x.id === this._drag.id);
                if (l) {
                    l.pos = [
                        clamp(l.pos[0] + dx, 0, this._roomW),
                        clamp(l.pos[1] + dy, 0, this._roomH),
                    ];
                }
            } else if (this._drag.kind === 'door' || this._drag.kind === 'window') {
                // Doors/windows slide along their wall: recompute t from
                // the closest point on the wall to the current pointer.
                const collection = this._drag.kind === 'door' ? this._drawing.doors : this._drawing.windows;
                const item = collection.find((x) => x.id === this._drag.id);
                if (item) {
                    const wall = this._drawing.walls.find((w) => w.id === item.wall_id);
                    if (wall) {
                        const c = closestOnWall(wall, mx, my);
                        item.t = c.t;
                    }
                }
            }
            this._renderAll();
        },

        onUp(e) {
            // Snap any drag-translated wall/label to the grid on release.
            if (this._drag && this._drag.started) {
                if (this._drag.kind === 'wall') {
                    const w = this._drawing.walls.find((x) => x.id === this._drag.id);
                    if (w) w.points = w.points.map(([x, y]) => [snap(x), snap(y)]);
                } else if (this._drag.kind === 'label') {
                    const l = this._drawing.labels.find((x) => x.id === this._drag.id);
                    if (l) l.pos = [snap(l.pos[0]), snap(l.pos[1])];
                }
                this._renderAll();
            }
            // Always clear drag state so pointermove won't keep dragging.
            this._drag = null;
        },

        onDoubleClick(e) {
            // Native dblclick is unreliable on SVG in some browsers, but
            // when it does fire we still honor it as a commit shortcut.
            if (this.mode === 'wall' && this._draftWall && this._draftWall.points.length >= 2) {
                this.commitWall();
            }
        },

        commitWall() {
            if (!this._draftWall || this._draftWall.points.length < 2) return;
            this._push();
            this._drawing.walls.push({
                id: uid('w'),
                points: this._draftWall.points,
                thickness_m: this.wallThickness,
            });
            this._draftWall = null;
            this._hoverPt = null;
            this._lastClickAt = 0;
            this._lastClickPt = null;
            this._renderAll();
        },

        onKey(e) {
            const ctrl = e.metaKey || e.ctrlKey;
            if (ctrl && e.key.toLowerCase() === 'z' && !e.shiftKey) {
                e.preventDefault();
                this.undo();
            } else if (ctrl && (e.key.toLowerCase() === 'y' || (e.key.toLowerCase() === 'z' && e.shiftKey))) {
                e.preventDefault();
                this.redo();
            } else if (e.key === 'Escape') {
                // While drafting a wall, Esc removes the last vertex (so a
                // mis-clicked node can be undone without losing the whole
                // polyline). Only when the draft has 0 vertices left we
                // cancel the draft entirely.
                if (this.mode === 'wall' && this._draftWall) {
                    this._draftWall.points.pop();
                    if (this._draftWall.points.length === 0) {
                        this._draftWall = null;
                    }
                    this._hoverPt = null;
                    this._lastClickAt = 0;
                    this._lastClickPt = null;
                    this._renderDraft();
                    return;
                }
                // Otherwise: drop selection and fall back to select mode so
                // the user can interact normally again.
                this.selectedId = null; this.selectedKind = null;
                this.mode = 'select';
                this._renderAll();
            } else if (e.key === 'Enter' && this.mode === 'wall' && this._draftWall && this._draftWall.points.length >= 2) {
                this.commitWall();
            } else if ((e.key === 'Delete' || e.key === 'Backspace') && this.selectedId && this.mode === 'select') {
                const hit = this._lookupSelected();
                if (hit) {
                    this._push();
                    this._remove(hit.kind, hit.id);
                    this.selectedId = null; this.selectedKind = null;
                    this._renderAll();
                }
            } else if (e.key.startsWith('Arrow') && this.selectedId && this.mode === 'select') {
                // Nudge the selected shape by 0.1 m (Shift = 1 m). Walls
                // translate rigidly with bbox clamping; doors/windows slide
                // along their wall via left/right arrows only.
                const step = e.shiftKey ? 1.0 : SNAP_M;
                let nx = 0, ny = 0;
                if (e.key === 'ArrowLeft') nx = -step;
                else if (e.key === 'ArrowRight') nx = step;
                else if (e.key === 'ArrowUp') ny = -step;
                else if (e.key === 'ArrowDown') ny = step;
                else return;
                e.preventDefault();
                const sel = this._lookupSelected();
                if (!sel) return;
                this._push();
                if (sel.kind === 'wall') {
                    const w = this._drawing.walls.find((x) => x.id === sel.id);
                    if (w) {
                        let minX = Infinity, minY = Infinity, maxX = -Infinity, maxY = -Infinity;
                        for (const [x, y] of w.points) {
                            if (x < minX) minX = x;
                            if (y < minY) minY = y;
                            if (x > maxX) maxX = x;
                            if (y > maxY) maxY = y;
                        }
                        const cdx = clamp(nx, -minX, this._roomW - maxX);
                        const cdy = clamp(ny, -minY, this._roomH - maxY);
                        w.points = w.points.map(([x, y]) => [snap(x + cdx), snap(y + cdy)]);
                    }
                } else if (sel.kind === 'label') {
                    const l = this._drawing.labels.find((x) => x.id === sel.id);
                    if (l) {
                        l.pos = [
                            snap(clamp(l.pos[0] + nx, 0, this._roomW)),
                            snap(clamp(l.pos[1] + ny, 0, this._roomH)),
                        ];
                    }
                } else if (sel.kind === 'door' || sel.kind === 'window') {
                    // Project the arrow-key delta onto the wall's tangent at
                    // the current position so all four arrows work: each one
                    // moves the door in the direction of its key, but
                    // constrained to slide along the wall (so on a diagonal
                    // wall, Right and Down both move "forward" if the wall
                    // slopes down-right).
                    const coll = sel.kind === 'door' ? this._drawing.doors : this._drawing.windows;
                    const item = coll.find((x) => x.id === sel.id);
                    if (item) {
                        const wall = this._drawing.walls.find((w) => w.id === item.wall_id);
                        if (wall) {
                            const len = wallLength(wall);
                            if (len > 0) {
                                const param = wallParam(wall, item.t);
                                const a = wall.points[param.seg];
                                const b = wall.points[param.seg + 1];
                                const tx = b[0] - a[0];
                                const ty = b[1] - a[1];
                                const tlen = Math.hypot(tx, ty) || 1;
                                const along = (nx * tx + ny * ty) / tlen;
                                item.t = clamp(item.t + along / len, 0, 1);
                            }
                        }
                    }
                }
                this._renderAll();
            }
        },

        // --- toolbar actions -------------------------------------------------

        setMode(m) {
            // Clicking the same tool again returns to the default select
            // mode, since there is no longer an explicit "Select" button.
            this.mode = this.mode === m ? 'select' : m;
            this._draftWall = null;
            this._hoverPt = null;
            this.selectedId = null; this.selectedKind = null;
            this._renderAll();
        },

        undo() {
            const snap = this._undo.pop();
            if (!snap) return;
            this._redo.push(JSON.stringify(this._drawing));
            this._drawing = JSON.parse(snap);
            this._renderAll();
        },

        redo() {
            const snap = this._redo.pop();
            if (!snap) return;
            this._undo.push(JSON.stringify(this._drawing));
            this._drawing = JSON.parse(snap);
            this._renderAll();
        },

        save() {
            this.$wire.savePlan(this._drawing);
        },

        clearAll() {
            if (!confirm('Cancellare tutto il disegno?')) return;
            this._push();
            this._drawing = { version: 1, walls: [], doors: [], windows: [], labels: [] };
            this._renderAll();
        },

        toggleDoorSwing() {
            if (!this.selectedId) return;
            const door = this._drawing.doors.find((d) => d.id === this.selectedId);
            if (!door) return;
            const swings = ['left_in', 'right_in', 'left_out', 'right_out'];
            this._push();
            door.swing = swings[(swings.indexOf(door.swing) + 1) % swings.length];
            this._renderAll();
        },

        // --- hit-testing -----------------------------------------------------

        _hitAny(mx, my) {
            for (const l of this._drawing.labels) {
                if (dist(l.pos[0], l.pos[1], mx, my) < 0.3) return { kind: 'label', id: l.id };
            }
            for (const d of this._drawing.doors) {
                const wall = this._drawing.walls.find((w) => w.id === d.wall_id);
                if (!wall) continue;
                const { pt } = wallParam(wall, d.t);
                if (dist(pt[0], pt[1], mx, my) < 0.35) return { kind: 'door', id: d.id };
            }
            for (const w of this._drawing.windows) {
                const wall = this._drawing.walls.find((x) => x.id === w.wall_id);
                if (!wall) continue;
                const { pt } = wallParam(wall, w.t);
                if (dist(pt[0], pt[1], mx, my) < 0.35) return { kind: 'window', id: w.id };
            }
            const hit = this._hitWall(mx, my, 0.2);
            if (hit) return { kind: 'wall', id: hit.wall.id };
            return null;
        },

        _hitWall(mx, my, tol) {
            let best = null;
            for (const w of this._drawing.walls) {
                const c = closestOnWall(w, mx, my);
                if (c.d < tol && (best === null || c.d < best.d)) {
                    best = { wall: w, t: c.t, d: c.d };
                }
            }
            return best;
        },

        _lookupSelected() {
            if (!this.selectedId) return null;
            for (const kind of ['wall', 'door', 'window', 'label']) {
                const coll = kind === 'wall' ? this._drawing.walls
                    : kind === 'door' ? this._drawing.doors
                    : kind === 'window' ? this._drawing.windows
                    : this._drawing.labels;
                if (coll.find((x) => x.id === this.selectedId)) return { kind, id: this.selectedId };
            }
            return null;
        },

        _remove(kind, id) {
            if (kind === 'wall') {
                this._drawing.walls = this._drawing.walls.filter((w) => w.id !== id);
                // also drop anchored doors/windows
                this._drawing.doors = this._drawing.doors.filter((d) => d.wall_id !== id);
                this._drawing.windows = this._drawing.windows.filter((w) => w.wall_id !== id);
            } else if (kind === 'door') {
                this._drawing.doors = this._drawing.doors.filter((d) => d.id !== id);
            } else if (kind === 'window') {
                this._drawing.windows = this._drawing.windows.filter((w) => w.id !== id);
            } else if (kind === 'label') {
                this._drawing.labels = this._drawing.labels.filter((l) => l.id !== id);
            }
        },

        // --- render ----------------------------------------------------------

        _renderAll() {
            const layer = this._svg.querySelector('g.plan-layer');
            if (!layer) return;
            const s = this._scale;
            const parts = [];

            // Walls
            for (const w of this._drawing.walls) {
                const pts = w.points.map(([x, y]) => `${x * s},${y * s}`).join(' ');
                const sel = w.id === this.selectedId ? '#4f46e5' : '#0f172a';
                const strokeW = w.thickness_m * s;
                parts.push(`<polyline points="${pts}" fill="none" stroke="${sel}" stroke-width="${strokeW}" stroke-linecap="square" stroke-linejoin="miter" data-kind="wall" data-id="${w.id}" />`);
                // Dimensions per segment
                for (let i = 0; i < w.points.length - 1; i++) {
                    const [a, b] = wallSegment(w, i);
                    const len = dist(a[0], a[1], b[0], b[1]);
                    if (len < 0.3) continue;
                    const mx = (a[0] + b[0]) / 2 * s;
                    const my = (a[1] + b[1]) / 2 * s;
                    parts.push(`<text x="${mx}" y="${my - 4}" text-anchor="middle" font-size="9" fill="#6b7280" style="pointer-events:none">${len.toFixed(2)} m</text>`);
                }
            }

            // Windows — rotated to the wall tangent so they sit along the wall.
            for (const wn of this._drawing.windows) {
                const wall = this._drawing.walls.find((x) => x.id === wn.wall_id);
                if (!wall) continue;
                const param = wallParam(wall, wn.t);
                const center = param.pt;
                const a = wall.points[param.seg];
                const b = wall.points[param.seg + 1];
                const angleDeg = Math.atan2(b[1] - a[1], b[0] - a[0]) * 180 / Math.PI;
                const wpx = wn.width_m * s;
                const sel = wn.id === this.selectedId ? '#4f46e5' : '#2563eb';
                const cx = center[0] * s;
                const cy = center[1] * s;
                parts.push(`<g data-kind="window" data-id="${wn.id}" transform="translate(${cx},${cy}) rotate(${angleDeg})">`);
                parts.push(`  <rect x="${-wpx/2}" y="-3" width="${wpx}" height="6" fill="#bfdbfe" stroke="${sel}" stroke-width="1.5" />`);
                parts.push(`</g>`);
            }

            // Doors — rotated to the wall tangent. The "left/right" swing
            // refers to the door's hinge side relative to the wall direction;
            // "in/out" refers to which side of the wall the door opens onto.
            for (const d of this._drawing.doors) {
                const wall = this._drawing.walls.find((x) => x.id === d.wall_id);
                if (!wall) continue;
                const param = wallParam(wall, d.t);
                const center = param.pt;
                const a = wall.points[param.seg];
                const b = wall.points[param.seg + 1];
                const angleDeg = Math.atan2(b[1] - a[1], b[0] - a[0]) * 180 / Math.PI;
                const wpx = d.width_m * s;
                const cx = center[0] * s;
                const cy = center[1] * s;
                const sel = d.id === this.selectedId ? '#4f46e5' : '#dc2626';
                const sweepDir = d.swing.startsWith('left') ? -1 : 1;
                const inOut = d.swing.endsWith('in') ? 1 : -1;
                const r = wpx;
                const arcSweep = sweepDir === inOut ? 1 : 0;
                // Architectural symbol (top view): in local coords the wall
                // runs along the X axis. The hinge sits at the door-opening
                // edge `(-wpx/2 * sweepDir, 0)`; the leaf is drawn fully
                // OPEN, perpendicular to the wall, ending at
                // `(-wpx/2 * sweepDir, wpx * inOut)`. A thin continuous arc
                // sweeps from the leaf tip back to the opposite jamb on the
                // wall `(wpx/2 * sweepDir, 0)`, illustrating the swing path.
                const hingeX = -wpx / 2 * sweepDir;
                const jambX = wpx / 2 * sweepDir;
                const leafEndY = wpx * inOut;
                // Large-arc-flag = 0 (quarter circle), sweep-flag chosen so
                // the curve bulges away from the wall on the correct side.
                const arcFlag = sweepDir === inOut ? 0 : 1;
                parts.push(`<g data-kind="door" data-id="${d.id}" transform="translate(${cx},${cy}) rotate(${angleDeg})">`);
                // Highlight the opening on the wall with a short break so the
                // wall stroke doesn't visually cover the symbol.
                parts.push(`  <line x1="${hingeX}" y1="0" x2="${jambX}" y2="0" stroke="#ffffff" stroke-width="2.2" />`);
                // Leaf (open) — solid line, hinge → tip.
                parts.push(`  <line x1="${hingeX}" y1="0" x2="${hingeX}" y2="${leafEndY}" stroke="${sel}" stroke-width="1.4" stroke-linecap="round" />`);
                // Swing arc — continuous, thin.
                parts.push(`  <path d="M ${hingeX} ${leafEndY} A ${wpx} ${wpx} 0 0 ${arcFlag} ${jambX} 0" fill="none" stroke="${sel}" stroke-width="0.7" />`);
                parts.push(`</g>`);
            }

            // Labels
            for (const l of this._drawing.labels) {
                const sel = l.id === this.selectedId ? '#4f46e5' : '#111827';
                parts.push(`<text x="${l.pos[0]*s}" y="${l.pos[1]*s}" text-anchor="middle" font-size="11" font-weight="600" fill="${sel}" data-kind="label" data-id="${l.id}">${this._escape(l.text)}</text>`);
            }

            layer.innerHTML = parts.join('');
            this._renderDraft();
        },

        _renderDraft() {
            const draftLayer = this._svg.querySelector('g.plan-draft');
            if (!draftLayer) return;
            if (!this._draftWall) {
                draftLayer.innerHTML = '';
                return;
            }
            const s = this._scale;
            const pts = this._draftWall.points.slice();
            if (this._hoverPt) pts.push(this._hoverPt);
            const pointsAttr = pts.map(([x, y]) => `${x * s},${y * s}`).join(' ');
            const dots = this._draftWall.points
                .map(([x, y]) => `<circle cx="${x*s}" cy="${y*s}" r="3" fill="#4f46e5" />`)
                .join('');
            draftLayer.innerHTML = `
                <polyline points="${pointsAttr}" fill="none" stroke="#4f46e5" stroke-width="${this.wallThickness * s}" stroke-opacity="0.55" stroke-dasharray="4,3" />
                ${dots}
            `;
        },

        _escape(s) {
            return String(s).replace(/[<>&"]/g, (c) => ({ '<': '&lt;', '>': '&gt;', '&': '&amp;', '"': '&quot;' }[c]));
        },
    };
}
