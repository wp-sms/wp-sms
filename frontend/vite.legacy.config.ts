import { defineConfig } from 'vite';
import { resolve } from 'node:path';
import copy from 'rollup-plugin-copy';

export default defineConfig({
    build: {
        target: 'es2015',
        outDir: './build/legacy',
        manifest: true,
        emptyOutDir: true,
        copyPublicDir: false,
        rollupOptions: {
            input: {
                //js
                admin: resolve(__dirname, 'legacy/js/admin.js'),
                frontend: resolve(__dirname, 'legacy/scripts/blocks.js'),

                //css
                'admin-bar-styles': resolve(__dirname, 'legacy/css/admin-bar.css'),
                'chatbox-styles': resolve(__dirname, 'legacy/css/chatbox.min.css'),
                'flatpickr-styles': resolve(__dirname, 'legacy/css/flatpickr.min.css'),
                'intlTelInput-styles': resolve(__dirname, 'legacy/css/intlTelInput.css'),
                'rtl-styles': resolve(__dirname, 'legacy/css/rtl.css'),
                'select2-styles': resolve(__dirname, 'legacy/css/select2.min.css'),
                'subscribe-styles': resolve(__dirname, 'legacy/css/subscribe.css'),
                'system-info-styles': resolve(__dirname, 'legacy/css/system-info.css'),
                'tooltipster-styles': resolve(__dirname, 'legacy/css/tooltipster.bundle.css'),
                'admin-styles': resolve(__dirname, 'legacy/admin/admin.scss'),
                'front-styles': resolve(__dirname, 'legacy/scss/front-styles.scss'),
                'mail-styles': resolve(__dirname, 'legacy/scss/mail.scss'),
            },
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: 'chunks/[name].js',
                assetFileNames: 'assets/[name].[ext]',
            },
            plugins: [
                copy({
                    targets: [
                        {
                            src: 'legacy/static/select2.js',
                            dest: 'build/legacy',
                        },
                        {
                            src: 'legacy/static/flatpickr.js',
                            dest: 'build/legacy',
                        },
                        {
                            src: 'legacy/static/tooltipster-bundle.js',
                            dest: 'build/legacy',
                        },
                        {
                            src: 'legacy/static/chatbox.min.js',
                            dest: 'build/legacy',
                        },
                        {
                            src: 'legacy/static/jquery-repeater.js',
                            dest: 'build/legacy',
                        },
                        {
                            src: 'legacy/static/jquery-word-and-character-counter.min.js',
                            dest: 'build/legacy',
                        },
                    ],
                    hook: 'writeBundle',
                }),
            ],
        },
    },
});
