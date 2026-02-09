import React from 'react'
import { createRoot } from 'react-dom/client'
import App from './App'
import './styles/index.css'

// Wait for DOM to be ready
const init = () => {
  const rootElement = document.getElementById('wpsms-settings-root')

  if (rootElement) {
    // Ensure the React root always has an explicit, correct `dir` so Tailwind `rtl:` variants
    // and logical utilities behave consistently. Some pages may render the root with an
    // incorrect dir; normalize it from the surrounding WordPress admin direction.
    const docDir =
      document.documentElement?.getAttribute('dir') || document.body?.getAttribute('dir')

    const isRtl =
      (docDir && String(docDir).toLowerCase() === 'rtl') ||
      document.body?.classList?.contains('rtl')

    const desiredDir = isRtl ? 'rtl' : 'ltr'
    if (rootElement.getAttribute('dir') !== desiredDir) {
      rootElement.setAttribute('dir', desiredDir)
    }

    const root = createRoot(rootElement)
    // StrictMode causes double-renders that conflict with Radix UI portals
    // Only use in development when needed for debugging
    root.render(<App />)
  }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', init)
} else {
  init()
}
