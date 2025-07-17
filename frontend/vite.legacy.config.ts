import { defineConfig } from 'vite';
import { resolve } from 'node:path';
import { readdirSync } from 'node:fs';

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
                'quick-reply': resolve(__dirname, 'legacy/scripts/quick-reply.js'),
                'import-subscriber': resolve(__dirname, 'legacy/scripts/import-subscriber.js'),
                'edit-subscriber': resolve(__dirname, 'legacy/scripts/edit-subscriber.js'),
                'edit-group': resolve(__dirname, 'legacy/scripts/edit-group.js'),
                chatbox: resolve(__dirname, 'legacy/js/chatbox.min.js'),
                'editor-blocks': resolve(__dirname, 'legacy/js/editor.blocks.js'),
                flatpickr: resolve(__dirname, 'legacy/js/flatpickr.min.js'),
                frontend: resolve(__dirname, 'legacy/js/frontend.min.js'),
                'jquery-repeater': resolve(__dirname, 'legacy/js/jquery.repeater.min.js'),
                'jquery-word-and-character-counter.min': resolve(
                    __dirname,
                    'legacy/js/jquery.word-and-character-counter.min.js'
                ),
                'tooltipster-bundle': resolve(__dirname, 'legacy/js/tooltipster.bundle.js'),
                select2: resolve(__dirname, 'legacy/js/select2.min.js'),
                'intel-script': resolve(__dirname, 'legacy/js/intel/intel-script.js'),
                intlTelInput: resolve(__dirname, 'legacy/js/intel/intlTelInput.min.js'),
                utils: resolve(__dirname, 'legacy/js/intel/utils.js'),
                chart: resolve(__dirname, 'legacy/scripts/chart.min.js'),
                admin: resolve(__dirname, 'legacy/scripts/admin.js'),
                'admin-export': resolve(__dirname, 'legacy/scripts/admin-export.js'),
                'admin-send-sms': resolve(__dirname, 'legacy/scripts/admin-send-sms.js'),
                'admin-privacy-data': resolve(__dirname, 'legacy/scripts/admin-privacy-data.js'),
                'admin-order-view': resolve(__dirname, 'legacy/scripts/admin-order-view.js'),
                'admin-dashboard-stats-widget': resolve(__dirname, 'legacy/scripts/admin-dashboard-stats-widget.js'),
                // Frontend scripts
                'frontend-blocks': resolve(__dirname, 'legacy/scripts/blocks.js'),

                //css folder
                'admin-bar': resolve(__dirname, 'legacy/css/admin-bar.css'),
                'chatbox-styles': resolve(__dirname, 'legacy/css/chatbox.min.css'),
                'flatpickr-styles': resolve(__dirname, 'legacy/css/flatpickr.min.css'),
                'intlTelInput-styles': resolve(__dirname, 'legacy/css/intlTelInput.css'),
                rtl: resolve(__dirname, 'legacy/css/rtl.css'),
                'select2-styles': resolve(__dirname, 'legacy/css/select2.min.css'),
                subscribe: resolve(__dirname, 'legacy/css/subscribe.css'),
                'system-info': resolve(__dirname, 'legacy/css/system-info.css'),
                tooltipster: resolve(__dirname, 'legacy/css/tooltipster.bundle.css'),
                // rest of css files
                'admin-style': resolve(__dirname, 'legacy/admin/admin.scss'),
                'front-styles': resolve(__dirname, 'legacy/scss/front-styles.scss'),
                mail: resolve(__dirname, 'legacy/scss/mail.scss'),
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
