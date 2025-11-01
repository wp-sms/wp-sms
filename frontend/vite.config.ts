import { resolve } from 'node:path'

import tailwindcss from '@tailwindcss/vite'
import { tanstackRouter } from '@tanstack/router-plugin/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// @ts-expect-error - PostCSS plugin without types
import postcssImportantPlugin from './postcss-important-plugin.js'

export default defineConfig({
  base: './',
  plugins: [
    tanstackRouter({
      target: 'react',
      autoCodeSplitting: true,
    }),
    react(),
    tailwindcss(),
  ],
  css: {
    modules: {
      localsConvention: 'camelCase',
    },
    postcss: {
      plugins: [postcssImportantPlugin()],
    },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, './src'),
      '@components': resolve(__dirname, './src/components'),
      '@hooks': resolve(__dirname, './src/hooks'),
      '@lib': resolve(__dirname, './src/lib'),
      '@services': resolve(__dirname, './src/services'),
      '@types': resolve(__dirname, './src/types'),
      '@stores': resolve(__dirname, './src/stores'),
      '@pages': resolve(__dirname, './src/pages'),
      '@routes': resolve(__dirname, './src/routes'),
    },
  },
  build: {
    outDir: './build',
    manifest: true,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'src/main.tsx'),
      },
    },
  },
  server: {
    port: 5173,
    cors: true,
  },
  optimizeDeps: {
    include: ['react', 'react-dom', 'lucide-react'],
    exclude: ['@wordpress/element'],
    force: false,
  },
})
