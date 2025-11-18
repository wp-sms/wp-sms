import { defineConfig } from 'vite'
import { resolve } from 'path'
import { readFileSync, writeFileSync, mkdirSync, rmSync, readdirSync, statSync, cpSync, existsSync } from 'fs'
import { join } from 'path'
import { globSync } from 'glob'

// Custom plugin to wrap JS in jQuery ready
function jQueryReadyWrapper() {
  return {
    name: 'jquery-ready-wrapper',
    generateBundle(options, bundle) {
      for (const fileName in bundle) {
        if (bundle[fileName].type === 'chunk' && fileName.endsWith('.js')) {
          const chunk = bundle[fileName]
          if (fileName.includes('admin.min.js')) {
            chunk.code = `jQuery(document).ready(function ($) {${chunk.code}});`
          }
        }
      }
    },
  }
}

// Custom plugin to clean output directory
function cleanOutputDir() {
  return {
    name: 'clean-output-dir',
    buildStart() {
      const outDir = resolve(__dirname, 'public/legacy')

      try {
        // Remove everything in public/legacy directory
        const items = readdirSync(outDir)
        items.forEach((item) => {
          const itemPath = join(outDir, item)
          const stat = statSync(itemPath)
          if (stat.isDirectory()) {
            rmSync(itemPath, { recursive: true, force: true })
          } else {
            rmSync(itemPath, { force: true })
          }
        })
        console.log('✓ Cleaned output directory')
      } catch (e) {
        // Directory might not exist yet, that's ok
        if (e.code !== 'ENOENT') {
          console.warn('Warning during cleanup:', e.message)
        }
      }
    },
  }
}

// Custom plugin to copy static files
function copyStaticFiles() {
  return {
    name: 'copy-static-files',
    writeBundle() {
      const staticDir = resolve(__dirname, 'resources/legacy/static')
      const outputJsDir = resolve(__dirname, 'public/legacy/js')
      const outputCssDir = resolve(__dirname, 'public/legacy/css')
      const intelOutputDir = resolve(__dirname, 'public/legacy/js/intel')

      try {
        mkdirSync(outputJsDir, { recursive: true })
        mkdirSync(intelOutputDir, { recursive: true })
        mkdirSync(outputCssDir, { recursive: true })

        // Copy static JS files
        const staticFiles = [
          'select2.js',
          'flatpickr.js',
          'tooltipster-bundle.js',
          'chatbox.min.js',
          'jquery-repeater.js',
          'jquery-word-and-character-counter.min.js',
          'chart.min.js',
        ]

        staticFiles.forEach(file => {
          const sourcePath = join(staticDir, file)
          const destPath = join(outputJsDir, file)

          if (existsSync(sourcePath)) {
            cpSync(sourcePath, destPath)
          }
        })

        // Copy intl-tel-input library from node_modules
        const intlTelInputSrc = resolve(__dirname, 'node_modules/intl-tel-input/build/js/intlTelInput.min.js')
        const intlTelInputDest = join(intelOutputDir, 'intlTelInput.min.js')
        if (existsSync(intlTelInputSrc)) {
          cpSync(intlTelInputSrc, intlTelInputDest)
        }

        // Copy intl-tel-input CSS
        const intlTelInputCssSrc = resolve(__dirname, 'node_modules/intl-tel-input/build/css/intlTelInput.min.css')
        const intlTelInputCssDest = join(outputCssDir, 'intlTelInput.min.css')
        if (existsSync(intlTelInputCssSrc)) {
          cpSync(intlTelInputCssSrc, intlTelInputCssDest)
        }

        // Copy custom intel-script.js
        const intelScriptSrc = resolve(__dirname, 'resources/legacy/intel/intel-script.js')
        const intelScriptDest = join(intelOutputDir, 'intel-script.js')
        if (existsSync(intelScriptSrc)) {
          cpSync(intelScriptSrc, intelScriptDest)
        }

        console.log('✓ Copied static files and intl-tel-input')
      } catch (e) {
        console.error('Failed to copy static files:', e.message)
      }
    },
  }
}

// Custom plugin to copy CSS files that aren't being built
function copyLegacyCss() {
  return {
    name: 'copy-legacy-css',
    writeBundle() {
      const cssSourceDir = resolve(__dirname, 'resources/legacy/css')
      const cssOutputDir = resolve(__dirname, 'public/legacy/css')

      try {
        mkdirSync(cssOutputDir, { recursive: true })

        // Copy all CSS files except those we're building
        const files = readdirSync(cssSourceDir)
        files.forEach(file => {
          if (file.endsWith('.css') && !['admin.css', 'front-styles.css', 'mail.css'].includes(file)) {
            const sourcePath = join(cssSourceDir, file)
            const destPath = join(cssOutputDir, file)
            cpSync(sourcePath, destPath)
          }
        })

        console.log('✓ Copied legacy CSS files')
      } catch (e) {
        console.error('Failed to copy legacy CSS:', e.message)
      }
    },
  }
}

