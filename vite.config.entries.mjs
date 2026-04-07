import { defineConfig } from 'vite';
import { resolve } from 'path';

const entry = process.env.ENTRY || 'admin';
const entryFile = entry === 'mounter'
    ? 'verify-widget-mounter.js'
    : `${entry}-entry.js`;

export default defineConfig({
    publicDir: false,
    build: {
        lib: {
            entry: resolve(__dirname, `resources/entries/${entryFile}`),
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
