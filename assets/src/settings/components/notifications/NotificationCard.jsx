import React, { useState } from 'react'
import { ExternalLink, X, Clock } from 'lucide-react'
import { cn } from '@/lib/utils'

/**
 * Background color variants with refined styling
 */
const colorVariants = {
  danger: {
    card: 'wsms-bg-gradient-to-br wsms-from-red-50 wsms-to-red-50/50 dark:wsms-from-red-950/30 dark:wsms-to-red-950/10',
    border: 'wsms-border-l-red-500',
    icon: 'wsms-bg-red-100 dark:wsms-bg-red-900/30',
    glow: 'wsms-shadow-red-500/5'
  },
  info: {
    card: 'wsms-bg-gradient-to-br wsms-from-blue-50 wsms-to-blue-50/50 dark:wsms-from-blue-950/30 dark:wsms-to-blue-950/10',
    border: 'wsms-border-l-blue-500',
    icon: 'wsms-bg-blue-100 dark:wsms-bg-blue-900/30',
    glow: 'wsms-shadow-blue-500/5'
  },
  warning: {
    card: 'wsms-bg-gradient-to-br wsms-from-amber-50 wsms-to-amber-50/50 dark:wsms-from-amber-950/30 dark:wsms-to-amber-950/10',
    border: 'wsms-border-l-amber-500',
    icon: 'wsms-bg-amber-100 dark:wsms-bg-amber-900/30',
    glow: 'wsms-shadow-amber-500/5'
  },
  success: {
    card: 'wsms-bg-gradient-to-br wsms-from-emerald-50 wsms-to-emerald-50/50 dark:wsms-from-emerald-950/30 dark:wsms-to-emerald-950/10',
    border: 'wsms-border-l-emerald-500',
    icon: 'wsms-bg-emerald-100 dark:wsms-bg-emerald-900/30',
    glow: 'wsms-shadow-emerald-500/5'
  },
  '': {
    card: 'wsms-bg-card',
    border: 'wsms-border-l-border',
    icon: 'wsms-bg-muted',
    glow: ''
  },
}

/**
 * Individual notification card component with refined design
 */
