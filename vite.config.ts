import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';

export default defineConfig({
    plugins: [
        laravel({
            // Both are entry points because the Blade shell references each
            // one directly via @vite().
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'resources/js'),
        },
    },

    build: {
        rollupOptions: {
            output: {
                /*
                 * The libraries every page needs are split out from the pages
                 * themselves, so navigating between screens downloads only the
                 * new screen — React and Inertia are already cached, and stay
                 * cached across deploys that do not change them.
                 */
                manualChunks(id: string) {
                    if (!id.includes('node_modules')) {
                        return undefined;
                    }

                    // Windows hands these back with backslashes; splitting on
                    // one is cheaper than escaping it into a regex twice.
                    const file = id.split(String.fromCharCode(92)).join('/');

                    if (
                        file.includes('/node_modules/react/')
                        || file.includes('/node_modules/react-dom/')
                        || file.includes('/node_modules/scheduler/')
                    ) {
                        return 'vendor-react';
                    }

                    if (file.includes('/node_modules/@inertiajs/')) {
                        return 'vendor-inertia';
                    }

                    return undefined;
                },
            },
        },
    },
});
