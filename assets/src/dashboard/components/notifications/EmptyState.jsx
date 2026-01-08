import React from 'react'
import { Inbox, CheckCheck, Sparkles } from 'lucide-react'
import { cn } from '@/lib/utils'

/**
 * Decorative floating dots for visual interest
 */
function FloatingDots({ variant }) {
  const baseClasses = 'wsms-absolute wsms-rounded-full wsms-opacity-60'
  const colors = variant === 'inbox'
    ? 'wsms-bg-primary/20'
    : 'wsms-bg-emerald-500/20'

  return (
    <>
      <div className={cn(baseClasses, colors, 'wsms-w-2 wsms-h-2 wsms-top-4 wsms-left-8 wsms-animate-pulse')} style={{ animationDelay: '0ms' }} />
      <div className={cn(baseClasses, colors, 'wsms-w-1.5 wsms-h-1.5 wsms-top-12 wsms-right-12 wsms-animate-pulse')} style={{ animationDelay: '300ms' }} />
      <div className={cn(baseClasses, colors, 'wsms-w-2.5 wsms-h-2.5 wsms-bottom-16 wsms-left-16 wsms-animate-pulse')} style={{ animationDelay: '600ms' }} />
      <div className={cn(baseClasses, colors, 'wsms-w-1 wsms-h-1 wsms-bottom-8 wsms-right-8 wsms-animate-pulse')} style={{ animationDelay: '900ms' }} />
    </>
  )
}

/**
 * Empty state component for notification tabs with refined design
 */
export function EmptyState({ tab = 'inbox' }) {
  const isInbox = tab === 'inbox'

  return (
    <div className="wsms-relative wsms-flex wsms-flex-col wsms-items-center wsms-justify-center wsms-py-20 wsms-px-8 wsms-text-center wsms-overflow-hidden">
      {/* Decorative elements */}
      <FloatingDots variant={tab} />

      {/* Background gradient glow */}
      <div className={cn(
        'wsms-absolute wsms-w-48 wsms-h-48 wsms-rounded-full wsms-blur-3xl wsms-opacity-30',
        isInbox
          ? 'wsms-bg-primary/30'
          : 'wsms-bg-emerald-500/30'
      )} />

      {/* Icon container with layered effect */}
      <div className="wsms-relative wsms-mb-6">
        {/* Outer ring */}
        <div className={cn(
          'wsms-absolute wsms-inset-0 wsms-rounded-2xl wsms-scale-125 wsms-opacity-20',
          isInbox
            ? 'wsms-bg-gradient-to-br wsms-from-primary/40 wsms-to-transparent'
            : 'wsms-bg-gradient-to-br wsms-from-emerald-500/40 wsms-to-transparent'
        )} />

        {/* Main icon container */}
        <div className={cn(
          'wsms-relative wsms-flex wsms-items-center wsms-justify-center',
          'wsms-h-20 wsms-w-20 wsms-rounded-2xl',
          'wsms-transition-transform wsms-duration-300',
          'hover:wsms-scale-105',
          isInbox
            ? 'wsms-bg-gradient-to-br wsms-from-primary/15 wsms-to-primary/5 wsms-shadow-lg wsms-shadow-primary/10'
            : 'wsms-bg-gradient-to-br wsms-from-emerald-500/15 wsms-to-emerald-500/5 wsms-shadow-lg wsms-shadow-emerald-500/10'
        )}>
          {isInbox ? (
            <Inbox
              className="wsms-h-9 wsms-w-9 wsms-text-primary"
              strokeWidth={1.5}
            />
          ) : (
            <CheckCheck
              className="wsms-h-9 wsms-w-9 wsms-text-emerald-500"
              strokeWidth={1.5}
            />
          )}

          {/* Sparkle accent */}
          <Sparkles
            className={cn(
              'wsms-absolute wsms--top-1 wsms--right-1 wsms-h-4 wsms-w-4',
              isInbox ? 'wsms-text-primary/60' : 'wsms-text-emerald-500/60'
            )}
            strokeWidth={2}
          />
        </div>
      </div>

      {/* Text content */}
      <h3 className={cn(
        'wsms-text-lg wsms-font-semibold wsms-tracking-tight wsms-mb-2',
        'wsms-bg-clip-text wsms-text-transparent',
        isInbox
          ? 'wsms-bg-gradient-to-r wsms-from-foreground wsms-to-foreground/70'
          : 'wsms-bg-gradient-to-r wsms-from-emerald-600 wsms-to-emerald-500 dark:wsms-from-emerald-400 dark:wsms-to-emerald-300'
      )}>
        {isInbox ? "You're all caught up!" : 'All clear'}
      </h3>

      <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-max-w-[220px] wsms-leading-relaxed">
        {isInbox
          ? 'No new notifications. Check back later for updates.'
          : 'Dismissed notifications are cleared. Nice and tidy!'}
      </p>

      {/* Decorative bottom line */}
      <div className={cn(
        'wsms-mt-8 wsms-h-1 wsms-w-12 wsms-rounded-full',
        isInbox
          ? 'wsms-bg-gradient-to-r wsms-from-transparent wsms-via-primary/30 wsms-to-transparent'
          : 'wsms-bg-gradient-to-r wsms-from-transparent wsms-via-emerald-500/30 wsms-to-transparent'
      )} />
    </div>
  )
}

export default EmptyState
