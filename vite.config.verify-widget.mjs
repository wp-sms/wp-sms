import { defineConfig } from 'vite';
import preact from '@preact/preset-vite';
import { resolve } from 'path';

const __dirname = resolve(import.meta.dirname);

export default defineConfig({
    plugins: [preact()],
    publicDir: false,
    build: {
        lib: {
            entry: resolve(__dirname, 'resources/preact/src/main-verify-widget.jsx'),
            formats: ['iife'],
            name: 'wsmsVerifyWidget',
            fileName: () => 'verify-widget.js',
        },
        outDir: resolve(__dirname, 'public/auth'),
        emptyOutDir: false,
        minify: 'terser',
        sourcemap: false,
        cssCodeSplit: false,
        rollupOptions: {
            output: { assetFileNames: 'verify-widget-style[extname]' },
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/preact/src'),
        },
    },
});
