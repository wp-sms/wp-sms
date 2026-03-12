import preact from '@preact/preset-vite';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'path';

/**
 * Shared Vite config factory for auth builds (full-page and popup).
 */
export function createAuthConfig({ entry, name, fileName, emptyOutDir = true, assetFileNames = 'style[extname]' }) {
    const __dirname = resolve(import.meta.dirname);

    return {
        plugins: [preact(), tailwindcss()],
        publicDir: false,
        build: {
            lib: {
                entry: resolve(__dirname, entry),
                formats: ['iife'],
                name,
                fileName: () => fileName,
            },
            outDir: resolve(__dirname, 'public/auth'),
            emptyOutDir,
            minify: 'terser',
            sourcemap: false,
            cssCodeSplit: false,
            rollupOptions: {
                output: { assetFileNames },
            },
        },
        resolve: {
            alias: {
                '@': resolve(__dirname, 'resources/preact/src'),
            },
        },
    };
}
