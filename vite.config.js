import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import { resolve } from 'path';

export default defineConfig({
    plugins: [react()],
    root: 'resources/react',
    base: './',
    build: {
        outDir: resolve(__dirname, 'public/app'),
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: resolve(__dirname, 'resources/react/src/main.jsx'),
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/react/src'),
        },
    },
    server: {
        port: 5177,
        cors: true,
        strictPort: true,
        hmr: {
            host: 'localhost',
        },
    },
});
