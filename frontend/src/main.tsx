import './globals.css'

import React from 'react'
import { createRoot } from 'react-dom/client'
import { HashRouter as Router } from 'react-router-dom'

import { SettingsLayout } from '@/components/layout/settings-layout'
import Providers from '@/providers'
import SettingsRoutes from '@/routes/settings'

const container = document.getElementById('wp-sms-settings-root')

if (container) {
  const root = createRoot(container)
  root.render(
    <React.StrictMode>
      <Providers>
        <Router>
          <SettingsLayout>
            <SettingsRoutes />
          </SettingsLayout>
        </Router>
      </Providers>
    </React.StrictMode>
  )
}
