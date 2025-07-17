# WP-SMS React Frontend

This directory contains the React frontend for WP-SMS admin interface.

## Directory Structure

```
assets/react/
├── src/                    # React source code
│   ├── components/         # Reusable UI components
│   ├── pages/             # Page-level components
│   ├── hooks/             # Custom React hooks
│   ├── services/          # API services
│   └── ...
├── public/                # Static assets
├── package.json           # Dependencies and scripts
├── vite.config.ts         # Build configuration
└── tsconfig.json          # TypeScript configuration
```

## Build Output

All build files are output to `public/admin/` directory:
- JavaScript bundles
- CSS files
- Asset manifest
- Source maps (in development)

## Development

### Prerequisites
- Node.js 18+
- npm or yarn

### Setup
```bash
cd assets/react
npm install
```

### Development Commands

```bash
# Start development server with hot reload
npm run dev

# Build for production
npm run build

# Watch mode (rebuilds on file changes)
npm run watch

# Type checking
npm run type-check

# Linting
npm run lint
```

### WordPress Integration

The React app is integrated with WordPress through:
- `src/Admin/Pages/SettingAdminPage.php` - Enqueues React assets
- REST API endpoints for data communication
- WordPress admin hooks and filters

## File Organization

### Components
- `ui/` - Basic UI components (buttons, inputs, etc.)
- `forms/` - Form-specific components
- `layout/` - Layout components (headers, sidebars, etc.)
- `features/` - Feature-specific components

### Pages
- `settings/` - Settings page components
- `dashboard/` - Dashboard components
- `subscribers/` - Subscriber management

### Services
- API calls to WordPress REST endpoints
- Data transformation utilities
- WordPress-specific integrations

## Build Configuration

The build is configured in `vite.config.ts`:
- Output directory: `../../public/admin`
- Asset optimization
- Code splitting
- WordPress compatibility shims

## WordPress Compatibility

- Uses WordPress REST API for data
- Integrates with WordPress admin interface
- Supports WordPress i18n for translations
- Compatible with WordPress security measures 