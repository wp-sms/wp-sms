import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { tanstackRouter } from '@tanstack/router-plugin/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)

// This config is used ONLY for extracting i18n strings
// It builds unminified code to public/i18n-temp/
export default defineConfig({
  base: './',
  root: resolve(__dirname, 'resources/react'),
  publicDir: false,
  plugins: [
    tanstackRouter({
      target: 'react',
      autoCodeSplitting: true,
    }),
    react(),
  ],
  build: {
    outDir: resolve(__dirname, 'public/i18n-temp'),
    emptyOutDir: true,
    minify: false, // NO minification - keep __ functions readable
    sourcemap: false,
    rollupOptions: {
      input: {
        main: resolve(__dirname, 'resources/react/main.tsx'),
      },
      output: {
        format: 'es',
        preserveModules: false,
      },
    },
  },
  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/react'),
      '@components': resolve(__dirname, 'resources/react/components'),
      '@hooks': resolve(__dirname, 'resources/react/hooks'),
      '@lib': resolve(__dirname, 'resources/react/lib'),
      '@services': resolve(__dirname, 'resources/react/services'),
      '@types': resolve(__dirname, 'resources/react/types'),
      '@providers': resolve(__dirname, 'resources/react/providers'),
      '@context': resolve(__dirname, 'resources/react/context'),
      '@utils': resolve(__dirname, 'resources/react/utils'),
      '@routes': resolve(__dirname, 'resources/react/routes'),
    },
    dedupe: ['react', 'react-dom'],
  },
  optimizeDeps: {
    include: ['react', 'react-dom'],
    exclude: ['@wordpress/element', '@wordpress/i18n'],
  },
})
