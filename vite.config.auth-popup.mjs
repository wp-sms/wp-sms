import { defineConfig } from 'vite';
import { createAuthConfig } from './vite.config.auth-base.mjs';

export default defineConfig(createAuthConfig({
    entry: 'resources/preact/src/main-popup.jsx',
    name: 'wsmsAuthPopup',
    fileName: 'popup.js',
    emptyOutDir: false,
    assetFileNames: 'popup[extname]',
}));
