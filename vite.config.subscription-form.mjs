import { defineConfig } from 'vite';
import { createAuthConfig } from './vite.config.auth-base.mjs';

export default defineConfig(createAuthConfig({
    entry: 'resources/preact/src/subscription-form/main-subscription-form.jsx',
    name: 'wsmsSubscriptionForm',
    fileName: 'subscription-form.js',
    emptyOutDir: false,
    assetFileNames: 'subscription-form[extname]',
}));
