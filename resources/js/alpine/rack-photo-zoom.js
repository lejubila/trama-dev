export default function rackPhotoZoom() {
    return {
        scale: 1,
        tx: 0,
        ty: 0,
        dragging: false,
        lastX: 0,
        lastY: 0,
        pinchStartDist: 0,
        pinchStartScale: 1,

        get transformStyle() {
            return `transform: translate(${this.tx}px, ${this.ty}px) scale(${this.scale}); transform-origin: 0 0;`;
        },

        reset() {
            this.scale = 1;
            this.tx = 0;
            this.ty = 0;
        },

        init() {
            this.$watch(() => this.$wire.lightboxIndex, () => this.reset());
        },

        zoomAt(px, py, target) {
            const s = Math.min(6, Math.max(1, target));
            const ratio = s / this.scale;
            this.tx = px - (px - this.tx) * ratio;
            this.ty = py - (py - this.ty) * ratio;
            this.scale = s;
            if (s === 1) {
                this.tx = 0;
                this.ty = 0;
            }
        },

        onWheel(e) {
            e.preventDefault();
            const rect = this.$el.getBoundingClientRect();
            const mx = e.clientX - rect.left;
            const my = e.clientY - rect.top;
            const factor = e.deltaY < 0 ? 1.15 : 1 / 1.15;
            this.zoomAt(mx, my, this.scale * factor);
        },

        onMouseDown(e) {
            if (this.scale <= 1) return;
            this.dragging = true;
            this.lastX = e.clientX;
            this.lastY = e.clientY;
        },

        onMouseMove(e) {
            if (!this.dragging) return;
            this.tx += e.clientX - this.lastX;
            this.ty += e.clientY - this.lastY;
            this.lastX = e.clientX;
            this.lastY = e.clientY;
        },

        onMouseUp() {
            this.dragging = false;
        },

        onDblClick(e) {
            const rect = this.$el.getBoundingClientRect();
            const mx = e.clientX - rect.left;
            const my = e.clientY - rect.top;
            if (this.scale > 1) {
                this.reset();
            } else {
                this.zoomAt(mx, my, 2.5);
            }
        },

        onTouchStart(e) {
            if (e.touches.length === 2) {
                const [a, b] = e.touches;
                this.pinchStartDist = Math.hypot(b.clientX - a.clientX, b.clientY - a.clientY);
                this.pinchStartScale = this.scale;
            } else if (e.touches.length === 1 && this.scale > 1) {
                this.dragging = true;
                this.lastX = e.touches[0].clientX;
                this.lastY = e.touches[0].clientY;
            }
        },

        onTouchMove(e) {
            if (e.touches.length === 2) {
                e.preventDefault();
                const [a, b] = e.touches;
                const d = Math.hypot(b.clientX - a.clientX, b.clientY - a.clientY);
                if (this.pinchStartDist === 0) return;
                const rect = this.$el.getBoundingClientRect();
                const mx = (a.clientX + b.clientX) / 2 - rect.left;
                const my = (a.clientY + b.clientY) / 2 - rect.top;
                this.zoomAt(mx, my, this.pinchStartScale * (d / this.pinchStartDist));
            } else if (this.dragging && e.touches.length === 1) {
                e.preventDefault();
                this.tx += e.touches[0].clientX - this.lastX;
                this.ty += e.touches[0].clientY - this.lastY;
                this.lastX = e.touches[0].clientX;
                this.lastY = e.touches[0].clientY;
            }
        },

        onTouchEnd() {
            this.dragging = false;
            this.pinchStartDist = 0;
        },
    };
}
