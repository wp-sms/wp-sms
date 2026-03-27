import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    publicDir: false,
    build: {
        lib: {
            entry: resolve(__dirname, 'resources/entries/vendor-entry.js'),
            formats: ['iife'],
            name: 'WsmsVendor',
            fileName: () => 'vendor.js',
        },
        outDir: resolve(__dirname, 'public/auth'),
        emptyOutDir: false,
        minify: 'terser',
        sourcemap: false,
        cssCodeSplit: false,
        rolldownOptions: {
            output: { assetFileNames: 'vendor[extname]' },
        },
    },
    resolve: {
        alias: {
            'react': resolve(__dirname, 'node_modules/preact/compat'),
            'react-dom': resolve(__dirname, 'node_modules/preact/compat'),
        },
    },
});