export function NotificationCard({ notification, onDismiss, showDismiss = true }) {
  const [isExiting, setIsExiting] = useState(false)
  const [isHovered, setIsHovered] = useState(false)

  const {
    id,
    title,
    icon,
    description,
    activatedAt,
    backgroundColor,
    primaryButton,
    secondaryButton,
  } = notification

  const variant = colorVariants[backgroundColor] || colorVariants['']

  const handleDismiss = (e) => {
    e.preventDefault()
    e.stopPropagation()
    setIsExiting(true)
    setTimeout(() => {
      onDismiss?.(id)
    }, 250)
  }

  return (
    <div
      className={cn(
        'wsms-group wsms-relative wsms-rounded-xl wsms-p-4',
        'wsms-border-l-[3px] wsms-border wsms-border-border/40',
        'wsms-transition-all wsms-duration-300 wsms-ease-out',
        'hover:wsms-shadow-lg',
        variant.card,
        variant.border,
        variant.glow,
        isExiting && 'wsms-opacity-0 wsms-translate-x-8 wsms-scale-95',
        isHovered && 'wsms-shadow-md'
      )}
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
    >
      {/* Dismiss button - positioned top right */}
      {showDismiss && (
        <button
          onClick={handleDismiss}
          className={cn(
            'wsms-absolute wsms-top-3 wsms-right-3',
            'wsms-flex wsms-items-center wsms-justify-center wsms-h-6 wsms-w-6 wsms-rounded-md',
            'wsms-text-muted-foreground wsms-transition-all wsms-duration-200',
            'wsms-opacity-0 group-hover:wsms-opacity-100',
            'hover:wsms-bg-foreground/10 hover:wsms-text-foreground',
            'active:wsms-scale-90',
            'focus:wsms-outline-none focus-visible:wsms-opacity-100 focus-visible:wsms-ring-2 focus-visible:wsms-ring-primary/50'
          )}
          aria-label="Dismiss notification"
        >
          <X className="wsms-h-3.5 wsms-w-3.5" strokeWidth={2.5} />
        </button>
      )}

      {/* Content layout */}
      <div className="wsms-flex wsms-gap-3.5">
        {/* Icon container */}
        {icon && (
          <div className={cn(
            'wsms-flex-shrink-0 wsms-flex wsms-items-center wsms-justify-center',
            'wsms-h-10 wsms-w-10 wsms-rounded-lg',
            'wsms-text-xl wsms-leading-none',
            'wsms-transition-transform wsms-duration-200',
            variant.icon,
            isHovered && 'wsms-scale-105'
          )}>
            {icon}
          </div>
        )}

        {/* Main content */}
        <div className="wsms-flex-1 wsms-min-w-0 wsms-pr-6">
          {/* Title */}
          {title && (
            <h4 className="wsms-text-[13px] wsms-font-semibold wsms-text-foreground wsms-leading-snug wsms-mb-1">
              {title}
            </h4>
          )}

          {/* Timestamp */}
          {activatedAt && (
            <div className="wsms-flex wsms-items-center wsms-gap-1 wsms-mb-2">
              <Clock className="wsms-h-3 wsms-w-3 wsms-text-muted-foreground" strokeWidth={2} />
              <span className="wsms-text-[11px] wsms-text-muted-foreground wsms-font-medium">
                {activatedAt}
              </span>
            </div>
          )}

          {/* Description */}
          {description && (
            <div
              className={cn(
                'wsms-text-[13px] wsms-text-muted-foreground wsms-leading-relaxed',
                'wsms-prose wsms-prose-sm wsms-max-w-none dark:wsms-prose-invert',
                '[&_a]:wsms-text-primary [&_a]:wsms-font-medium [&_a]:wsms-no-underline [&_a]:hover:wsms-underline',
                '[&_p]:wsms-my-1.5 [&_ul]:wsms-my-1.5 [&_ol]:wsms-my-1.5'
              )}
              dangerouslySetInnerHTML={{ __html: description }}
            />
          )}

          {/* Action buttons */}
          {(primaryButton || secondaryButton) && (
            <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-mt-3.5 wsms-flex-wrap">
              {primaryButton && (
                <a
                  href={primaryButton.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className={cn(
                    'wsms-inline-flex wsms-items-center wsms-gap-1.5',
                    'wsms-px-3.5 wsms-py-2',
                    'wsms-text-[12px] wsms-font-semibold wsms-rounded-lg',
                    'wsms-bg-primary wsms-text-primary-foreground',
                    'wsms-shadow-sm wsms-shadow-primary/20',
                    'wsms-transition-all wsms-duration-200',
                    'hover:wsms-bg-primary/90 hover:wsms-shadow-md hover:wsms-shadow-primary/25',
                    'hover:wsms--translate-y-px',
                    'active:wsms-scale-[0.98]'
                  )}
                >
                  {primaryButton.title}
                  <ExternalLink className="wsms-h-3 wsms-w-3 wsms-opacity-70" strokeWidth={2.5} />
                </a>
              )}

              {secondaryButton && (
                <a
                  href={secondaryButton.url}
                  target="_blank"
                  rel="noopener noreferrer"
                  className={cn(
                    'wsms-inline-flex wsms-items-center wsms-gap-1.5',
                    'wsms-px-3.5 wsms-py-2',
                    'wsms-text-[12px] wsms-font-medium wsms-rounded-lg',
                    'wsms-bg-secondary wsms-text-secondary-foreground',
                    'wsms-border wsms-border-border/50',
                    'wsms-transition-all wsms-duration-200',
                    'hover:wsms-bg-secondary/80 hover:wsms-border-border',
                    'active:wsms-scale-[0.98]'
                  )}
                >
                  {secondaryButton.title}
                  <ExternalLink className="wsms-h-3 wsms-w-3 wsms-opacity-50" strokeWidth={2} />
                </a>
              )}
            </div>
          )}
        </div>
      </div>
    </div>
  )
}

export default NotificationCard
