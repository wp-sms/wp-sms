import React from 'react'
import { createRoot } from 'react-dom/client'
import App from './App'
import './styles/index.css'

// Wait for DOM to be ready
const init = () => {
  const rootElement = document.getElementById('wpsms-settings-root')

  if (rootElement) {
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
