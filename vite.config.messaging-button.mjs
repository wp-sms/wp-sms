import { defineConfig } from 'vite';
import { createAuthConfig } from './vite.config.auth-base.mjs';

export default defineConfig(createAuthConfig({
    entry: 'resources/preact/src/messaging-button/main-messaging-button.jsx',
    name: 'wsmsMessagingButton',
    fileName: 'messaging-button.js',
    emptyOutDir: false,
    assetFileNames: 'messaging-button[extname]',
    tailwind: true,
}));
