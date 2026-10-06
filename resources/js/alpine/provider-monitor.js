/** Salamat faghat az DB/hot-cache read mishe; hich upstream-i call nemishe. */
export default ({ route, health }) => ({
    health,
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
