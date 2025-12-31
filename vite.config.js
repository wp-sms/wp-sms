import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'
import tailwindcss from 'tailwindcss'
import autoprefixer from 'autoprefixer'

export default defineConfig(({ command }) => ({
  plugins: [react()],
  root: 'assets/src/settings',
  css: {
    postcss: {
      plugins: [
        tailwindcss(path.resolve(__dirname, 'tailwind.config.js')),
        autoprefixer(),
      ],
    },
  },
  base: command === 'serve' ? '/' : '/wp-content/plugins/wp-sms/assets/dist/settings/',
  build: {
    outDir: path.resolve(__dirname, 'assets/dist/settings'),
    emptyDirBeforeWrite: true,
    manifest: true,
    rollupOptions: {
      input: path.resolve(__dirname, 'assets/src/settings/index.html'),
      output: {
        entryFileNames: 'assets/settings.js',
        chunkFileNames: 'assets/settings-[hash].js',
        assetFileNames: 'assets/settings[extname]'
      }
    }
  },
  server: {
    port: 3000,
    cors: true,
    origin: 'http://localhost:3000',
    host: true,
    strictPort: true,
    hmr: {
      host: 'localhost',
      port: 3000,
      protocol: 'ws'
    }
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV || 'development')
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'assets/src/settings')
    }
  }
}))
