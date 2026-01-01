import React, { useState, useEffect, useRef } from 'react'
import { X, Loader2, Bell, Trash2 } from 'lucide-react'
import { cn } from '@/lib/utils'
import { NotificationCard } from './NotificationCard'
import { EmptyState } from './EmptyState'

/**
 * WordPress admin bar height
 */
const WP_ADMIN_BAR_HEIGHT = 32

/**
 * Tab button component with refined styling
 */
function TabButton({ active, onClick, children, count }) {
  return (
    <button
      onClick={onClick}
      className={cn(
        'wsms-relative wsms-px-1 wsms-py-3 wsms-text-[13px] wsms-font-medium',
        'wsms-transition-all wsms-duration-200',
        'focus:wsms-outline-none focus-visible:wsms-ring-2 focus-visible:wsms-ring-primary/50 focus-visible:wsms-ring-offset-1',
        active
          ? 'wsms-text-foreground'
          : 'wsms-text-muted-foreground hover:wsms-text-foreground/80'
      )}
    >
      <span className="wsms-flex wsms-items-center wsms-gap-1.5">
        {children}
        {count > 0 && (
          <span className={cn(
            'wsms-inline-flex wsms-items-center wsms-justify-center',
            'wsms-min-w-[18px] wsms-h-[18px] wsms-px-1.5 wsms-rounded-full',
            'wsms-text-[10px] wsms-font-semibold wsms-tabular-nums',
            'wsms-transition-all wsms-duration-200',
            active
              ? 'wsms-bg-primary wsms-text-primary-foreground wsms-shadow-sm wsms-shadow-primary/25'
              : 'wsms-bg-muted wsms-text-muted-foreground'
          )}>
            {count > 99 ? '99+' : count}
          </span>
        )}
      </span>

      {/* Active indicator line */}
      <span
        className={cn(
          'wsms-absolute wsms-bottom-0 wsms-left-0 wsms-right-0 wsms-h-0.5',
          'wsms-bg-primary wsms-rounded-full',
          'wsms-transition-all wsms-duration-300 wsms-ease-out',
          active
            ? 'wsms-opacity-100 wsms-scale-x-100'
            : 'wsms-opacity-0 wsms-scale-x-0'
        )}
      />
    </button>
  )
}

/**
 * Notification sidebar panel component
 */
