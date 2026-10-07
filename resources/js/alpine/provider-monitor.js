/**
 * Salamat faghat az DB/hot-cache read mishe; hich upstream-i call nemishe.
 *
 * `active` + `toggleActive`: roshan/khamoosh e provider bedun e reload
 * (darkhast e sahebe proje، 2026-10-07) — hamun olgu ye AJAX e safhe ye
 * olaviat ha (`panelRequest`؛ toast ro khodesh neshun mide).
 */
export default ({ route, health, active = {} }) => ({
    health,
    active,
    busyKey: null,
    delayed: false,
    timer: null,
    controller: null,
    init() {
        this.timer = setInterval(() => this.refresh(), 15000);
    },
    destroy() {
        clearInterval(this.timer);
        this.controller?.abort();
    },
    tone(key) {
        return {
            healthy: 'bg-ok-soft text-ok',
            degraded: 'bg-warn-soft text-warn',
            down: 'bg-danger-soft text-danger',
        }[this.health[key]?.stateValue] || 'bg-surface-3 text-muted';
    },
    async toggleActive(key, url) {
        if (this.busyKey) return;
        this.busyKey = key;
        try {
            const response = await window.panelRequest(url, { body: { is_active: this.active[key] ? 0 : 1 } });

            if (response.ok) this.active[key] = Boolean(response.data.is_active);
        } finally {
            this.busyKey = null;
        }
    },
    async refresh() {
        if (this.controller || document.hidden) return;
        this.controller = new AbortController();
        try {
            const response = await window.panelRequest(route, { method: 'GET', toast: false, signal: this.controller.signal });
            this.delayed = !response.ok;
            if (response.ok && response.data.health) this.health = response.data.health;
        } catch (error) {
            if (error?.name !== 'AbortError') this.delayed = true;
        } finally {
            this.controller = null;
        }
    },
});
