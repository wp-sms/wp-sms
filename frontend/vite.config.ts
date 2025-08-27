import { defineConfig } from 'vite';
import { resolve } from 'node:path';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
  plugins: [react(), tailwindcss()],
  css: {
    modules: {
      localsConvention: 'camelCase',
    },
  },

  resolve: {
    alias: {
      '@': resolve(__dirname, './src'),
      '@components': resolve(__dirname, './src/components'),
      '@utils': resolve(__dirname, './src/utils'),
      '@types': resolve(__dirname, './src/types'),
      '@assets': resolve(__dirname, './src/assets'),
      '@layouts': resolve(__dirname, './src/layouts'),
      '@core': resolve(__dirname, './src/core'),
      '@hooks': resolve(__dirname, './src/hooks'),
      '@models': resolve(__dirname, './src/models'),
      '@pages': resolve(__dirname, './src/pages'),
      '@routes': resolve(__dirname, './src/routes'),
      '@documents': resolve(__dirname, './src/documents'),
      '@stores': resolve(__dirname, './src/stores'),
    },
  },
  build: {
    sourcemap: false,
    reportCompressedSize: false,
    chunkSizeWarningLimit: 1000,
    manifest: true,
    target: 'es2022',
    outDir: './build',
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console: true,
        drop_debugger: true,
      },
    },
    rollupOptions: {
      treeshake: 'recommended',
      input: {
        settings: resolve(__dirname, 'src/pages/settings/index.tsx'),
        settingsDynamicPages: resolve(__dirname, 'src/pages/settings/dynamic-pages.tsx'),
        // Add blocks
        sendSmsBlock: resolve(__dirname, 'src/blocks/send-sms/index.ts'),
        // subscribeBlock: resolve(__dirname, 'src/blocks/subscribe/index.ts'),

        // css
        globals: resolve(__dirname, 'src/globals.css'),
      },
      output: {
        // entryFileNames: '[name]-[hash].js',
        // chunkFileNames: 'chunks/[name]-[hash].js',
        // assetFileNames: 'assets/[name]-[hash].[ext]',

        entryFileNames: '[name].js',
        chunkFileNames: 'chunks/[name].js',
        assetFileNames: 'assets/[name].[ext]',
        manualChunks(id) {
          if (id.includes('node_modules')) {
            return 'vendor';
          }
        },
        experimentalMinChunkSize: 1000,
      },
    },

    emptyOutDir: false,
  },
  server: {
    port: 5173,
    cors: true,
  },
  optimizeDeps: {
    include: ['react', 'react-dom', 'react-router-dom', 'react-hook-form', 'zustand', 'lucide-react'],
    exclude: ['@wordpress/element'],
    force: false,
  },
});
