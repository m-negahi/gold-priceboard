import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

// Nokte: plugin e `fonts` e laravel-vite (bunny) amdan hazf shod — font ro az CDN
// migire va ru host e ma CDN haye khareji ghabel e etminan nistan. Font ha local-and
// (`resources/fonts/`) va tu `app.css` ba @font-face ta'rif mishan.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        // ⚠️ 5373 (na 5173): 5173 dast e hg_website va livewire_learn ast.
        // `strictPort` ya'ni agar gereftar bud fail kone, na inke saket bere ru port e digar
        // — vagarna mapping e docker-compose be jayi vasl nemishe va HMR saket namikone.
        host: '0.0.0.0',
        port: 5373,
        strictPort: true,
        hmr: { host: 'localhost' },
        // Docker ru macOS event e filesystem ro be container nemirese → polling.
        watch: {
            usePolling: true,
            interval: 500,
            ignored: ['**/storage/framework/views/**', '**/vendor/**'],
        },
    },
});
