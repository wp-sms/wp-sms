import { defineConfig } from 'vite'
import path from 'path'

/**
 * Vite config for building admin + frontend JS bundles as IIFE.
 * Replaces webpack.scripts.config.js.
 *
 * Usage: ENTRY=admin vite build --config vite.config.entries.mjs
 *        ENTRY=frontend vite build --config vite.config.entries.mjs
 */
const entry = process.env.ENTRY || 'admin'

const entries = {
  admin: path.resolve(__dirname, 'resources/entries/admin-entry.js'),
  frontend: path.resolve(__dirname, 'resources/entries/frontend-entry.js'),
}

export default defineConfig({
  publicDir: false,
  build: {
    outDir: path.resolve(__dirname, 'public/js'),
    emptyOutDir: false,
    rollupOptions: {
      input: entries[entry],
      output: {
        format: 'iife',
        entryFileNames: `${entry}.min.js`,
        assetFileNames: '[name][extname]',
      },
    },
    minify: 'terser',
    terserOptions: {
      format: { comments: false },
    },
  },
})
