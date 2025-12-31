import React, { useState } from 'react'
import Sidebar from './Sidebar'
import Header from './Header'
import FloatingSaveBar from './FloatingSaveBar'
import { useSettings } from '@/context/SettingsContext'
import { useIsMobile } from '@/hooks/use-mobile'
import { Button } from '@/components/ui/button'
import { SkeletonCard } from '@/components/ui/skeleton'
import { Menu, X } from 'lucide-react'
import { cn } from '@/lib/utils'

import Overview from '@/pages/Overview'
import Gateway from '@/pages/Gateway'
import PhoneConfig from '@/pages/PhoneConfig'
import MessageButton from '@/pages/MessageButton'
import Notifications from '@/pages/Notifications'
import Newsletter from '@/pages/Newsletter'
import Integrations from '@/pages/Integrations'
import Advanced from '@/pages/Advanced'

const pages = {
  overview: Overview,
  gateway: Gateway,
  phone: PhoneConfig,
  'message-button': MessageButton,
  notifications: Notifications,
  newsletter: Newsletter,
  integrations: Integrations,
  advanced: Advanced,
}

function LoadingSkeleton() {
  return (
    <div className="wsms-space-y-4">
      <SkeletonCard />
      <SkeletonCard />
    </div>
  )
}

export default function AppShell() {
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
                <div className="wsms-animate-slide-up">
                  <CurrentPage />
                </div>
              )}
            </div>
          </main>

          <FloatingSaveBar />
        </div>
      </div>
    </div>
  )
}
