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
                external: [
                    'preact-render-to-string',
                    'preact',
                    'preact/hooks',
                    'preact/compat',
                    '@preact/signals',
                    'input-otp',
                    'lite-phone-input/preact',
                    'lite-phone-input/vanilla',
                ],
                output: {
                    assetFileNames,
                    globals: {
                        'preact': 'WsmsVendor.preact',
                        'preact/hooks': 'WsmsVendor.preactHooks',
                        'preact/compat': 'WsmsVendor.preactCompat',
                        '@preact/signals': 'WsmsVendor.signals',
                        'input-otp': 'WsmsVendor.inputOtp',
                    },
                },
            },
        },
        resolve: {
            alias: {
                '@': resolve(__dirname, 'resources/preact/src'),
            },
        },
    };
}
