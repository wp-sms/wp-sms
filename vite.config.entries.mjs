import { defineConfig } from 'vite';
import { resolve } from 'path';

const entry = process.env.ENTRY || 'admin';

export default defineConfig({
    build: {
        lib: {
            entry: resolve(__dirname, `resources/entries/${entry}-entry.js`),
            formats: ['iife'],
            name: `wsms_${entry}`,
            fileName: () => `${entry}.min.js`,
        },
        outDir: resolve(__dirname, 'public/js'),
        emptyOutDir: false,
        minify: 'terser',
        sourcemap: false,
    },
});
