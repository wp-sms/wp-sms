import { __, sprintf } from '@wordpress/i18n'
import React, { useState } from 'react'
import { useTheme } from '@/context/ThemeContext'
import Logo from './Logo'
import { Menu, Bell, Moon, Sun, Sparkles, ExternalLink, KeyRound } from 'lucide-react'
import { cn } from '@/lib/utils'
import { NotificationSidebar } from '@/components/notifications'
import { useNotifications } from '@/hooks/useNotifications'
import { useSavedSetting } from '@/context/SettingsContext'

/**
 * License/Upgrade button component
 * Follows the same logic as the legacy PHP template in:
 * /includes/templates/admin/partials/license-status.php
 *
 * States:
 * 1. Premium (All-in-One SKU): Show "All-in-One" badge (no upgrade link)
 * 2. Has any valid license (single/partial): Show "License: X/Y" with upgrade
 * 3. No license at all: Show "Upgrade to All-in-One"
 */
function LicenseButton() {
  const wpSmsSettings = window.wpSmsSettings || {}
  const license = wpSmsSettings?.license || {}
  const features = wpSmsSettings?.features || {}
  const isPremium = license.isPremium || false
  const hasValidLicense = license.hasValidLicense || false
  const summary = features.licenseSummary || {}
  const licensedCount = summary.licensed ?? license.licensedCount ?? 0
  const totalPlugins = summary.total ?? license.totalPlugins ?? 7
  const addOnsUrl = 'admin.php?page=wsms&tab=add-ons'

  const pricingUrl = 'https://wsms.io/pricing?utm_source=wp-sms&utm_medium=link&utm_campaign=header'

  const buttonClass = cn(
    'wsms-flex wsms-h-7 wsms-items-center wsms-gap-1.5 wsms-px-2.5',
    'wsms-rounded-md wsms-text-[13px] wsms-font-semibold wsms-leading-none wsms-no-underline',
    'wsms-transition-colors wsms-duration-200'
  )

  // State 0: a license expired - the fix comes first, with the discount when there is one
  if (features.licenseExpired > 0) {
    const renew = features.licenseRenew
    const label = renew?.percent > 0
      ? sprintf(__('Renew with %s%% discount', 'wp-sms'), String(renew.percent))
      : __('Renew', 'wp-sms')

    return (
      <div className="wsms-flex wsms-items-center wsms-gap-2">
        <a
          href={addOnsUrl}
          className="wsms-flex wsms-h-7 wsms-items-center wsms-gap-1.5 wsms-rounded-md wsms-bg-amber-500/10 wsms-px-2.5 wsms-text-[13px] wsms-font-semibold wsms-leading-none wsms-text-amber-700 wsms-no-underline hover:wsms-bg-amber-500/20"
        >
          <span aria-hidden="true">⚠️</span>
          <span>{__('License expired', 'wp-sms')}</span>
        </a>
        <a
          href={renew?.url || addOnsUrl}
          {...(renew?.url ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
          className={cn(buttonClass, 'wsms-bg-red-600 wsms-text-white hover:wsms-bg-red-700')}
        >
          <span aria-hidden="true">🔄</span>
          <span>{label}</span>
        </a>
      </div>
    )
  }

  // State 1: All-in-One license - badge with emoji, links to license management
  if (isPremium) {
    return (
      <a
        href={addOnsUrl}
        title={__('Manage licenses', 'wp-sms')}
        className={cn(buttonClass, 'wsms-bg-emerald-500/10 wsms-text-emerald-700 hover:wsms-bg-emerald-500/20')}
      >
        <svg aria-hidden="true" viewBox="0 0 24 24" className="wsms-h-5 wsms-w-5 wsms-shrink-0">
          <path d="M3.85 8.62a4 4 0 0 1 4.78-4.77 4 4 0 0 1 6.74 0 4 4 0 0 1 4.78 4.78 4 4 0 0 1 0 6.74 4 4 0 0 1-4.77 4.78 4 4 0 0 1-6.75 0 4 4 0 0 1-4.78-4.77 4 4 0 0 1 0-6.76Z" fill="#5fae62" />
          <path d="m8.5 12.3 2.4 2.4 4.6-4.9" fill="none" stroke="#fff" strokeWidth="2.2" strokeLinecap="round" strokeLinejoin="round" />
        </svg>
        <span>{__('All-in-One', 'wp-sms')}</span>
      </a>
    )
  }

  // State 2: Has valid license but not AIO - count links to license management, plus upgrade
  if (hasValidLicense) {
    return (
      <div className="wsms-flex wsms-items-center wsms-gap-2">
        <a
          href={addOnsUrl}
          title={__('Manage licenses', 'wp-sms')}
          className="wsms-flex wsms-h-7 wsms-items-center wsms-gap-1.5 wsms-rounded-md wsms-px-2 wsms-text-[13px] wsms-font-medium wsms-leading-none wsms-text-muted-foreground wsms-no-underline hover:wsms-bg-accent hover:wsms-text-foreground"
        >
          <KeyRound className="wsms-h-4 wsms-w-4 wsms-text-primary" aria-hidden="true" />
          <span>{__('License:', 'wp-sms')} {licensedCount}/{totalPlugins}</span>
        </a>
        <a
          href={pricingUrl}
          target="_blank"
          rel="noopener noreferrer"
          className={cn(buttonClass, 'wsms-bg-primary wsms-text-primary-foreground hover:wsms-bg-primary/90')}
        >
          <span>{__('Upgrade', 'wp-sms')}</span>
          <ExternalLink className="wsms-h-3 wsms-w-3" />
        </a>
      </div>
    )
  }

  // State 2b: add-ons are installed but no key was entered yet - the next step is entering it
  if (!hasValidLicense && features.licenseMissing > 0) {
    return (
      <div className="wsms-flex wsms-items-center wsms-gap-2">
        <a href={addOnsUrl} className={cn(buttonClass, 'wsms-bg-primary wsms-text-primary-foreground hover:wsms-bg-primary/90')}>
          <KeyRound className="wsms-h-4 wsms-w-4" aria-hidden="true" />
          <span>{__('Activate license', 'wp-sms')}</span>
        </a>
        <a
          href={pricingUrl}
          target="_blank"
          rel="noopener noreferrer"
          className="wsms-hidden wsms-text-[13px] wsms-text-muted-foreground wsms-underline-offset-2 hover:wsms-text-foreground hover:wsms-underline sm:wsms-inline"
        >
          {__('Upgrade', 'wp-sms')}
        </a>
      </div>
    )
  }

  // State 3: No license at all - one upgrade button, and a link for people who already have a key
  return (
    <div className="wsms-flex wsms-items-center wsms-gap-2">
      <a
        href={pricingUrl}
        target="_blank"
        rel="noopener noreferrer"
        className={cn(buttonClass, 'wsms-bg-primary wsms-text-primary-foreground hover:wsms-bg-primary/90')}
      >
        <span aria-hidden="true">✨</span>
        <span className="wsms-hidden sm:wsms-inline">{__('Upgrade to All-in-One', 'wp-sms')}</span>
        <span className="wsms-inline sm:wsms-hidden">{__('Upgrade', 'wp-sms')}</span>
        <ExternalLink className="wsms-h-3 wsms-w-3 wsms-opacity-70" />
      </a>
      <a
        href={addOnsUrl}
        className="wsms-hidden wsms-text-[13px] wsms-text-muted-foreground wsms-underline-offset-2 hover:wsms-text-foreground hover:wsms-underline sm:wsms-inline"
      >
        {__('Have a license?', 'wp-sms')}
      </a>
    </div>
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

  // An unread offer (promo notice) turns the red count into a rainbow one, so it reads as different.
  const offer = inboxNotifications.find(n => n.backgroundColor === 'promo')
  const hasOffer = Boolean(offer)
  const offerText = (offer?.badge || '-30%').replace(/[\u2600-\u27BF\uFE0F]|\uD83C[\uDC00-\uDFFF]|\uD83D[\uDC00-\uDFFF]|\uD83E[\uDC00-\uDFFF]/g, '').trim() || '-30%'

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
          'wsms-relative wsms-flex wsms-h-8 wsms-items-center wsms-justify-center wsms-gap-1.5 wsms-rounded-md',
          'wsms-w-8',
          'wsms-text-muted-foreground wsms-transition-colors',
          'hover:wsms-bg-accent hover:wsms-text-foreground'
        )}
        aria-label={__('Notifications', 'wp-sms')}
      >
        <span className="wsms-relative wsms-flex wsms-items-center wsms-justify-center">
          {hasOffer ? (
            <span className="wsms-flex wsms-h-7 wsms-w-7 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-gradient-to-br wsms-from-rose-500/15 wsms-to-orange-400/20 wsms-text-[19px] wsms-leading-none" aria-hidden="true">🎁</span>
          ) : (
            <Bell className="wsms-h-[18px] wsms-w-[18px]" strokeWidth={1.75} />
          )}
          {hasUnread && (
            <span className="wsms-absolute wsms--top-1.5 wsms--end-1.5 wsms-flex wsms-h-4 wsms-min-w-4 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-red-600 wsms-px-1 wsms-text-[11px] wsms-font-bold wsms-leading-none wsms-text-white wsms-ring-2 wsms-ring-card">
              {inboxNotifications.length}
            </span>
          )}
        </span>
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
  const displayNotifications = useSavedSetting('display_notifications', '1')
  const isAllInOne = window.wpSmsSettings?.license?.isPremium || false
  const saleEnd = new Date('2026-12-01T23:59:00Z').getTime()
  const daysLeft = Math.ceil((saleEnd - Date.now()) / 86400000)
  const pillText = daysLeft <= 1 ? __('Sale ends today', 'wp-sms') : daysLeft <= 3 ? sprintf(__('Sale ends in %d days', 'wp-sms'), daysLeft) : __('Black Friday: 30% off', 'wp-sms')
  const pillEmoji = daysLeft <= 1 ? '⏰' : '🔥'
  const pillUrgent = daysLeft <= 1
  const hasExpired = (window.wpSmsSettings?.features?.licenseExpired || 0) > 0
  const showBlackFriday = (!isAllInOne && !hasExpired) || new URLSearchParams(window.location.search).has('bf-preview')

  return (
    <div className="wsms-flex wsms-items-center wsms-justify-between wsms-h-16 wsms-px-5 wsms-bg-card wsms-border-b wsms-border-border">
      {/* Left: Mobile menu + Logo */}
      <div className="wsms-flex wsms-items-center wsms-gap-3">
        {showMenuButton && (
          <button
            onClick={onMenuClick}
            aria-label={__('Open navigation menu', 'wp-sms')}
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

        <div className="wsms-hidden wsms-h-5 wsms-w-px wsms-bg-border sm:wsms-block" />

        <div className="wsms-flex wsms-items-center wsms-gap-3">
          <LicenseButton />
        </div>
      </div>

      {/* Right: notifications and theme */}
      <div className="wsms-flex wsms-items-center wsms-gap-3">
        {/* LOCAL PREVIEW ONLY: hardcoded Black Friday pill, not from Horizon. Shown to non-All-in-One users; add &bf-preview=1 to the URL to see it on an All-in-One site. */}
        {showBlackFriday && (
          <a
            href="https://wsms.io/pricing/?utm_source=wp-sms&utm_medium=banner&utm_campaign=black-friday-2026"
            target="_blank"
            rel="noopener noreferrer"
            title="30% off WSMS All-in-One, first year. Code BFCM2026. Ends Dec 1."
            className={cn(
              'wsms-me-2 wsms-hidden wsms-items-center wsms-gap-1.5 wsms-rounded-full wsms-px-3 wsms-py-1 wsms-text-xs wsms-font-semibold wsms-text-white wsms-no-underline md:wsms-flex',
              pillUrgent ? 'wsms-bg-red-800 hover:wsms-bg-red-900' : 'wsms-bg-red-600 hover:wsms-bg-red-700'
            )}
          >
            <span aria-hidden="true">{pillEmoji}</span>
            <span>{pillText}</span>
          </a>
        )}
        {displayNotifications === '1' && <NotificationBell />}
        <ThemeToggle />
      </div>
    </div>
  )
}
