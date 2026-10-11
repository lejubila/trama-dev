import { driver } from 'driver.js';

/**
 * Guided tour orchestrator (Alpine store `guide`).
 *
 * The tours come from the server (<x-guide>, App\Support\Guide\GuideTours):
 * each step names a page and a `[data-guide]` element on it. The user works
 * on the real forms; driver.js dims the rest of the page and the step
 * advances on its own (see GuideTours for the `advance` kinds).
 *
 * driver.js only knows about the current page, so the progress lives in
 * sessionStorage and the store picks it up again after every wire:navigate
 * or reload.
 */

const STATE_KEY = 'trama.guide';
const LATER_KEY = 'trama.guide.later';
const WAIT_MS = 8000;
const POLL_MS = 150;
// A form that vanishes was either saved or cancelled; the "created" event
// of a save arrives well within this delay.
const GONE_MS = 1200;

// Runtime handles kept out of the Alpine store: reactive proxies around
// driver.js and DOM nodes would break identity checks.
const rt = { driver: null, current: null, timers: [], runId: 0 };

function storageGet(key) {
    try {
        const raw = window.sessionStorage.getItem(key);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function storageSet(key, value) {
    try {
        if (value === null) {
            window.sessionStorage.removeItem(key);
        } else {
            window.sessionStorage.setItem(key, JSON.stringify(value));
        }
    } catch {
        // Private mode / blocked storage: the tour just won't survive a reload.
    }
}

function readConfig() {
    const el = document.getElementById('trama-guide');
    if (!el) return null;
    try {
        return JSON.parse(el.textContent);
    } catch {
        return null;
    }
}

// Below this the popover text wraps too much to be worth docking beside.
const MIN_DOCKED_WIDTH = 240;

function overlapArea(a, b) {
    const w = Math.min(a.right, b.right) - Math.max(a.left, b.left);
    const h = Math.min(a.bottom, b.bottom) - Math.max(a.top, b.top);
    return w > 0 && h > 0 ? w * h : 0;
}

function findTarget(name) {
    const el = document.querySelector(`[data-guide="${name}"]`);
    return el && el.getClientRects().length > 0 ? el : null;
}

function navigate(url) {
    if (window.Livewire && typeof window.Livewire.navigate === 'function') {
        window.Livewire.navigate(url);
    } else {
        window.location.href = url;
    }
}

export default function guideStore() {
    return {
        menuOpen: false,
        offerVisible: false,
        config: null,
        // { tourId, step, ctx: {site: 12, …}, full, seen: [nonce…], navigatedFor }
        state: null,

        init() {
            this.state = storageGet(STATE_KEY);

            document.addEventListener('livewire:navigated', () => this.refresh());
            const hookLivewire = () => {
                window.Livewire.on('guide-created', (payload) => this.onCreated(payload));
                // A Livewire re-render can strip driver.js's classes from the
                // highlighted element (or replace it): highlight it again.
                window.Livewire.hook('commit', ({ succeed }) => {
                    succeed(() => window.requestAnimationFrame(() => this.rehighlight()));
                });
            };
            // Stores are registered on alpine:init, which Livewire fires
            // after its own livewire:init: Livewire is normally there already.
            if (window.Livewire) hookLivewire();
            else document.addEventListener('livewire:init', hookLivewire);
            document.addEventListener('keydown', (e) => {
                if (e.key === 'Escape' && this.state && !this.menuOpen) this.stop();
            });
            // driver.js repositions the popover on resize: dock it again.
            window.addEventListener('resize', () => this.dockPopoverSoon());

            // livewire:navigated also fires on the first load; this covers
            // pages where it ran before the store existed.
            window.setTimeout(() => this.refresh(), 0);
        },

        // ── entry points ────────────────────────────────────────────────

        openMenu() {
            this.offerVisible = false;
            this.menuOpen = true;
        },

        start(tourId, full = false, ctx = {}) {
            if (!this.config?.tours?.[tourId]) return;
            this.menuOpen = false;
            this.offerVisible = false;
            const stale = this.config.lastCreated?.nonce;
            this.state = { tourId, step: 0, ctx, full, seen: stale ? [stale] : [], skipped: [], navigatedFor: null };
            this.persist();
            this.runStep();
        },

        startFull() {
            const first = this.config?.order?.[0];
            if (first) this.start(first, true);
        },

        stop() {
            rt.runId++;
            this.clearTimers();
            this.destroyDriver();
            this.state = null;
            this.persist();
        },

        acceptOffer() {
            this.dismissOffer();
            this.startFull();
        },

        postponeOffer() {
            this.offerVisible = false;
            storageSet(LATER_KEY, true);
        },

        dismissOffer() {
            this.offerVisible = false;
            if (!this.config?.dismissUrl) return;
            const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            window.fetch(this.config.dismissUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token, Accept: 'application/json' },
                credentials: 'same-origin',
            }).catch(() => {});
        },

        // ── lifecycle ───────────────────────────────────────────────────

        refresh() {
            this.config = readConfig();
            if (!this.config) {
                // Logged out, read-only user or no tours: nothing to guide.
                if (this.state) this.stop();
                this.offerVisible = false;
                return;
            }

            if (this.state && !this.config.tours[this.state.tourId]) {
                this.stop();
            }
            if (this.state && this.config.lastCreated) {
                this.onCreated(this.config.lastCreated, false);
            }

            this.offerVisible = !this.state && !this.menuOpen && this.config.offer && !storageGet(LATER_KEY);

            if (this.state) this.runStep();
        },

        onCreated(payload, rerun = true) {
            if (!this.state || !payload?.entity) return;
            if (payload.nonce && this.state.seen.includes(payload.nonce)) return;
            if (payload.nonce) this.state.seen = [...this.state.seen.slice(-19), payload.nonce];

            this.state.ctx[payload.entity] = payload.id;

            // The form may already have been treated as cancelled (it vanished
            // before the event arrived), so look one step back as well.
            const steps = this.tour()?.steps ?? [];
            for (let i = Math.max(0, this.state.step - 1); i < steps.length; i++) {
                if (steps[i].advance === `created:${payload.entity}`) {
                    this.state.step = i + 1;
                    break;
                }
            }
            this.persist();
            if (rerun) this.runStep();
        },

        tour() {
            return this.state ? this.config?.tours?.[this.state.tourId] : null;
        },

        persist() {
            storageSet(STATE_KEY, this.state);
        },

        advance() {
            this.state.step++;
            this.persist();
            this.runStep();
        },

        async runStep() {
            const run = ++rt.runId;
            this.clearTimers();

            const tour = this.tour();
            if (!tour) return this.stop();

            const step = tour.steps[this.state.step];
            if (!step) return this.showDone(tour);

            if ((step.skipIf && this.state.ctx[step.skipIf]) || (step.skipWhen && this.config.facts?.[step.skipWhen])) {
                // Kept out of the "step x of y" count.
                this.state.skipped = [...new Set([...(this.state.skipped ?? []), this.state.step])];
                this.state.step++;
                this.persist();
                return this.runStep();
            }

            // "Open one of these": the user just did, remember which.
            if (step.advance.startsWith('route:') && this.captureRoute(step.advance.slice(6))) {
                return this.advance();
            }

            const url = this.stepUrl(step);
            if (url === null || !this.onPage(step, url)) {
                const key = `${this.state.tourId}:${this.state.step}`;
                // Bring the user to the step's page once; after that, a
                // different page means they chose to wander off.
                if (url !== null && this.state.navigatedFor !== key) {
                    this.state.navigatedFor = key;
                    this.persist();
                    this.destroyDriver();
                    navigate(url);
                    return;
                }
                return this.showNotFound(step, url);
            }

            const el = await this.waitFor(step.target, run);
            if (run !== rt.runId) return;
            if (!el) return this.showNotFound(step, url);

            this.highlight(el, step, tour);
            this.watch(step, run);
        },

        // ── step helpers ────────────────────────────────────────────────

        stepUrl(step) {
            let missing = false;
            let path = step.url.replace(/\{(\w+)\}/g, (_, name) => {
                const value = this.state.ctx[name];
                if (value === undefined || value === null) missing = true;
                return encodeURIComponent(value ?? '');
            });
            if (missing) return null;

            const params = new URLSearchParams(step.query ?? {});
            for (const [param, ctxKey] of Object.entries(step.ctxQuery ?? {})) {
                if (this.state.ctx[ctxKey]) params.set(param, this.state.ctx[ctxKey]);
            }
            const query = params.toString();
            return query ? `${path}?${query}` : path;
        },

        onPage(step, url) {
            const target = new URL(url, window.location.origin);
            if (target.pathname.replace(/\/$/, '') !== window.location.pathname.replace(/\/$/, '')) return false;
            const current = new URLSearchParams(window.location.search);
            return Object.entries(step.query ?? {}).every(([k, v]) => current.get(k) === String(v));
        },

        captureRoute(routeName) {
            const [template, param] = this.config.routes[routeName] ?? [];
            if (!template) return false;
            const pattern = new RegExp(
                '^' + template.replace(/[.*+?^$()|[\]\\]/g, '\\$&').replace(`{${param}}`, '([^/]+)') + '/?$',
            );
            const match = window.location.pathname.match(pattern);
            if (!match) return false;
            this.state.ctx[param] = decodeURIComponent(match[1]);
            return true;
        },

        waitFor(target, run) {
            return new Promise((resolve) => {
                const started = Date.now();
                const tick = () => {
                    if (run !== rt.runId) return resolve(null);
                    const el = findTarget(target);
                    if (el) return resolve(el);
                    if (Date.now() - started > WAIT_MS) return resolve(null);
                    rt.timers.push(window.setTimeout(tick, POLL_MS));
                };
                tick();
            });
        },

        watch(step, run) {
            let goneSince = null;
            const id = window.setInterval(() => {
                if (run !== rt.runId) return window.clearInterval(id);

                if (step.advance.startsWith('appear:') && findTarget(step.advance.slice(7))) {
                    return this.advance();
                }
                if (step.loop && findTarget(step.loop.appear)) {
                    this.state.step = step.loop.to;
                    this.persist();
                    return this.runStep();
                }

                // The highlighted form was closed without saving: back to the
                // step that opens it.
                if (!findTarget(step.target)) {
                    goneSince ??= Date.now();
                    if (Date.now() - goneSince > GONE_MS) {
                        const prev = this.tour().steps[this.state.step - 1];
                        if (prev && prev.advance === `appear:${step.target}`) this.state.step--;
                        this.persist();
                        this.runStep();
                    }
                } else {
                    goneSince = null;
                }
            }, POLL_MS * 2);
            rt.timers.push(id);
        },

        clearTimers() {
            rt.timers.forEach((t) => {
                window.clearTimeout(t);
                window.clearInterval(t);
            });
            rt.timers = [];
        },

        // ── rendering ───────────────────────────────────────────────────

        highlight(el, step, tour) {
            const skipped = this.state.skipped ?? [];
            const before = skipped.filter((i) => i < this.state.step).length;
            rt.current = { el, step, tour };
            const s = this.config.strings;
            const buttons = [{ label: s.exit, action: () => this.stop(), secondary: true }];
            if (step.advance === 'next') buttons.push({ label: s.next, action: () => this.advance() });

            this.show(
                el,
                step.title,
                step.text,
                buttons,
                s.stepOf.replace(':current', this.state.step + 1 - before).replace(':total', tour.steps.length - skipped.length) + ' · ' + tour.title,
            );
        },

        rehighlight() {
            const cur = rt.current;
            if (!this.state || !cur || !rt.driver) return;
            const el = findTarget(cur.step.target);
            if (!el) return;
            if (el === cur.el) {
                // Same node, but the morph reset its class attribute (e.g. a
                // wire:model.live field in the form): restore driver.js's
                // marks in place. Rebuilding would flash the overlay and move
                // the focus to the popover on every keystroke.
                el.classList.add('driver-active-element');
                el.setAttribute('aria-haspopup', 'dialog');
                el.setAttribute('aria-expanded', 'true');
                el.setAttribute('aria-controls', 'driver-popover-content');
                rt.driver.refresh();
                this.dockPopoverSoon();
                return;
            }
            const focused = document.activeElement;
            this.highlight(el, cur.step, cur.tour);
            if (focused && focused !== document.body && el.contains(focused)) focused.focus();
        },

        showNotFound(step, url) {
            const s = this.config.strings;
            const buttons = [{ label: s.exit, action: () => this.stop(), secondary: true }];
            if (url !== null) {
                buttons.push({
                    label: this.onPage(step, url) ? s.retry : s.goToStep,
                    action: () => {
                        // Forms only exist after their opener was clicked.
                        const prev = this.tour().steps[this.state.step - 1];
                        if (prev && prev.advance === `appear:${step.target}`) this.state.step--;
                        this.state.navigatedFor = null;
                        this.persist();
                        this.runStep();
                    },
                });
            }
            rt.current = null;
            this.show(null, s.notFoundTitle, s.notFoundText, buttons);
        },

        showDone(tour) {
            const s = this.config.strings;
            const next = tour.next ? this.config.tours[tour.next] : null;
            const buttons = [{ label: s.close, action: () => this.stop(), secondary: true }];
            if (next) {
                buttons.push({
                    label: s.continueWith.replace(':tour', next.title),
                    action: () => this.start(next.id, this.state.full, this.state.ctx),
                });
            }
            rt.current = null;
            this.show(null, s.doneTitle, next ? s.doneText.replace(':tour', tour.title) : s.doneLast, buttons);
        },

        show(el, title, text, buttons, progress = '') {
            this.destroyDriver();
            rt.driver = driver({
                allowClose: false,
                overlayOpacity: 0.6,
                stagePadding: 6,
                stageRadius: 8,
                smoothScroll: true,
                popoverClass: 'trama-guide-popover',
                showButtons: [],
                onPopoverRender: (popover) => {
                    if (progress) {
                        popover.progress.textContent = progress;
                        popover.progress.style.display = 'block';
                    }
                    popover.footerButtons.replaceChildren(
                        ...buttons.map((b) => {
                            const btn = document.createElement('button');
                            btn.type = 'button';
                            btn.textContent = b.label;
                            btn.className = b.secondary ? 'trama-guide-btn-secondary' : 'trama-guide-btn-primary';
                            btn.addEventListener('click', b.action);
                            return btn;
                        }),
                    );
                    popover.footer.style.display = 'flex';
                },
            });
            const popover = { title, description: text };
            rt.driver.highlight(el ? { element: el, popover } : { popover });
            if (el) {
                // Once now and once after the smooth scroll has settled.
                this.dockPopoverSoon();
                rt.timers.push(window.setTimeout(() => this.dockPopover(), 600));
            }
        },

        // A target taller than the free space (a long form, a document
        // section) leaves driver.js no side to use, so the popover lands on
        // top of the fields to fill in: move it to the viewport corner.
        // driver.js repositions in an animation frame of its own: run after it.
        dockPopoverSoon() {
            window.requestAnimationFrame(() => window.requestAnimationFrame(() => this.dockPopover()));
        },

        dockPopover() {
            const el = rt.current?.el;
            const pop = document.querySelector('.driver-popover.trama-guide-popover');
            if (!rt.driver || !el || !pop) return;
            const arrow = pop.querySelector('.driver-popover-arrow');
            arrow?.style.removeProperty('display');
            pop.style.removeProperty('width');
            const p = pop.getBoundingClientRect();
            const e = el.getBoundingClientRect();
            if (overlapArea(p, e) === 0) return;

            const vw = window.innerWidth;
            const vh = window.innerHeight;
            const pad = 16;
            const place = (style) => {
                Object.assign(pop.style, { top: 'auto', left: 'auto', right: 'auto', bottom: 'auto', ...style });
                arrow?.style.setProperty('display', 'none');
            };

            // Beside the target (a centred modal leaves room on its sides),
            // narrowed to fit if needed.
            const right = vw - e.right - 2 * pad;
            const left = e.left - 2 * pad;
            const side = Math.max(right, left);
            if (side >= MIN_DOCKED_WIDTH) {
                const width = Math.min(p.width, side);
                place({ width: `${width}px`, bottom: `${pad}px`, ...(right >= left ? { right: `${pad}px` } : { left: `${pad}px` }) });
                return;
            }

            // Otherwise the viewport corner covering the least of it.
            const corners = [
                { right: pad, bottom: pad },
                { left: pad, bottom: pad },
                { right: pad, top: pad },
                { left: pad, top: pad },
            ].map((c) => {
                const x = 'left' in c ? c.left : vw - c.right - p.width;
                const y = 'top' in c ? c.top : vh - c.bottom - p.height;
                return { c, area: overlapArea({ left: x, right: x + p.width, top: y, bottom: y + p.height }, e) };
            });
            const best = corners.reduce((a, b) => (b.area < a.area ? b : a)).c;
            place(Object.fromEntries(Object.entries(best).map(([k, v]) => [k, `${v}px`])));
        },

        destroyDriver() {
            if (rt.driver) {
                const d = rt.driver;
                rt.driver = null;
                d.destroy();
            }
        },
    };
}
