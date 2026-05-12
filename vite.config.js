import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        // server.host=0.0.0.0 binds inside the container; hmr.host tells
        // Vite which URL to inject in the <script> tags it serves to the
        // browser (the browser hits localhost:5173 via the docker port map).
        hmr: { host: 'localhost' },
    },
});
