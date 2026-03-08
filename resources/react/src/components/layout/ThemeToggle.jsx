import React from 'react'
import { Moon, Sun } from 'lucide-react'
import { useTheme } from '@/context/ThemeContext'
import { cn } from '@/lib/utils'

export default function ThemeToggle() {
  const { theme, toggleTheme } = useTheme()

  return (
    <button
      onClick={toggleTheme}
      className="wsms-flex wsms-h-8 wsms-w-8 wsms-items-center wsms-justify-center wsms-rounded wsms-text-muted-foreground hover:wsms-bg-accent hover:wsms-text-foreground wsms-transition-colors"
      aria-label={`Switch to ${theme === 'light' ? 'dark' : 'light'} mode`}
    >
      {theme === 'dark' ? (
        <Sun className="wsms-h-4 wsms-w-4" strokeWidth={1.5} />
      ) : (
        <Moon className="wsms-h-4 wsms-w-4" strokeWidth={1.5} />
      )}
    </button>
  )
}
