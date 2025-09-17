import './globals.css'

import React from 'react'
import { createRoot } from 'react-dom/client'

import { App } from './app'

const container = document.getElementById('wp-sms-react-root')

if (container) {
  const root = createRoot(container)
  root.render(
    <React.StrictMode>
      <App />
    </React.StrictMode>
  )
}
