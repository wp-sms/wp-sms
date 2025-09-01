import { HashRouter as Router } from 'react-router-dom'

import { SettingsLayout } from '@/components/layout/settings-layout'
import Providers from '@/providers'
import AppRoutes from '@/routes/root'

import { Toaster } from './components/ui/sonner'

export const App = () => {
  return (
    <Providers>
      <Router>
        <SettingsLayout>
          <AppRoutes />
        </SettingsLayout>
      </Router>
      <Toaster />
    </Providers>
  )
}
