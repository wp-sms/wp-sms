import React from 'react'
import './globals.css'

import { createRoot } from 'react-dom/client'
import { HashRouter as Router } from 'react-router-dom'
import SettingsRoutes from '@/routes/settings'
import { SettingsLayout } from '@/components/layout/settings-layout'
import Providers from '@/providers'

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
