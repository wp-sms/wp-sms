import React, { useState, Suspense, memo, useEffect } from 'react'
import Sidebar from './Sidebar'
import Header from './Header'
import FloatingSaveBar from './FloatingSaveBar'
import BrandingFooter from './BrandingFooter'
import ErrorBoundary from '@/components/ErrorBoundary'
import { useSettings } from '@/context/SettingsContext'
import { useIsMobile } from '@/hooks/use-mobile'
import { Button } from '@/components/ui/button'
import { PageLoadingSkeleton } from '@/components/ui/skeleton'
import { Menu, X } from 'lucide-react'
import { cn } from '@/lib/utils'
import { getPageComponents } from '@/lib/pageRegistry'

// Get page components from centralized registry
const pages = getPageComponents()

// Memoized loading skeleton - uses PageLoadingSkeleton for consistent data table loading
const LoadingSkeleton = memo(function LoadingSkeleton() {
  return <PageLoadingSkeleton />
})

// Memoized app shell for performance
const AppShell = memo(function AppShell() {
  const { currentPage, isLoading } = useSettings()
  const isMobile = useIsMobile()
  const [mobileMenuOpen, setMobileMenuOpen] = useState(false)

  // Track visited pages to keep them mounted (preserve state when switching tabs)
  const [visitedPages, setVisitedPages] = useState(() => new Set([currentPage]))

  // Add current page to visited pages when it changes
  useEffect(() => {
    if (currentPage && pages[currentPage]) {
      setVisitedPages((prev) => {
        if (prev.has(currentPage)) return prev
        const next = new Set(prev)
        next.add(currentPage)
        return next
      })
    }
  }, [currentPage])

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
                <>
                  {/* Render all visited pages, hide inactive ones to preserve state */}
                  {Array.from(visitedPages).map((pageId) => {
                    const PageComponent = pages[pageId]
                    if (!PageComponent) return null
                    const isActive = pageId === currentPage
                    return (
                      <div
                        key={pageId}
                        style={{ display: isActive ? 'block' : 'none' }}
                        aria-hidden={!isActive}
                      >
                        <ErrorBoundary>
                          <Suspense fallback={<LoadingSkeleton />}>
                            <PageComponent />
                          </Suspense>
                        </ErrorBoundary>
                      </div>
                    )
                  })}
                </>
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