// Custom plugin to copy JSON data files
function copyJsonFiles() {
  return {
    name: 'copy-json-files',
    writeBundle() {
      const jsonSourceDir = resolve(__dirname, 'resources/json')
      const jsonOutputDir = resolve(__dirname, 'public/data')

      try {
        if (existsSync(jsonSourceDir)) {
          mkdirSync(jsonOutputDir, { recursive: true })

          // Copy all JSON files
          const files = readdirSync(jsonSourceDir)
          files.forEach(file => {
            if (file.endsWith('.json')) {
              const sourcePath = join(jsonSourceDir, file)
              const destPath = join(jsonOutputDir, file)
              cpSync(sourcePath, destPath)
            }
          })

          console.log('✓ Copied JSON data files')
        }
      } catch (e) {
        console.error('Failed to copy JSON files:', e.message)
      }
    },
  }
}

// Custom plugin to copy images for legacy CSS
function copyLegacyImages() {
  return {
    name: 'copy-legacy-images',
    writeBundle() {
      const imagesSourceDir = resolve(__dirname, 'resources/react/images')
      const imagesOutputDir = resolve(__dirname, 'public/legacy/images')

      try {
        if (existsSync(imagesSourceDir)) {
          mkdirSync(imagesOutputDir, { recursive: true })

          // Copy entire images directory recursively
          cpSync(imagesSourceDir, imagesOutputDir, { recursive: true })

          console.log('✓ Copied images for legacy CSS')
        }
      } catch (e) {
        console.error('Failed to copy images:', e.message)
      }
    },
  }
}

// Custom plugin to fix CSS URL paths
function fixCssUrlPaths() {
  return {
    name: 'fix-css-url-paths',
    generateBundle(options, bundle) {
      for (const fileName in bundle) {
        if (bundle[fileName].type === 'asset' && fileName.endsWith('.css')) {
          let css = bundle[fileName].source
          // Fix URL paths that are missing ../ prefix
          // Replace url(images/ with url(../images/ for files in css/ subdirectory
          if (fileName.includes('css/')) {
            css = css.replace(/url\((["']?)images\//g, 'url($1../images/')
            css = css.replace(/url\((["']?)fonts\//g, 'url($1../fonts/')
          }
          bundle[fileName].source = css
        }
      }
    },
  }
}

export default defineConfig({
  root: resolve(__dirname, 'resources/legacy'),
  publicDir: false,

  plugins: [
    cleanOutputDir(),
    jQueryReadyWrapper(),
    fixCssUrlPaths(),
    copyStaticFiles(),
    copyLegacyCss(),
    copyJsonFiles(),
    copyLegacyImages(),
  ],

  build: {
    outDir: resolve(__dirname, 'public/legacy'),
    emptyOutDir: false, // We handle cleanup with cleanOutputDir plugin
    minify: 'terser',

    terserOptions: {
      compress: {
        drop_console: false,
      },
      format: {
        comments: false,
      },
    },

    rollupOptions: {
      input: {
        // Admin bundle
        'js/admin.min': resolve(__dirname, 'resources/legacy/js/admin.js'),

        // Frontend bundle
        'js/frontend.min': resolve(__dirname, 'resources/legacy/js/frontend.js'),

        // Styles
        'css/admin.min': resolve(__dirname, 'resources/legacy/admin/admin.scss'),
        'css/front-styles.min': resolve(__dirname, 'resources/legacy/scss/front-styles.scss'),
        'css/mail.min': resolve(__dirname, 'resources/legacy/scss/mail.scss'),
      },

      output: {
        entryFileNames: '[name].js',
        chunkFileNames: 'js/[name].js',
        assetFileNames: (assetInfo) => {
          if (assetInfo.name && assetInfo.name.endsWith('.css')) {
            const name = assetInfo.name.replace('.css', '')
            if (name.includes('admin') || name.includes('front-styles') || name.includes('mail')) {
              return name + '.min.css'
            }
            return '[name][extname]'
          }
          return 'assets/[name][extname]'
        },
      },

      // External dependencies (available globally in WordPress)
      external: [
        'jquery',
      ],

      // Prevent code splitting by disabling chunk optimization
      preserveEntrySignatures: 'strict',
    },

    // Disable build optimization that creates shared chunks
    commonjsOptions: {
      transformMixedEsModules: true,
    },

    target: 'es2020',

    cssMinify: 'lightningcss',
  },

  css: {
    preprocessorOptions: {
      scss: {
        api: 'modern-compiler',
        silenceDeprecations: ['import'],
      },
    },
    lightningcss: {
      minify: true,
    },
  },

  resolve: {
    alias: {
      '@': resolve(__dirname, 'resources/legacy'),
    },
  },
})