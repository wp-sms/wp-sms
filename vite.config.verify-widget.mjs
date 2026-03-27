import { defineConfig } from 'vite';
import { createAuthConfig } from './vite.config.auth-base.mjs';

export default defineConfig(createAuthConfig({
    entry: 'resources/preact/src/main-verify-widget.jsx',
    name: 'wsmsVerifyWidget',
    fileName: 'verify-widget.js',
    emptyOutDir: false,
    assetFileNames: 'verify-widget-style[extname]',
}));
