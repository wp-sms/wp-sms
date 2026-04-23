import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import postcssImportantPlugin from './postcss-important-plugin';
import { resolve } from 'path';

export default defineConfig({
    plugins: [react(), tailwindcss()],
    define: {
        'process.env.NODE_ENV': JSON.stringify('production'),
    },
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
        lib: {
            entry: resolve(__dirname, 'resources/react/src/main.tsx'),
            formats: ['iife'],
            name: 'wsmsDashboard',
            fileName: () => 'main.js',
        },
        minify: 'terser',
        terserOptions: {
            mangle: { reserved: ['__', '_x', '_n', '_nx', 'sprintf'] },
        },
        cssCodeSplit: false,
        rolldownOptions: {
            external: ['@wordpress/i18n', '@wordpress/api-fetch'],
            output: {
                assetFileNames: 'main[extname]',
                globals: {
                    '@wordpress/i18n': 'wp.i18n',
                    '@wordpress/api-fetch': 'wp.apiFetch',
                },
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
