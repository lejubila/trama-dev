/**
 * Alpine factory for the rack-elevation drag & drop.
 *
 * Wired up with `x-data="rackDnD" x-init="init($el)"` on the SVG container
 * (see resources/views/livewire/racks/elevation.blade.php). The factory:
 *  - tracks a mousedown on any `<g.rack-equipment>` whose `data-locked="0"`
 *  - shows a small ghost label with the candidate U as the pointer moves
 *  - on mouseup, computes the snap-to-U from the SVG geometry stored as
 *    `data-u-px`, `data-pad-top`, `data-rack-units` on the SVG element, and
 *    dispatches a Livewire `moveEquipment` event with the new start U
 *
 * Slot clicks (empty-U add) and equipment clicks (drawer open) are handled
 * directly by `wire:click` on the SVG elements; this script only intervenes
 * when the user actually drags.
 */
export default function rackDnD() {
    return {
        dragging: false,
        hint: '',
        // Internal state captured at mousedown
        _svg: null,
        _eqId: null,
        _startUOriginal: null,
        _uHeight: null,
        _uPx: 24,
        _padTop: 20,
        _rackUnits: 42,
        _pointerYAtStart: 0,
        _bandYAtStart: 0,
        // Movement threshold (px) before we treat the gesture as a drag rather
        // than a click. Below this, click events still propagate to wire:click.
        _DRAG_THRESHOLD: 4,
        _moved: false,

        init(root) {
            this._root = root;
            this._svg = root.querySelector('svg.rack-elevation');
            if (!this._svg) return;

            this._uPx = parseFloat(this._svg.dataset.uPx || '24');
            this._padTop = parseFloat(this._svg.dataset.padTop || '20');
            this._rackUnits = parseInt(this._svg.dataset.rackUnits || '42', 10);

            this._svg.addEventListener('mousedown', (e) => this._onDown(e));
            window.addEventListener('mousemove', (e) => this._onMove(e));
            window.addEventListener('mouseup', (e) => this._onUp(e));
        },

        _onDown(e) {
            const group = e.target.closest('g.rack-equipment');
            if (!group) return;
            if (group.dataset.locked === '1') return;

            const rect = group.querySelector('rect');
            if (!rect) return;

            this._eqId = parseInt(group.dataset.id, 10);
            this._startUOriginal = parseInt(group.dataset.uStart, 10);
            this._uHeight = parseInt(group.dataset.uHeight, 10);
            this._pointerYAtStart = e.clientY;
            this._bandYAtStart = parseFloat(rect.getAttribute('y'));
            this._moved = false;

            // Don't visually start dragging until the user actually moves —
            // lets a plain click reach wire:click.
        },

        _onMove(e) {
            if (this._eqId === null) return;

            const dy = e.clientY - this._pointerYAtStart;
            if (!this._moved && Math.abs(dy) < this._DRAG_THRESHOLD) return;

            if (!this._moved) {
                this._moved = true;
                this.dragging = true;
                // Stop the upcoming `mouseup` from firing wire:click on the equipment
                document.body.style.cursor = 'grabbing';
            }

            // Convert pointer Y to candidate start U
            const candidate = this._candidateStartU(e);
            this.hint = `U${candidate}`;

            const ghost = this.$refs.ghost;
            if (ghost) {
                ghost.style.left = (e.pageX + 12) + 'px';
                ghost.style.top = (e.pageY + 12) + 'px';
            }
        },

        _onUp(e) {
            if (this._eqId === null) return;

            const id = this._eqId;
            const startUOriginal = this._startUOriginal;
            const moved = this._moved;

            // Reset state regardless of outcome
            this.dragging = false;
            this.hint = '';
            this._eqId = null;
            document.body.style.cursor = '';

            if (!moved) return; // plain click — wire:click handles it

            // Suppress the click event that would otherwise fire on mouseup,
            // so wire:click on the equipment block doesn't open the drawer.
            const swallow = (ev) => {
                ev.stopPropagation();
                ev.preventDefault();
                window.removeEventListener('click', swallow, true);
            };
            window.addEventListener('click', swallow, true);

            const candidate = this._candidateStartU(e);
            if (candidate === startUOriginal) return;

            // Dispatch to Livewire (the elevation component listens via #[On('moveEquipment')])
            window.Livewire.dispatch('moveEquipment', { id, newStartU: candidate });
        },

        _candidateStartU(e) {
            // Translate clientY → SVG user units → U index. We render with U1
            // at the BOTTOM, so y=padTop is the top edge of the highest U.
            const ctm = this._svg.getScreenCTM();
            if (!ctm) return this._startUOriginal;
            const pt = this._svg.createSVGPoint();
            pt.x = 0;
            pt.y = e.clientY;
            const local = pt.matrixTransform(ctm.inverse());

            // The drag should track the *top* of the equipment band. The band's
            // current top maps to U = position_u_start + height - 1 (highest U
            // covered). Compute the new top-U then derive new start.
            const rawTopU = this._rackUnits - Math.floor((local.y - this._padTop) / this._uPx);
            const newTopU = Math.max(this._uHeight, Math.min(this._rackUnits, rawTopU));
            const newStartU = newTopU - this._uHeight + 1;
            return Math.max(1, newStartU);
        },
    };
}
