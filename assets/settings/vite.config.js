import {defineConfig} from 'vite';
import react from '@vitejs/plugin-react';
import path from 'path';

export default defineConfig({
    root: path.resolve(__dirname),
    build: {
        outDir: path.resolve(__dirname, '../../build/settings'),
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: {
                main: path.resolve(__dirname, 'src/main.jsx'),
            },
        },
    },
    plugins: [react()],
});
