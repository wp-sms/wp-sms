# WP SMS Build & Development Guide

This documentation covers the assets build system and development workflow for the WP SMS plugin.

## Table of Contents

1. [Overview](#overview)
2. [Directory Structure](#directory-structure)
3. [Build System](#build-system)
4. [Development Workflow](#development-workflow)

---

## Overview

The WP SMS plugin uses a modern build system with clear separation between source files and built assets:

- **Source files**: `resources/` (development only, excluded from distribution)
- **Built assets**: `public/` (production builds, included in distribution)
- **Build tools**: Vite 7.x for legacy/React, Webpack 5 for Gutenberg blocks

### Key Features

- Modern build tooling (Vite 7.x)
- Faster builds and Hot Module Replacement
- Clear source vs. build separation
- Automatic asset copying (JSON, images, static files)
- Proper ES module handling
- All assets properly namespaced

---

## Directory Structure

```
wp-sms/
├── resources/              # Source files (NOT in distribution)
│   ├── legacy/
│   │   ├── js/            # Entry points (admin.js, frontend.js)
│   │   ├── scripts/       # Individual JS modules
│   │   ├── scss/          # SCSS source files
│   │   ├── css/           # Static CSS files
│   │   ├── static/        # Pre-built libraries (chart.js, select2, etc.)
│   │   ├── intel/         # Intel tel input custom script
│   │   ├── admin/         # Admin-specific SCSS
│   │   └── blocks/        # Gutenberg block sources
│   │
│   ├── react/             # React app source
│   │   ├── main.tsx       # React entry point
│   │   ├── app.tsx        # Main App component
│   │   ├── components/    # React components
│   │   ├── routes/        # Tanstack Router routes
│   │   ├── services/      # API services
│   │   └── images/        # React image assets
│   │
│   └── json/              # JSON data files (countries.json, etc.)
│
└── public/                 # Built assets (IN distribution)
    ├── legacy/
    │   ├── js/            # Built JavaScript + static files
    │   │   ├── admin.min.js         # 45 KB
    │   │   ├── frontend.min.js      # 6 KB
    │   │   ├── chart.min.js         # 191 KB (separate)
    │   │   ├── select2.js
    │   │   ├── flatpickr.js
    │   │   └── intel/               # Intel tel input
    │   │       ├── intel-script.js
    │   │       └── intlTelInput.min.js
    │   │
    │   └── css/           # Built CSS + static files
    │       ├── admin.min.css        # 196 KB
    │       ├── front-styles.min.css # 19 KB
    │       ├── mail.min.css         # 6 KB
    │       ├── intlTelInput.min.css
    │       └── [other static CSS]
    │
    ├── react/             # React app build
    │   ├── assets/        # Built JS/CSS chunks
    │   ├── images/        # React images (auto-copied)
    │   └── .vite/
    │       └── manifest.json
    │
    ├── blocks/            # Gutenberg blocks
    │   ├── Subscribe/     # Subscribe form block
    │   └── SendSms/       # Send SMS form block
    │
    └── data/              # JSON data (auto-copied from resources/json)
        └── countries.json
```

---

## Build System

### Build Commands

```bash
# Build everything (blocks + legacy + react)
npm run build

# Build individual parts
npm run build:blocks   # Gutenberg blocks (Webpack)
npm run build:legacy   # Legacy JS/CSS (Vite)
npm run build:react    # React app (Vite)

# Development modes
npm run dev            # React dev server (HMR)
npm run dev:react      # Same as above
npm run dev:legacy     # Legacy watch mode

# Watch modes (production builds with watch)
npm run watch:legacy   # Watch legacy assets
npm run watch:react    # Watch React assets
```

### Build Configurations

#### 1. Legacy Assets (`vite.config.legacy.js`)

**Entry Points:**
- `resources/legacy/js/admin.js` → `public/legacy/js/admin.min.js`
- `resources/legacy/js/frontend.js` → `public/legacy/js/frontend.min.js`
- `resources/legacy/admin/admin.scss` → `public/legacy/css/admin.min.css`
- `resources/legacy/scss/front-styles.scss` → `public/legacy/css/front-styles.min.css`
- `resources/legacy/scss/mail.scss` → `public/legacy/css/mail.min.css`

**Custom Plugins:**
- `cleanOutputDir()` - Cleans public/legacy before build
- `jQueryReadyWrapper()` - Wraps admin.min.js in jQuery ready
- `copyStaticFiles()` - Copies static JS files and Intel tel input from node_modules
- `copyLegacyCss()` - Copies static CSS files
- `copyJsonFiles()` - Copies JSON data files

**Key Features:**
- Terser minification
- LightningCSS for CSS minification
- Modern SCSS compiler API
- Automatic static file copying
- jQuery externalized (WordPress provides it)

#### 2. React App (`vite.config.react.ts`)

**Entry Point:**
- `resources/react/main.tsx` → `public/react/assets/main-[hash].js`

**Custom Plugins:**
- `copyImages()` - Copies images from resources/react/images to public/react/images
- `@tanstack/router-plugin` - Auto-generates routes
- `@tailwindcss/vite` - TailwindCSS v4 native integration

**Key Features:**
- TypeScript support
- Tanstack Router with auto code-splitting
- TailwindCSS v4
- Vite manifest.json for WordPress integration
- ES modules for modern browsers

#### 3. Gutenberg Blocks (`webpack.config.js`)

**Entry Points:**
- `resources/legacy/blocks/Subscribe/index.js` → `public/blocks/Subscribe/`
- `resources/legacy/blocks/SendSms/index.js` → `public/blocks/SendSms/`

**Output:**
Each block produces:
- `index.js` - Block editor code
- `index.css` - Block styles
- `index.asset.php` - WordPress dependencies
- `block.json` - Block metadata

**Uses:** `@wordpress/scripts` (Webpack 5 + WordPress tooling)

---

## Development Workflow

### Starting Development

```bash
# 1. Install dependencies
npm install

# 2. Start React dev server (HMR enabled)
npm run dev

# 3. In another terminal, watch legacy assets (optional)
npm run dev:legacy
```

### Adding New Assets

#### Adding a new JS module
1. Create file in `resources/legacy/scripts/`
2. Import in `resources/legacy/js/admin.js` or `frontend.js`
3. Build: `npm run build:legacy`

#### Adding a new SCSS file
1. Create file in `resources/legacy/scss/`
2. Import in main SCSS file or add as new entry in `vite.config.legacy.js`
3. Build: `npm run build:legacy`

#### Adding static files
1. Place in `resources/legacy/static/`
2. Add filename to `copyStaticFiles()` plugin in `vite.config.legacy.js`
3. Build: `npm run build:legacy`

#### Adding JSON data files
1. Place in `resources/json/`
2. Files automatically copied to `public/data/` during build
3. Update PHP code to reference `public/data/[filename].json`

#### Adding React images
1. Place in `resources/react/images/`
2. Images automatically copied to `public/react/images/` during build
3. Reference in templates as `WP_SMS_URL . 'public/react/images/[image].jpg'`

### Creating Production Builds

```bash
# Build everything
npm run build

# This runs:
# 1. npm run build:blocks  (Webpack - Gutenberg blocks)
# 2. npm run build:legacy  (Vite - Legacy assets)
# 3. npm run build:react   (Vite - React app)
```

**Build Output:**
- Blocks: ~7 KB total (Subscribe + SendSms)
- Legacy JS: ~51 KB (admin + frontend)
- Legacy CSS: ~221 KB (admin + frontend + mail)
- Static files: ~191 KB (chart.js separate)
- React app: ~750 KB (main bundle + CSS + images)

### Distribution

**Excluded from distribution (`.distignore`):**
```
/resources              # Source files
/docs                   # Build documentation
vite.config.legacy.js
vite.config.react.ts
webpack.config.js
tsconfig.*.json
eslint.config.js
```

**Included in distribution:**
```
/public/legacy/         # Built legacy assets
/public/react/          # Built React app
/public/blocks/         # Built Gutenberg blocks
/public/data/           # JSON data files
```

---

## Troubleshooting

### Build fails with "Cannot find module"

**Solution:** Run `npm install` to ensure all dependencies are installed.

### React app not rendering

**Check:**
1. Manifest exists: `public/react/.vite/manifest.json`
2. Main file key is `'main.tsx'` not `'src/main.tsx'`
3. ReactHandler.php uses correct manifest key

### Gutenberg blocks not appearing

**Check:**
1. Both blocks built: `public/blocks/Subscribe/` and `public/blocks/SendSms/`
2. Each has: `block.json`, `index.js`, `index.css`, `index.asset.php`
3. BlockAbstract.php uses `public/blocks/` path

### Intel tel input not working

**Check:**
1. Files exist in `public/legacy/js/intel/`
2. CSS exists: `public/legacy/css/intlTelInput.min.css`
3. PHP enqueues both JS files with correct paths
4. Version updated to `25.12.5` in PHP

### Chart.js errors in console

**Check:**
1. chart.min.js loads separately before admin.min.js
2. chart.min.js is in static folder, not bundled
3. WordPress dependency chain: jQuery → Chart → Admin

---

## Additional Resources

- [Vite Documentation](https://vitejs.dev/)
- [WordPress Scripts](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-scripts/)
- [Tanstack Router](https://tanstack.com/router)
- [TailwindCSS v4](https://tailwindcss.com/)

---

**Last Updated:** November 2024
**Build System:** Vite 7.x + Webpack 5
