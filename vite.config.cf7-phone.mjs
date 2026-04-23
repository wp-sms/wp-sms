import { defineConfig } from 'vite';
import { resolve } from 'path';

export default defineConfig({
    publicDir: false,
    build: {
        lib: {
            entry: resolve(__dirname, 'resources/entries/cf7-phone-entry.js'),
            formats: ['iife'],
            name: 'wsmsCf7Phone',
            fileName: () => 'cf7-phone.js',
        },
        outDir: resolve(__dirname, 'public/js'),
        emptyOutDir: false,
        minify: 'terser',
        sourcemap: false,
        cssCodeSplit: false,
        rolldownOptions: {
            external: ['lite-phone-input/vanilla', 'lite-phone-input/styles', '@wordpress/api-fetch'],
            output: {
                assetFileNames: 'cf7-phone[extname]',
                globals: {
                    '@wordpress/api-fetch': 'wp.apiFetch',
                },
            },
        },
    },
});
