import Alpine from 'alpinejs';
import collapse from '@alpinejs/collapse';
import focus from '@alpinejs/focus';
import { panelRequest } from './lib/request';

Alpine.plugin(collapse);
Alpine.plugin(focus);

// Har file tu resources/js/alpine/ yek component-e Alpine-e: esm-e file (kebab-case) mishe
// esm-e component (camelCase). Registration-e dasti nadarim ta chand nafar hamzaman betoonan
// component ezafe konand bedoone dast zadan be in file.
const modules = import.meta.glob('./alpine/*.js', { eager: true });

for (const [path, module] of Object.entries(modules)) {
    const name = path
        .replace('./alpine/', '')
        .replace('.js', '')
        .replace(/-([a-z])/g, (_, c) => c.toUpperCase());

    if (typeof module.default === 'function') Alpine.data(name, module.default);
    if (typeof module.store === 'object') Alpine.store(name, module.store);
}

// Expression-haye Alpine tu Blade rooye window hal mishan; panelRequest bayad az unja dar dastres bashe.
window.panelRequest = panelRequest;
window.Alpine = Alpine;
Alpine.start();
