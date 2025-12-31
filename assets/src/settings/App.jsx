import React from 'react'
import { SettingsProvider } from './context/SettingsContext'
import { ThemeProvider } from './context/ThemeContext'
import { Toaster } from './components/ui/toaster'
import AppShell from './components/layout/AppShell'

export default function App() {
  return (
    <ThemeProvider>
      <SettingsProvider>
        <Toaster>
          <AppShell />
        </Toaster>
      </SettingsProvider>
    </ThemeProvider>
  )
}
