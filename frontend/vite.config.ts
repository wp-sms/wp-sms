import { defineConfig } from 'vite';
import { resolve } from 'node:path';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [react(), tailwindcss()],
    css: {
        modules: {
            localsConvention: 'camelCase',
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, './src'),
            '@components': resolve(__dirname, './src/components'),
            '@utils': resolve(__dirname, './src/utils'),
            '@types': resolve(__dirname, './src/types'),
            '@assets': resolve(__dirname, './src/assets'),
            '@layouts': resolve(__dirname, './src/layouts'),
            '@core': resolve(__dirname, './src/core'),
            '@hooks': resolve(__dirname, './src/hooks'),
            '@models': resolve(__dirname, './src/models'),
            '@pages': resolve(__dirname, './src/pages'),
            '@routes': resolve(__dirname, './src/routes'),
            '@documents': resolve(__dirname, './src/documents'),
        },
    },
    build: {
        manifest: true,
        target: 'es2022',
        outDir: './build',
        rollupOptions: {
            input: {
                admin: resolve(__dirname, 'src/pages/dashboard/index.tsx'),
                settings: resolve(__dirname, 'src/pages/settings/index.tsx'),
                settingsPermissions: resolve(__dirname, 'src/pages/settings/permissions.tsx'),
                // Add blocks
                sendSmsBlock: resolve(__dirname, 'src/blocks/send-sms/index.ts'),
                // subscribeBlock: resolve(__dirname, 'src/blocks/subscribe/index.ts'),

                // css
                globals: resolve(__dirname, 'src/globals.css'),
            },
            output: {
                // entryFileNames: '[name]-[hash].js',
                // chunkFileNames: 'chunks/[name]-[hash].js',
                // assetFileNames: 'assets/[name]-[hash].[ext]',

                entryFileNames: '[name].js',
                chunkFileNames: 'chunks/[name].js',
                assetFileNames: 'assets/[name].[ext]',
            },
        },

        emptyOutDir: true,
    },
    server: {
        port: 5173,
        cors: true,
    },
});
