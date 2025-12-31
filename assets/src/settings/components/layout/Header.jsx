import React, { useState } from 'react'
import { useTheme } from '@/context/ThemeContext'
import Logo from './Logo'
import { Menu, Bell, Moon, Sun, Sparkles, ExternalLink } from 'lucide-react'
import { cn } from '@/lib/utils'
import { NotificationSidebar } from '@/components/notifications'
import { useNotifications } from '@/hooks/useNotifications'

/**
 * License/Upgrade button component
 * Follows the same logic as the PHP template in:
 * /includes/templates/admin/partials/license-status.php
 *
 * States:
 * 1. No license: Show "Upgrade to All-in-One"
 * 2. Partial license: Show "License: X/Y" with upgrade option
 * 3. Premium (All-in-One): Show "All-in-One" badge (no upgrade needed)
 */
function LicenseButton() {
  const wpSmsSettings = window.wpSmsSettings || {}
  const isPremium = wpSmsSettings?.addons?.pro || false
  const licenses = wpSmsSettings?.licenses || []
  const hasValidLicense = licenses.length > 0

  // Count licensed plugins vs total available
  const licensedCount = wpSmsSettings?.licensedPluginsCount || 0
  const totalPlugins = wpSmsSettings?.totalPlugins || 7

  const pricingUrl = 'https://wp-sms-pro.com/pricing?utm_source=wp-sms&utm_medium=link&utm_campaign=header'

  // State 3: Premium license - show activated badge
  if (isPremium) {
    return (
      <div className={cn(
        'wsms-flex wsms-items-center wsms-gap-2 wsms-px-3 wsms-py-1.5',
        'wsms-bg-emerald-500/10 wsms-text-emerald-600',
        'wsms-rounded-md wsms-text-[12px] wsms-font-medium'
      )}>
        <Sparkles className="wsms-h-3.5 wsms-w-3.5" />
        <span>All-in-One</span>
      </div>
    )
  }

  // State 2: Has partial license - show license count with upgrade
  if (hasValidLicense && licensedCount > 0) {
    return (
      <div className="wsms-flex wsms-items-center wsms-gap-2">
        <span className="wsms-text-[12px] wsms-text-muted-foreground">
          License: {licensedCount}/{totalPlugins}
        </span>
        <a
          href={pricingUrl}
          target="_blank"
          rel="noopener noreferrer"
          className={cn(
            'wsms-flex wsms-items-center wsms-gap-1.5 wsms-px-3 wsms-py-1.5',
            'wsms-bg-primary wsms-text-primary-foreground',
            'wsms-rounded-md wsms-text-[12px] wsms-font-medium',
            'wsms-transition-all wsms-duration-200',
            'hover:wsms-bg-primary/90'
          )}
        >
          <span>Upgrade</span>
          <ExternalLink className="wsms-h-3 wsms-w-3" />
        </a>
      </div>
    )
  }

  // State 1: No license - show full upgrade button
  return (
    <a
      href={pricingUrl}
      target="_blank"
      rel="noopener noreferrer"
      className={cn(
        'wsms-flex wsms-items-center wsms-gap-2 wsms-px-3 wsms-py-1.5',
        'wsms-bg-primary wsms-text-primary-foreground',
        'wsms-rounded-md wsms-text-[12px] wsms-font-medium',
        'wsms-transition-all wsms-duration-200',
        'hover:wsms-bg-primary/90'
      )}
    >
      <Sparkles className="wsms-h-3.5 wsms-w-3.5" />
      <span className="wsms-hidden sm:wsms-inline">Upgrade to All-in-One</span>
      <span className="wsms-inline sm:wsms-hidden">Upgrade</span>
      <ExternalLink className="wsms-h-3 wsms-w-3 wsms-opacity-70" />
    </a>
  )
}

/**
 * Notification bell button with sidebar
 */
function NotificationBell() {
  const [isSidebarOpen, setIsSidebarOpen] = useState(false)
  const {
    inboxNotifications,
    dismissedNotifications,
    hasUnread,
    loading,
    dismiss,
    dismissAll,
  } = useNotifications()

  const handleOpenSidebar = () => {
    setIsSidebarOpen(true)
  }

  const handleCloseSidebar = () => {
    setIsSidebarOpen(false)
  }

  return (
    <>
      <button
        onClick={handleOpenSidebar}
        className={cn(
          'wsms-relative wsms-flex wsms-h-8 wsms-w-8 wsms-items-center wsms-justify-center wsms-rounded-md',
          'wsms-text-muted-foreground wsms-transition-colors',
          'hover:wsms-bg-accent hover:wsms-text-foreground'
        )}
        aria-label="Notifications"
      >
        <Bell className="wsms-h-[18px] wsms-w-[18px]" strokeWidth={1.75} />
        {hasUnread && (
          <span className="wsms-absolute wsms-top-1 wsms-right-1 wsms-h-2 wsms-w-2 wsms-rounded-full wsms-bg-primary wsms-animate-pulse" />
        )}
      </button>

      <NotificationSidebar
        isOpen={isSidebarOpen}
        onClose={handleCloseSidebar}
        inboxNotifications={inboxNotifications}
        dismissedNotifications={dismissedNotifications}
        loading={loading}
        onDismiss={dismiss}
        onDismissAll={dismissAll}
      />
    </>
  )
}

/**
 * Theme toggle button
 */
function ThemeToggle() {
  const { theme, toggleTheme } = useTheme()

  return (
    <button
      onClick={toggleTheme}
      className={cn(
        'wsms-flex wsms-h-8 wsms-w-8 wsms-items-center wsms-justify-center wsms-rounded-md',
        'wsms-text-muted-foreground wsms-transition-colors',
        'hover:wsms-bg-accent hover:wsms-text-foreground'
      )}
      aria-label={`Switch to ${theme === 'light' ? 'dark' : 'light'} mode`}
    >
      {theme === 'light' ? (
        <Moon className="wsms-h-[18px] wsms-w-[18px]" strokeWidth={1.75} />
      ) : (
        <Sun className="wsms-h-[18px] wsms-w-[18px]" strokeWidth={1.75} />
      )}
    </button>
  )
}

export default function Header({ onMenuClick, showMenuButton }) {
  return (
    <header className="wsms-flex wsms-items-center wsms-justify-between wsms-h-14 wsms-px-4 wsms-bg-card wsms-border-b wsms-border-border">
      {/* Left: Mobile menu + Logo */}
      <div className="wsms-flex wsms-items-center wsms-gap-3">
        {showMenuButton && (
          <button
            onClick={onMenuClick}
            aria-label="Open navigation menu"
            className={cn(
              'wsms-flex wsms-items-center wsms-justify-center wsms-h-8 wsms-w-8 wsms-rounded-md',
              'wsms-text-muted-foreground wsms-transition-colors',
              'hover:wsms-bg-accent hover:wsms-text-foreground'
            )}
          >
            <Menu className="wsms-h-5 wsms-w-5" aria-hidden="true" />
          </button>
        )}
        <Logo className="wsms-h-8" />
      </div>

      {/* Right: License button + Actions */}
      <div className="wsms-flex wsms-items-center wsms-gap-3">
        <LicenseButton />

        <div className="wsms-h-5 wsms-w-px wsms-bg-border" />

        <div className="wsms-flex wsms-items-center wsms-gap-1">
          <NotificationBell />
          <ThemeToggle />
        </div>
      </div>
    </header>
  )
}
