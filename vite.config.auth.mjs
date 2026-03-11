import { defineConfig } from 'vite';
import preact from '@preact/preset-vite';
import { resolve } from 'path';

export default defineConfig({
    plugins: [preact()],
    publicDir: false,
    build: {
        lib: {
            entry: resolve(__dirname, 'resources/preact/src/main.jsx'),
            formats: ['iife'],
            name: 'wsmsAuthApp',
            fileName: () => 'app.js',
        },
        outDir: resolve(__dirname, 'public/auth'),
        emptyOutDir: true,
        minify: 'terser',
        sourcemap: false,
        cssCodeSplit: false,
        rollupOptions: {
            output: {
                assetFileNames: 'style[extname]',
            },
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/preact/src'),
        },
    },
});
