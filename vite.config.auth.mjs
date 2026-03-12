import { defineConfig } from 'vite';
import { createAuthConfig } from './vite.config.auth-base.mjs';

export default defineConfig(createAuthConfig({
    entry: 'resources/preact/src/main.jsx',
    name: 'wsmsAuthApp',
    fileName: 'app.js',
    emptyOutDir: true,
    assetFileNames: 'style[extname]',
}));
