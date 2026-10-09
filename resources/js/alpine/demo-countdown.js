/**
 * Live "next reset in 5 h 12 min" countdown for the demo banner.
 * The target instant comes from the server (Demo::nextResetAt()).
 */
export default function demoCountdown({ target, labels }) {
    return {
        remaining: '',
        _timer: null,

        init() {
            const end = new Date(target).getTime();
            const tick = () => {
                const mins = Math.max(0, Math.ceil((end - Date.now()) / 60000));
                const h = Math.floor(mins / 60);
                const m = mins % 60;
                this.remaining = h > 0 ? `${h} ${labels.h} ${m} ${labels.min}` : `${m} ${labels.min}`;
            };
            tick();
            this._timer = setInterval(tick, 30000);
        },

        destroy() {
            clearInterval(this._timer);
        },
    };
}
