const path = require('path')
const plugin = require('tailwindcss/plugin')

/** @type {import('tailwindcss').Config} */
module.exports = {
  prefix: 'wsms-',
  important: '#wpsms-settings-root',
  darkMode: ['class', '[data-theme="dark"]'],
  content: [
    path.join(__dirname, 'resources/react/src/**/*.{js,jsx,ts,tsx}'),
  ],
  theme: {
    extend: {
      colors: {
        border: 'hsl(var(--border))',
        input: 'hsl(var(--input))',
        ring: 'hsl(var(--ring))',
        background: 'hsl(var(--background))',
        foreground: 'hsl(var(--foreground))',
        primary: {
          DEFAULT: 'hsl(var(--primary))',
          foreground: 'hsl(var(--primary-foreground))',
        },
        secondary: {
          DEFAULT: 'hsl(var(--secondary))',
          foreground: 'hsl(var(--secondary-foreground))',
        },
        destructive: {
          DEFAULT: 'hsl(var(--destructive))',
          foreground: 'hsl(var(--destructive-foreground))',
        },
        muted: {
          DEFAULT: 'hsl(var(--muted))',
          foreground: 'hsl(var(--muted-foreground))',
        },
        accent: {
          DEFAULT: 'hsl(var(--accent))',
          foreground: 'hsl(var(--accent-foreground))',
        },
        popover: {
          DEFAULT: 'hsl(var(--popover))',
          foreground: 'hsl(var(--popover-foreground))',
        },
        card: {
          DEFAULT: 'hsl(var(--card))',
          foreground: 'hsl(var(--card-foreground))',
        },
        success: {
          DEFAULT: 'hsl(var(--success))',
          foreground: 'hsl(var(--success-foreground))',
        },
        warning: {
          DEFAULT: 'hsl(var(--warning))',
          foreground: 'hsl(var(--warning-foreground))',
        },
      },
      borderRadius: {
        lg: 'var(--radius)',
        md: 'calc(var(--radius) - 2px)',
        sm: 'calc(var(--radius) - 4px)',
      },
      fontFamily: {
        sans: [
          '-apple-system',
          'BlinkMacSystemFont',
          'Segoe UI',
          'Roboto',
          'Oxygen-Sans',
          'Ubuntu',
          'Cantarell',
          'Helvetica Neue',
          'sans-serif',
        ],
      },
      keyframes: {
        'accordion-down': {
          from: { height: '0' },
          to: { height: 'var(--radix-accordion-content-height)' },
        },
        'accordion-up': {
          from: { height: 'var(--radix-accordion-content-height)' },
          to: { height: '0' },
        },
        'slide-up': {
          from: { transform: 'translateY(100%)' },
          to: { transform: 'translateY(0)' },
        },
        'slide-down': {
          from: { transform: 'translateY(0)' },
          to: { transform: 'translateY(100%)' },
        },
        'fade-in': {
          from: { opacity: '0' },
          to: { opacity: '1' },
        },
      },
      animation: {
        'accordion-down': 'accordion-down 0.2s ease-out',
        'accordion-up': 'accordion-up 0.2s ease-out',
        'slide-up': 'slide-up 0.3s ease-out',
        'slide-down': 'slide-down 0.3s ease-out',
        'fade-in': 'fade-in 0.2s ease-out',
      },
    },
  },
  plugins: [
    plugin(function ({ addVariant }) {
      // Direction-aware variants scoped to the React root.
      //
      // Note: this project uses `important: '#wpsms-settings-root'`, which prefixes every selector
      // with `#wpsms-settings-root`. If we used a plain selector like `#wpsms-settings-root[dir="rtl"] &`,
      // Tailwind would end up generating impossible selectors like:
      //   #wpsms-settings-root #wpsms-settings-root[dir="rtl"] .rtl\:...
      // So we rewrite the prefixed selector in-place.
      addVariant('rtl', ({ modifySelectors }) => {
        modifySelectors(({ selector }) =>
          selector.startsWith('#wpsms-settings-root')
            ? selector.replace(/^#wpsms-settings-root\b/, '#wpsms-settings-root[dir="rtl"]')
            : selector
        )
      })

      addVariant('ltr', ({ modifySelectors }) => {
        modifySelectors(({ selector }) =>
          selector.startsWith('#wpsms-settings-root')
            ? selector.replace(/^#wpsms-settings-root\b/, '#wpsms-settings-root[dir="ltr"]')
            : selector
        )
      })
    }),
  ],
}