export function NotificationSidebar({
  isOpen,
  onClose,
  inboxNotifications = [],
  dismissedNotifications = [],
  loading = false,
  onDismiss,
  onDismissAll,
}) {
  const [activeTab, setActiveTab] = useState('inbox')
  const [isAnimatingOut, setIsAnimatingOut] = useState(false)
  const contentRef = useRef(null)

  // Lock body scroll when sidebar is open
  useEffect(() => {
    if (isOpen) {
      document.body.classList.add('wsms-notification-sidebar-open')
      setIsAnimatingOut(false)
    } else {
      document.body.classList.remove('wsms-notification-sidebar-open')
    }

    return () => {
      document.body.classList.remove('wsms-notification-sidebar-open')
    }
  }, [isOpen])

  // Close on escape key
  useEffect(() => {
    const handleEscape = (e) => {
      if (e.key === 'Escape' && isOpen) {
        onClose()
      }
    }

    document.addEventListener('keydown', handleEscape)
    return () => document.removeEventListener('keydown', handleEscape)
  }, [isOpen, onClose])

  // Scroll to top when switching tabs
  useEffect(() => {
    if (contentRef.current) {
      contentRef.current.scrollTop = 0
    }
  }, [activeTab])

  const currentNotifications = activeTab === 'inbox' ? inboxNotifications : dismissedNotifications
  const totalCount = inboxNotifications.length + dismissedNotifications.length

  return (
    <>
      {/* Overlay with blur effect */}
      <div
        className={cn(
          'wsms-fixed wsms-inset-0 wsms-z-[9998]',
          'wsms-transition-all wsms-duration-300 wsms-ease-out',
          isOpen
            ? 'wsms-opacity-100 wsms-backdrop-blur-[2px]'
            : 'wsms-opacity-0 wsms-pointer-events-none'
        )}
        style={{ top: WP_ADMIN_BAR_HEIGHT }}
        onClick={onClose}
        aria-hidden="true"
      >
        {/* Gradient overlay for depth */}
        <div className="wsms-absolute wsms-inset-0 wsms-bg-gradient-to-l wsms-from-black/40 wsms-via-black/25 wsms-to-black/10" />
      </div>

      {/* Sidebar panel */}
      <div
        className={cn(
          'wsms-fixed wsms-right-0 wsms-w-[420px] wsms-max-w-[calc(100vw-24px)]',
          'wsms-bg-background wsms-z-[9999]',
          'wsms-flex wsms-flex-col',
          'wsms-transition-transform wsms-duration-300',
          isOpen
            ? 'wsms-translate-x-0 wsms-ease-out'
            : 'wsms-translate-x-full wsms-ease-in'
        )}
        style={{
          top: WP_ADMIN_BAR_HEIGHT,
          height: `calc(100vh - ${WP_ADMIN_BAR_HEIGHT}px)`,
          boxShadow: isOpen
            ? '-20px 0 60px -15px rgba(0, 0, 0, 0.2), -8px 0 20px -10px rgba(0, 0, 0, 0.1)'
            : 'none'
        }}
        role="dialog"
        aria-modal="true"
        aria-label="Notifications"
      >
        {/* Header with gradient accent */}
        <div className="wsms-relative wsms-flex-shrink-0">
          {/* Subtle top accent line */}
          <div className="wsms-absolute wsms-top-0 wsms-left-0 wsms-right-0 wsms-h-[2px] wsms-bg-gradient-to-r wsms-from-primary/60 wsms-via-primary wsms-to-primary/60" />

          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-px-5 wsms-py-4 wsms-bg-card wsms-border-b wsms-border-border/50">
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-items-center wsms-justify-center wsms-h-9 wsms-w-9 wsms-rounded-lg wsms-bg-primary/10">
                <Bell className="wsms-h-[18px] wsms-w-[18px] wsms-text-primary" strokeWidth={2} />
              </div>
              <div>
                <h2 className="wsms-text-base wsms-font-semibold wsms-text-foreground wsms-tracking-tight">
                  Notifications
                </h2>
                {totalCount > 0 && (
                  <p className="wsms-text-xs wsms-text-muted-foreground wsms-mt-0.5">
                    {inboxNotifications.length} unread
                  </p>
                )}
              </div>
            </div>

            <button
              onClick={onClose}
              className={cn(
                'wsms-flex wsms-items-center wsms-justify-center wsms-h-8 wsms-w-8 wsms-rounded-lg',
                'wsms-text-muted-foreground wsms-transition-all wsms-duration-200',
                'hover:wsms-bg-accent hover:wsms-text-foreground',
                'active:wsms-scale-95',
                'focus:wsms-outline-none focus-visible:wsms-ring-2 focus-visible:wsms-ring-primary/50'
              )}
              aria-label="Close notifications"
            >
              <X className="wsms-h-[18px] wsms-w-[18px]" strokeWidth={2} />
            </button>
          </div>
        </div>

        {/* Tabs section */}
        <div className="wsms-flex wsms-items-center wsms-justify-between wsms-px-5 wsms-py-1 wsms-border-b wsms-border-border/50 wsms-bg-card/50">
          <div className="wsms-flex wsms-gap-5">
            <TabButton
              active={activeTab === 'inbox'}
              onClick={() => setActiveTab('inbox')}
              count={inboxNotifications.length}
            >
              Inbox
            </TabButton>
            <TabButton
              active={activeTab === 'dismissed'}
              onClick={() => setActiveTab('dismissed')}
              count={dismissedNotifications.length}
            >
              Dismissed
            </TabButton>
          </div>

          {/* Dismiss all button */}
          {activeTab === 'inbox' && inboxNotifications.length > 1 && (
            <button
              onClick={onDismissAll}
              className={cn(
                'wsms-flex wsms-items-center wsms-gap-1.5 wsms-px-2.5 wsms-py-1.5',
                'wsms-text-xs wsms-font-medium wsms-text-muted-foreground wsms-rounded-md',
                'wsms-transition-all wsms-duration-200',
                'hover:wsms-text-foreground hover:wsms-bg-accent',
                'active:wsms-scale-95'
              )}
            >
              <Trash2 className="wsms-h-3.5 wsms-w-3.5" />
              <span>Clear all</span>
            </button>
          )}
        </div>

        {/* Content area */}
        <div
          ref={contentRef}
          className={cn(
            'wsms-flex-1 wsms-overflow-y-auto wsms-overscroll-contain',
            'wsms-scrollbar-thin wsms-bg-muted/30'
          )}
        >
          {loading ? (
            <div className="wsms-flex wsms-flex-col wsms-items-center wsms-justify-center wsms-py-20">
              <div className="wsms-relative">
                <div className="wsms-absolute wsms-inset-0 wsms-bg-primary/20 wsms-rounded-full wsms-blur-xl wsms-animate-pulse" />
                <Loader2 className="wsms-relative wsms-h-8 wsms-w-8 wsms-animate-spin wsms-text-primary" strokeWidth={2} />
              </div>
              <p className="wsms-text-sm wsms-text-muted-foreground wsms-mt-4">Loading notifications...</p>
            </div>
          ) : currentNotifications.length === 0 ? (
            <EmptyState tab={activeTab} />
          ) : (
            <div className="wsms-p-4 wsms-space-y-3">
              {currentNotifications.map((notification, index) => (
                <div
                  key={notification.id}
                  className="wsms-animate-in"
                  style={{
                    animationDelay: `${index * 50}ms`,
                    animationFillMode: 'both'
                  }}
                >
                  <NotificationCard
                    notification={notification}
                    onDismiss={onDismiss}
                    showDismiss={activeTab === 'inbox'}
                  />
                </div>
              ))}
            </div>
          )}
        </div>

        {/* Footer with subtle branding */}
        <div className="wsms-flex-shrink-0 wsms-px-5 wsms-py-3 wsms-border-t wsms-border-border/50 wsms-bg-card/80">
          <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-text-center">
            Stay updated with the latest from WSMS
          </p>
        </div>
      </div>
    </>
  )
}

export default NotificationSidebar
