import React, { useState, lazy, Suspense, memo } from 'react'
import Sidebar from './Sidebar'
import Header from './Header'
import FloatingSaveBar from './FloatingSaveBar'
import { useSettings } from '@/context/SettingsContext'
import { useIsMobile } from '@/hooks/use-mobile'
import { Button } from '@/components/ui/button'
import { SkeletonCard } from '@/components/ui/skeleton'
import { Menu, X } from 'lucide-react'
import { cn } from '@/lib/utils'

// Lazy load page components for code splitting
// Messaging pages
const SendSms = lazy(() => import('@/pages/SendSms'))
const Outbox = lazy(() => import('@/pages/Outbox'))
// Subscriber pages
const Subscribers = lazy(() => import('@/pages/Subscribers'))
const Groups = lazy(() => import('@/pages/Groups'))
// Settings pages
const Overview = lazy(() => import('@/pages/Overview'))
const Gateway = lazy(() => import('@/pages/Gateway'))
const PhoneConfig = lazy(() => import('@/pages/PhoneConfig'))
const MessageButton = lazy(() => import('@/pages/MessageButton'))
const Notifications = lazy(() => import('@/pages/Notifications'))
const Newsletter = lazy(() => import('@/pages/Newsletter'))
const Integrations = lazy(() => import('@/pages/Integrations'))
const Advanced = lazy(() => import('@/pages/Advanced'))
// Privacy page
const Privacy = lazy(() => import('@/pages/Privacy'))

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
          <Header
            onMenuClick={() => setMobileMenuOpen(true)}
            showMenuButton={isMobile}
          />

          <main className="wsms-content wsms-scrollbar-thin">
            <div className="wsms-content-inner">
              {isLoading ? (
                <LoadingSkeleton />
              ) : (
                <Suspense fallback={<LoadingSkeleton />}>
                  <div className="wsms-animate-slide-up">
                    <CurrentPage />
                  </div>
                </Suspense>
              )}
            </div>
          </main>

          <FloatingSaveBar />
        </div>
      </div>
    </div>
  )
})

export default AppShell
