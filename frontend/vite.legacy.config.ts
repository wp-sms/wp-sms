import { defineConfig } from 'vite';
import { resolve } from 'node:path';

export default defineConfig({
    build: {
        target: 'es2015', // For better browser compatibility
        outDir: './build/legacy',
        manifest: true,
        emptyOutDir: true,
        copyPublicDir: false,
        rollupOptions: {
            input: {
                // Admin scripts
                'admin-quick-reply': resolve(__dirname, 'legacy/scripts/quick-reply.js'),
                'admin-import-subscriber': resolve(__dirname, 'legacy/scripts/import-subscriber.js'),
                'admin-main': resolve(__dirname, 'legacy/scripts/admin.js'),
                'admin-export': resolve(__dirname, 'legacy/scripts/admin-export.js'),
                'admin-send-sms': resolve(__dirname, 'legacy/scripts/admin-send-sms.js'),
                'admin-edit-subscriber': resolve(__dirname, 'legacy/scripts/edit-subscriber.js'),
                'admin-edit-group': resolve(__dirname, 'legacy/scripts/edit-group.js'),
                'admin-privacy-data': resolve(__dirname, 'legacy/scripts/admin-privacy-data.js'),
                'admin-order-view': resolve(__dirname, 'legacy/scripts/admin-order-view.js'),
                'admin-dashboard-stats': resolve(__dirname, 'legacy/scripts/admin-dashboard-stats-widget.js'),
                'admin-chart': resolve(__dirname, 'legacy/scripts/chart.min.js'),
                // Frontend scripts
                'frontend-blocks': resolve(__dirname, 'legacy/scripts/blocks.js'),
                'admin-css': resolve(__dirname, 'legacy/admin/admin.scss'),
                'front-styles-css': resolve(__dirname, 'legacy/scss/front-styles.scss'),
                'mail-css': resolve(__dirname, 'legacy/scss/mail.scss'),
            },
            output: {
                // entryFileNames: '[name].min.js',
                // format: 'es',
                // preserveModules: false,
                // dir: './build/legacy',

                entryFileNames: '[name].js',
                chunkFileNames: 'chunks/[name].js',
                assetFileNames: 'assets/[name].[ext]',
            },
        },
    },
});
