import { defineConfig } from 'vite';
import { createAuthConfig } from './vite.config.auth-base.mjs';

export default defineConfig(createAuthConfig({
    entry: 'resources/preact/src/user-button/main-user-button.jsx',
    name: 'wsmsUserButton',
    fileName: 'user-button.js',
    emptyOutDir: false,
    assetFileNames: 'user-button[extname]',
}));
