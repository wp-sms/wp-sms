import preact from '@preact/preset-vite';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'path';

/**
 * Shared Vite config factory for auth builds (full-page and popup).
 */
export function createAuthConfig({ entry, name, fileName, emptyOutDir = true, assetFileNames = 'style[extname]', tailwind = true }) {
    const __dirname = resolve(import.meta.dirname);

    return {
        plugins: tailwind ? [preact(), tailwindcss()] : [preact()],
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
            rolldownOptions: {
                external: ['preact-render-to-string'],
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
