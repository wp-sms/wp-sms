import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import postcssImportantPlugin from './postcss-important-plugin';
import { resolve } from 'path';

export default defineConfig({
    plugins: [react(), tailwindcss()],
    css: {
        postcss: {
            plugins: [postcssImportantPlugin()],
        },
    },
    root: 'resources/react',
    base: './',
    build: {
        outDir: resolve(__dirname, 'public/app'),
        emptyOutDir: true,
        rolldownOptions: {
            input: resolve(__dirname, 'resources/react/src/main.tsx'),
            output: {
                entryFileNames: 'main.js',
                assetFileNames: 'main[extname]',
            },
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
