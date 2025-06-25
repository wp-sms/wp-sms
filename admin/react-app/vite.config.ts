import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';
import * as path from 'path';

export default defineConfig({
    plugins: [react()],
    base: './',
    resolve: {
        alias: {
            '@': path.resolve(__dirname, 'src'),
            '@wordpress/i18n': path.resolve(__dirname, 'src/shims/wordpress-i18n.ts'),
        },
    },
    build: {
        outDir: '../build',
        emptyOutDir: true,
        manifest: true,
        watch: process.env.WATCH === 'true' ? {} : null,
        rollupOptions: {
            input: {
                settings: path.resolve(__dirname, 'src/pages/settings/index.tsx'),
            },
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: '[name].js',
                assetFileNames: '[name].[ext]',
            },
        },
    },
    optimizeDeps: {
        include: ['react', 'react-dom'],
    },
});