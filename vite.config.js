import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'
import path from 'path'
import tailwindcss from 'tailwindcss'
import autoprefixer from 'autoprefixer'
import compression from 'vite-plugin-compression'
import { visualizer } from 'rollup-plugin-visualizer'

export default defineConfig(({ command, mode }) => ({
  plugins: [
    react(),
    // Generate gzip compressed assets for production
    mode === 'production' && compression({
      algorithm: 'gzip',
      ext: '.gz',
      threshold: 1024, // Only compress files > 1KB
    }),
    // Bundle analyzer - generates bundle-analysis.html
    mode === 'production' && visualizer({
      filename: 'bundle-analysis.html',
      open: false,
      gzipSize: true,
      brotliSize: true,
    }),
  ].filter(Boolean),
  root: 'assets/src/dashboard',
  css: {
    postcss: {
      plugins: [
        tailwindcss(path.resolve(__dirname, 'tailwind.config.js')),
        autoprefixer(),
      ],
    },
  },
  base: command === 'serve' ? '/' : '/wp-content/plugins/wp-sms/assets/dist/dashboard/',
  build: {
    outDir: path.resolve(__dirname, 'assets/dist/dashboard'),
    emptyDirBeforeWrite: true,
    manifest: true,
    // Use terser for better minification
    minify: 'terser',
    terserOptions: {
      compress: {
        drop_console: true,
        drop_debugger: true,
      },
      output: {
        comments: false,
      },
    },
    rollupOptions: {
      input: path.resolve(__dirname, 'assets/src/dashboard/index.html'),
      output: {
        entryFileNames: 'assets/dashboard.js',
        chunkFileNames: 'assets/dashboard-[hash].js',
        assetFileNames: 'assets/dashboard[extname]',
        // Manual chunks to reduce chunk count and optimize caching
        manualChunks: {
          // Core React runtime - changes rarely
          vendor: ['react', 'react-dom'],
          // Radix UI primitives - shared across components
          radix: [
            '@radix-ui/react-checkbox',
            '@radix-ui/react-dialog',
            '@radix-ui/react-dropdown-menu',
            '@radix-ui/react-label',
            '@radix-ui/react-scroll-area',
            '@radix-ui/react-select',
            '@radix-ui/react-separator',
            '@radix-ui/react-slot',
            '@radix-ui/react-switch',
            '@radix-ui/react-tabs',
            '@radix-ui/react-toast',
          ],
          // Icons - can be cached separately
          icons: ['lucide-react'],
        },
      },
    },
  },
  server: {
    port: 5177,
    cors: true,
    origin: 'http://localhost:5177',
    host: '0.0.0.0',
    strictPort: true,
    hmr: {
      host: 'localhost',
      port: 5177,
      protocol: 'ws',
      clientPort: 5177
    },
    headers: {
      'Access-Control-Allow-Origin': '*',
    }
  },
  define: {
    'process.env.NODE_ENV': JSON.stringify(process.env.NODE_ENV || 'development')
  },
  optimizeDeps: {
    include: ['react', 'react-dom', 'react/jsx-runtime'],
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'assets/src/dashboard'),
      // Ensure single React instance (prevents "useState is null" errors)
      'react': path.resolve(__dirname, 'node_modules/react'),
      'react-dom': path.resolve(__dirname, 'node_modules/react-dom'),
    },
    // Dedupe React to prevent multiple instances from dependencies
    dedupe: ['react', 'react-dom']
  }
}))
