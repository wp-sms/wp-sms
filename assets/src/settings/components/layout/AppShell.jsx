import React, { useState, lazy, Suspense, memo } from 'react'
import Sidebar from './Sidebar'
import Header from './Header'
import FloatingSaveBar from './FloatingSaveBar'
import BrandingFooter from './BrandingFooter'
import ErrorBoundary from '@/components/ErrorBoundary'
import { useSettings } from '@/context/SettingsContext'
import { useIsMobile } from '@/hooks/use-mobile'
import { Button } from '@/components/ui/button'
import { SkeletonCard } from '@/components/ui/skeleton'
import { Menu, X } from 'lucide-react'
import { cn } from '@/lib/utils'

// Import commonly used pages directly to avoid lazy loading context issues
import SendSms from '@/pages/SendSms'
import Overview from '@/pages/Overview'

// Lazy load other page components for code splitting
// Messaging pages
const Outbox = lazy(() => import('@/pages/Outbox'))
// Subscriber pages
const Subscribers = lazy(() => import('@/pages/Subscribers'))
const Groups = lazy(() => import('@/pages/Groups'))
// Settings pages
const Gateway = lazy(() => import('@/pages/Gateway'))
const PhoneConfig = lazy(() => import('@/pages/PhoneConfig'))
const MessageButton = lazy(() => import('@/pages/MessageButton'))
const Notifications = lazy(() => import('@/pages/Notifications'))
const Newsletter = lazy(() => import('@/pages/Newsletter'))
const Integrations = lazy(() => import('@/pages/Integrations'))
const Advanced = lazy(() => import('@/pages/Advanced'))
// Privacy page
const Privacy = lazy(() => import('@/pages/Privacy'))
// Add-on pages - WooCommerce Pro
const WooCommercePro = lazy(() => import('@/pages/WooCommercePro'))
const CartAbandonment = lazy(() => import('@/pages/CartAbandonment'))
const SmsCampaigns = lazy(() => import('@/pages/SmsCampaigns'))
// Add-on pages - Two-Way SMS
const TwoWayInbox = lazy(() => import('@/pages/TwoWayInbox'))
const TwoWayCommands = lazy(() => import('@/pages/TwoWayCommands'))
const TwoWaySettings = lazy(() => import('@/pages/TwoWaySettings'))

const pages = {
  // Messaging
  'send-sms': SendSms,
  outbox: Outbox,
  // Subscribers
  subscribers: Subscribers,
  groups: Groups,
  // Settings
  overview: Overview,
  gateway: Gateway,
  phone: PhoneConfig,
  'message-button': MessageButton,
  notifications: Notifications,
  newsletter: Newsletter,
  integrations: Integrations,
  advanced: Advanced,
  // Privacy
  privacy: Privacy,
  // Add-ons - WooCommerce Pro
  'woocommerce-pro': WooCommercePro,
  'cart-abandonment': CartAbandonment,
  'sms-campaigns': SmsCampaigns,
  // Add-ons - Two-Way SMS
  'two-way-inbox': TwoWayInbox,
  'two-way-commands': TwoWayCommands,
  'two-way-settings': TwoWaySettings,
}

// Memoized loading skeleton
const LoadingSkeleton = memo(function LoadingSkeleton() {
  return (
    <div className="wsms-space-y-4">
      <SkeletonCard />
      <SkeletonCard />
    </div>
  )
})

// Memoized app shell for performance
const AppShell = memo(function AppShell() {
  const { currentPage, isLoading } = useSettings()
  const isMobile = useIsMobile()
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)
  const CurrentPage = pages[currentPage] || Overview

  React.useEffect(() => {
    setMobileMenuOpen(false)
  }, [currentPage])

  return (
    <div className="wsms-settings-app">
      {/* Skip to content link for keyboard navigation */}
      <a
        href="#main-content"
        className="wsms-sr-only focus:wsms-not-sr-only focus:wsms-fixed focus:wsms-top-2 focus:wsms-left-2 focus:wsms-z-[100] focus:wsms-bg-primary focus:wsms-text-primary-foreground focus:wsms-px-4 focus:wsms-py-2 focus:wsms-rounded-md focus:wsms-shadow-lg focus:wsms-outline-none focus:wsms-ring-2 focus:wsms-ring-ring"
      >
        Skip to main content
      </a>

      {/* Header - Full width at top */}
      <Header
        onMenuClick={() => setMobileMenuOpen(true)}
        showMenuButton={isMobile}
      />

      <div className="wsms-settings-wrapper">
        {/* Mobile overlay */}
        {isMobile && mobileMenuOpen && (
          <div
            className="wsms-fixed wsms-inset-0 wsms-z-40 wsms-bg-black/50"
            onClick={() => setMobileMenuOpen(false)}
          />
        )}

        {/* Sidebar */}
        <aside
          className={cn(
            'wsms-sidebar',
            isMobile && 'wsms-fixed wsms-inset-y-0 wsms-left-0 wsms-z-50 wsms-w-[240px] wsms-transform wsms-transition-transform wsms-duration-200',
            isMobile && !mobileMenuOpen && 'wsms--translate-x-full'
          )}
        >
          <Sidebar onClose={() => setMobileMenuOpen(false)} showClose={isMobile && mobileMenuOpen} />
        </aside>

        {/* Main content */}
        <div className="wsms-main">
          <main id="main-content" className="wsms-content wsms-scrollbar-thin" tabIndex={-1}>
            <div className="wsms-content-inner">
              {isLoading ? (
                <LoadingSkeleton />
              ) : (
                <ErrorBoundary>
                  <Suspense fallback={<LoadingSkeleton />}>
                    <div className="wsms-animate-slide-up">
                      <CurrentPage />
                    </div>
                  </Suspense>
                </ErrorBoundary>
              )}

              <BrandingFooter />
            </div>
          </main>

          <FloatingSaveBar />
        </div>
      </div>
    </div>
  )
})

export default AppShell
