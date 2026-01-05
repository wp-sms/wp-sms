import React from 'react'
import { SettingsProvider } from './context/SettingsContext'
import { ThemeProvider } from './context/ThemeContext'
import { Toaster } from './components/ui/toaster'
import AppShell from './components/layout/AppShell'
import ErrorBoundary from './components/ErrorBoundary'

export default function App() {
  return (
    <ErrorBoundary>
      <ThemeProvider>
        <SettingsProvider>
          <Toaster>
            <AppShell />
          </Toaster>
        </SettingsProvider>
      </ThemeProvider>
    </ErrorBoundary>
  )
}
