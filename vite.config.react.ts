import { resolve, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { cpSync, existsSync, mkdirSync } from 'fs'

import tailwindcss from '@tailwindcss/vite'
import { tanstackRouter } from '@tanstack/router-plugin/vite'
import react from '@vitejs/plugin-react'
import { defineConfig } from 'vite'

// @ts-expect-error - PostCSS plugin without types
import postcssImportantPlugin from './postcss-important-plugin.js'

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)

// Custom plugin to copy images
function copyImages() {
  return {
    name: 'copy-images',
    writeBundle() {
      const imagesSourceDir = resolve(__dirname, 'resources/react/images')
      const imagesOutputDir = resolve(__dirname, 'public/react/images')

      try {
        if (existsSync(imagesSourceDir)) {
          mkdirSync(imagesOutputDir, { recursive: true })
          cpSync(imagesSourceDir, imagesOutputDir, { recursive: true })
          console.log('✓ Copied React images')
        }
      } catch (e) {
        console.error('Failed to copy images:', e.message)
      }
    },
  }
}

// https://vite.dev/config/
export default defineConfig(({ mode }) => {
  const reactRoot = resolve(__dirname, 'resources/react')

  return {
    base: './',
    root: reactRoot,
    publicDir: false,
    plugins: [
      tanstackRouter({
        target: 'react',
        autoCodeSplitting: true,
      }),
      react(),
      tailwindcss(),
      copyImages(),
    ],
    css: {
      modules: {
        localsConvention: 'camelCase',
      },
      postcss: {
        plugins: [postcssImportantPlugin()],
      },
    },
    build: {
      outDir: resolve(__dirname, 'public/react'),
      emptyOutDir: true,
      manifest: true,
      minify: mode === 'production',
      sourcemap: mode === 'development' ? true : false,
      rollupOptions: {
        input: {
          main: resolve(reactRoot, 'main.tsx'),
        },
      },
    },
    resolve: {
      alias: {
        '@': resolve(reactRoot),
        '@components': resolve(reactRoot, 'components'),
        '@hooks': resolve(reactRoot, 'hooks'),
        '@lib': resolve(reactRoot, 'lib'),
        '@services': resolve(reactRoot, 'services'),
        '@types': resolve(reactRoot, 'types'),
        '@providers': resolve(reactRoot, 'providers'),
        '@context': resolve(reactRoot, 'context'),
        '@utils': resolve(reactRoot, 'utils'),
        '@routes': resolve(reactRoot, 'routes'),
      },
      dedupe: ['react', 'react-dom'],
    },
    optimizeDeps: {
      include: ['react', 'react-dom', 'lucide-react'],
      exclude: ['@wordpress/element'],
      force: false,
    },
    server: {
      port: 5173,
      cors: true,
    },
  }
})