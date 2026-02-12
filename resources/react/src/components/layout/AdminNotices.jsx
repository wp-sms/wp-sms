import React, { memo, useCallback, useState, useMemo } from 'react'
import { AlertTriangle, Info, X, ExternalLink } from 'lucide-react'
import { useAdminNotices } from '@/hooks/useAdminNotices'
import { useSettings } from '@/context/SettingsContext'
import { cn, __ } from '@/lib/utils'

/**
 * Variant styles following the Tip / NotificationCard design language:
 * - Subtle background gradient
 * - Colored left border for visual weight
 * - High-contrast foreground text (not colored text)
 */
const variants = {
  warning: {
    container:
      'wsms-bg-gradient-to-r wsms-from-amber-50 wsms-to-amber-50/30 dark:wsms-from-amber-950/20 dark:wsms-to-transparent wsms-border-amber-400',
    icon: 'wsms-text-amber-600 dark:wsms-text-amber-400',
    IconComponent: AlertTriangle,
  },
  info: {
    container:
      'wsms-bg-gradient-to-r wsms-from-blue-50 wsms-to-blue-50/30 dark:wsms-from-blue-950/20 dark:wsms-to-transparent wsms-border-blue-400',
    icon: 'wsms-text-blue-600 dark:wsms-text-blue-400',
    IconComponent: Info,
  },
}

function NoticeItem({ notice, onDismiss, onAction, onInlineLink, onNavigate }) {
  const [isExiting, setIsExiting] = useState(false)
  const v = variants[notice.variant] || variants.warning
  const Icon = v.IconComponent

  const handleDismiss = () => {
    setIsExiting(true)
    setTimeout(() => onDismiss(notice.id, notice.dismissStore), 200)
  }

  return (
    <div
      className={cn(
        'wsms-flex wsms-items-start wsms-gap-3 wsms-p-3 wsms-rounded-lg',
        'wsms-border wsms-border-s-[3px]',
        'wsms-transition-all wsms-duration-200',
        v.container,
        isExiting && 'wsms-opacity-0 wsms-scale-[0.98]'
      )}
    >
      <Icon className={cn('wsms-h-4 wsms-w-4 wsms-shrink-0 wsms-mt-0.5', v.icon)} />

      <div className="wsms-flex-1 wsms-min-w-0">
        {notice.title && (
          <p className="wsms-text-[13px] wsms-font-semibold wsms-text-foreground wsms-mb-0.5">
            {notice.title}
          </p>
        )}
        <div
          className={cn(
            'wsms-text-[12px] wsms-text-foreground/80 wsms-leading-relaxed',
            '[&_a]:wsms-text-primary [&_a]:wsms-font-medium [&_a]:wsms-underline [&_a]:wsms-underline-offset-2 [&_a]:hover:wsms-opacity-80'
          )}
          onClick={onInlineLink}
          dangerouslySetInnerHTML={{ __html: notice.message }}
        />

        {notice.type === 'action' && notice.actions?.length > 0 && (
          <div className="wsms-flex wsms-flex-wrap wsms-items-center wsms-gap-2 wsms-mt-2">
            {notice.actions.map((action, i) =>
              action.url ? (
                <a
                  key={i}
                  href={action.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[12px] wsms-font-medium wsms-text-primary hover:wsms-underline"
                >
                  {action.label}
                  <ExternalLink className="wsms-h-3 wsms-w-3 wsms-opacity-60" />
                </a>
              ) : action.navigate ? (
                <button
                  key={i}
                  type="button"
                  onClick={() => onNavigate(action.navigate)}
                  className="wsms-inline-flex wsms-items-center wsms-px-2.5 wsms-py-1 wsms-text-[11px] wsms-font-medium wsms-rounded-md wsms-border wsms-border-border wsms-bg-card wsms-text-foreground hover:wsms-border-blue-400 hover:wsms-text-blue-600 dark:hover:wsms-text-blue-400 wsms-transition-colors"
                >
                  {action.label}
                </button>
              ) : (
                <button
                  key={i}
                  type="button"
                  onClick={() => onAction(notice.id, action)}
                  className="wsms-inline-flex wsms-items-center wsms-px-2.5 wsms-py-1 wsms-text-[11px] wsms-font-medium wsms-rounded-md wsms-border wsms-border-border wsms-bg-card wsms-text-foreground hover:wsms-border-blue-400 hover:wsms-text-blue-600 dark:hover:wsms-text-blue-400 wsms-transition-colors"
                >
                  {action.label}
                </button>
              )
            )}
          </div>
        )}
      </div>

      {notice.dismissible && (
        <button
          type="button"
          onClick={handleDismiss}
          className="wsms-shrink-0 wsms-p-1 wsms-rounded wsms-text-muted-foreground hover:wsms-text-foreground hover:wsms-bg-foreground/5 wsms-transition-colors"
          aria-label={__('Dismiss')}
        >
          <X className="wsms-h-3.5 wsms-w-3.5" />
        </button>
      )}
    </div>
  )
}

const AdminNotices = memo(function AdminNotices() {
  const { notices, dismissNotice, executeAction, hasNotices } = useAdminNotices()
  const { currentPage, setCurrentPage } = useSettings()

  // Filter notices by current tab — matches legacy page-conditional behavior
  const visibleNotices = useMemo(
    () => notices.filter((n) => !n.showOnTab || n.showOnTab === currentPage),
    [notices, currentPage]
  )

  const handleInlineLink = useCallback(
    (e) => {
      const anchor = e.target.closest('a')
      if (!anchor) return
      const href = anchor.getAttribute('href')
      if (!href) return
      const tabMatch = href.match(/[?&]tab=([^&#]+)/)
      if (tabMatch) {
        e.preventDefault()
        setCurrentPage(tabMatch[1])
      }
    },
    [setCurrentPage]
  )

  const handleNavigate = useCallback(
    (target) => {
      if (target === 'wizard') {
        // Open wizard by adding ?wizard=open param — SetupWizard reads it on mount
        const url = new URL(window.location.href)
        url.searchParams.set('wizard', 'open')
        window.location.href = url.toString()
      }
    },
    []
  )

  if (!hasNotices || visibleNotices.length === 0) return null

  return (
    <div className="wsms-flex wsms-flex-col wsms-gap-2 wsms-mb-4">
      {visibleNotices.map((notice) => (
        <NoticeItem
          key={notice.id}
          notice={notice}
          onDismiss={dismissNotice}
          onAction={executeAction}
          onInlineLink={handleInlineLink}
          onNavigate={handleNavigate}
        />
      ))}
    </div>
  )
})

export default AdminNotices
