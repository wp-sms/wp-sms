import { __ } from '@wordpress/i18n'
import React, { useState, useEffect } from 'react'
import { ExternalLink, X, Clock } from 'lucide-react'
import { cn } from '@/lib/utils'

/**
 * Background color variants with refined styling
 */
const colorVariants = {
  danger: {
    card: 'wsms-bg-red-50 wsms-border-red-200 dark:wsms-bg-red-500/10 dark:wsms-border-red-400/30',
    border: '',
    icon: 'wsms-bg-red-100 dark:wsms-bg-red-900/30',
    glow: 'wsms-shadow-red-500/5'
  },
  info: {
    card: 'wsms-bg-blue-50 wsms-border-blue-200 dark:wsms-bg-blue-500/10 dark:wsms-border-blue-400/30',
    border: '',
    icon: 'wsms-bg-blue-100 dark:wsms-bg-blue-900/30',
    glow: 'wsms-shadow-blue-500/5'
  },
  warning: {
    card: 'wsms-bg-amber-50 wsms-border-amber-200 dark:wsms-bg-amber-500/10 dark:wsms-border-amber-400/30',
    border: '',
    icon: 'wsms-bg-amber-100 dark:wsms-bg-amber-900/30',
    glow: 'wsms-shadow-amber-500/5'
  },
  success: {
    card: 'wsms-bg-emerald-50 wsms-border-emerald-200 dark:wsms-bg-emerald-500/10 dark:wsms-border-emerald-400/30',
    border: '',
    icon: 'wsms-bg-emerald-100 dark:wsms-bg-emerald-900/30',
    glow: 'wsms-shadow-emerald-500/5'
  },
  promo: {
    card: 'wsms-border-transparent wsms-text-white wsms-overflow-hidden',
    border: '',
    icon: 'wsms-bg-white/15',
    glow: ''
  },
  '': {
    card: 'wsms-bg-card wsms-border-border',
    border: '',
    icon: 'wsms-bg-muted',
    glow: ''
  },
}


/**
 * Countdown line to the notification's end time (promo cards): "Ends in 19d 17h 13m".
 */
function Countdown({ endsAt }) {
  const [now, setNow] = useState(() => Date.now())

  useEffect(() => {
    const timer = setInterval(() => setNow(Date.now()), 30000)
    return () => clearInterval(timer)
  }, [])

  const left = Math.max(0, new Date(endsAt).getTime() - now)
  const d = Math.floor(left / 86400000)
  const h = Math.floor((left % 86400000) / 3600000)
  const m = Math.floor((left % 3600000) / 60000)

  return (
    <span className="wsms-text-[13px] wsms-text-white/75" dir="ltr">
      {__('Ends in', 'wp-sms')} <strong className="wsms-font-semibold wsms-tabular-nums wsms-text-white">{`${d}d ${h}h ${m}m`}</strong>
    </span>
  )
}

/**
 * Coupon code with a copy button (promo cards).
 */
function CodeChip({ code }) {
  const [copied, setCopied] = useState(false)

  const copy = () => {
    if (navigator.clipboard?.writeText) {
      navigator.clipboard.writeText(code).then(() => {
        setCopied(true)
        setTimeout(() => setCopied(false), 1500)
      })
    }
  }

  return (
    <div className="wsms-mt-3.5 wsms-flex wsms-items-center wsms-justify-between wsms-gap-3 wsms-rounded-lg wsms-border-[1.5px] wsms-border-dashed wsms-border-red-300/70 wsms-bg-white/5 wsms-py-2.5 wsms-ps-4 wsms-pe-3" dir="ltr">
      <code className="wsms-font-sans wsms-text-[19px] wsms-font-bold wsms-text-white">{code}</code>
      <button
        type="button"
        onClick={copy}
        className="wsms-rounded-md wsms-bg-white wsms-px-3 wsms-py-1 wsms-text-xs wsms-font-bold wsms-text-zinc-900 hover:wsms-bg-red-100"
      >
        {copied ? __('Copied', 'wp-sms') : __('Copy', 'wp-sms')}
      </button>
    </div>
  )
}

/**
 * Individual notification card component with refined design
 */
const promoStyle = {
  background:
    'radial-gradient(120% 90% at 100% 0%, rgba(255,77,141,.28), transparent 55%), radial-gradient(100% 80% at 0% 100%, rgba(255,154,61,.2), transparent 55%), linear-gradient(160deg,#16161c 0%,#2a0e16 100%)',
  border: '1px solid rgba(255,176,120,.35)',
}

const isInternalUrl = (url) => {
  try {
    return new URL(url, window.location.href).origin === window.location.origin
  } catch (e) {
    return false
  }
}

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
    code,
    endsAt,
    highlight,
  } = notification

  const isPromo = backgroundColor === 'promo'

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
        'wsms-group wsms-relative wsms-rounded-lg wsms-p-4',
        'wsms-border',
        'wsms-transition-all wsms-duration-300 wsms-ease-out',
        variant.card,
        variant.border,
        isExiting && 'wsms-opacity-0 wsms-translate-x-8 wsms-scale-95',
      )}
      style={isPromo ? promoStyle : undefined}
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
    >
      {isPromo && (
        <>
          <span aria-hidden="true" className="wsms-pointer-events-none wsms-absolute wsms--end-8 wsms--top-8 wsms-h-28 wsms-w-28 wsms-rounded-full wsms-bg-pink-500/25 wsms-blur-2xl" />
          <span aria-hidden="true" className="wsms-pointer-events-none wsms-absolute wsms--bottom-10 wsms--start-8 wsms-h-28 wsms-w-28 wsms-rounded-full wsms-bg-orange-500/20 wsms-blur-2xl" />
        </>
      )}
      {/* Dismiss button - positioned top right */}
      {showDismiss && !notification.localOnly && (
        <button
          onClick={handleDismiss}
          className={cn(
            'wsms-absolute wsms-top-3 wsms-end-3',
            'wsms-flex wsms-items-center wsms-justify-center wsms-h-6 wsms-w-6 wsms-rounded-md',
            'wsms-text-muted-foreground',
            'wsms-opacity-0 group-hover:wsms-opacity-100',
            'hover:wsms-bg-foreground/10 hover:wsms-text-foreground',
            'focus:wsms-outline-none focus-visible:wsms-opacity-100 focus-visible:wsms-ring-2 focus-visible:wsms-ring-primary/50'
          )}
          aria-label={__('Dismiss notification', 'wp-sms')}
        >
          <X className="wsms-h-3.5 wsms-w-3.5" strokeWidth={2.5} />
        </button>
      )}

      {/* Content layout */}
      <div className="wsms-flex wsms-gap-3.5">
        {/* Icon container */}
        {icon && !isPromo && (
          <div className={cn(
            'wsms-flex-shrink-0 wsms-flex wsms-items-center wsms-justify-center',
            'wsms-h-9 wsms-w-9 wsms-rounded-md',
            'wsms-text-xl wsms-leading-none',
            'wsms-transition-transform wsms-duration-200',
            variant.icon,
            isHovered && 'wsms-scale-105'
          )}>
            {icon}
          </div>
        )}

        {/* Main content */}
        <div className={cn('wsms-flex-1 wsms-min-w-0', !isPromo && 'wsms-pe-6')}>
          {isPromo && (
            <div className="wsms-mb-2">
              <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-3 wsms-pe-7">
                <span className="wsms-text-[11px] wsms-font-bold wsms-uppercase wsms-text-amber-200/80">{__('Black Friday', 'wp-sms')}</span>
                {endsAt && <Countdown endsAt={endsAt} />}
              </div>
              {highlight && (
                <div
                  className="wsms-mt-1 wsms-text-[38px] wsms-font-extrabold wsms-leading-none wsms-tracking-tight"
                  style={{ background: 'linear-gradient(100deg,#ffe08a 0%,#ffb454 40%,#ff6a88 100%)', WebkitBackgroundClip: 'text', backgroundClip: 'text', color: 'transparent' }}
                  dir="ltr"
                >
                  {highlight}
                </div>
              )}
            </div>
          )}

          {/* Title */}
          {title && !isPromo && (
            <p className={cn('wsms-text-[14px] wsms-font-semibold wsms-leading-snug wsms-mb-1', isPromo ? 'wsms-text-white' : 'wsms-text-foreground')}>
              {title}
            </p>
          )}

          {/* Timestamp */}
          {activatedAt && !isPromo && (
            <div className="wsms-flex wsms-items-center wsms-gap-1 wsms-mb-2">
              <Clock className="wsms-h-3 wsms-w-3 wsms-text-muted-foreground" strokeWidth={2} />
              <span className="wsms-text-[12px] wsms-text-muted-foreground wsms-font-medium">
                {activatedAt}
              </span>
            </div>
          )}

          {/* Description */}
          {description && (
            <div
              className={cn(
                'wsms-text-[14px] wsms-leading-relaxed',
                isPromo ? 'wsms-text-white/85' : 'wsms-text-muted-foreground',
                'wsms-prose wsms-prose-sm wsms-max-w-none dark:wsms-prose-invert',
                '[&_a]:wsms-text-primary [&_a]:wsms-font-medium [&_a]:wsms-no-underline [&_a]:hover:wsms-underline',
                '[&_p]:wsms-my-1.5 [&_ul]:wsms-my-1.5 [&_ol]:wsms-my-1.5'
              )}
              dangerouslySetInnerHTML={{ __html: description }}
            />
          )}

          {isPromo && code && <CodeChip code={code} />}

          {/* Action buttons */}
          {(primaryButton || secondaryButton) && (
            <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-mt-3.5 wsms-flex-wrap">
              {primaryButton && (
                <a
                  href={primaryButton.url}
                  {...(isInternalUrl(primaryButton.url) ? {} : { target: '_blank', rel: 'noopener noreferrer' })}
                  className={cn(
                    'wsms-inline-flex wsms-items-center wsms-gap-1.5',
                    'wsms-px-3.5 wsms-py-2',
                    'wsms-text-[13px] wsms-font-semibold wsms-rounded-md',
                    isPromo ? 'wsms-flex-1 wsms-justify-center wsms-py-2.5 wsms-text-[14px] wsms-font-bold wsms-text-white wsms-bg-gradient-to-r wsms-from-red-600 wsms-via-rose-600 wsms-to-orange-500 hover:wsms-from-red-700 hover:wsms-via-rose-700 hover:wsms-to-orange-600' : 'wsms-bg-primary wsms-text-primary-foreground',
                    !isPromo && 'hover:wsms-bg-primary/90'
                  )}
                >
                  {primaryButton.title}
                  {!isInternalUrl(primaryButton.url) && <ExternalLink className="wsms-h-3 wsms-w-3 wsms-opacity-70" strokeWidth={2.5} />}
                </a>
              )}

              {secondaryButton && !isPromo && (
                <a
                  href={secondaryButton.url}
                  {...(isInternalUrl(secondaryButton.url) ? {} : { target: '_blank', rel: 'noopener noreferrer' })}
                  className={cn(
                    'wsms-inline-flex wsms-items-center wsms-gap-1.5',
                    'wsms-px-3.5 wsms-py-2',
                    'wsms-text-[13px] wsms-font-medium wsms-rounded-md',
                    isPromo ? 'wsms-bg-transparent wsms-text-white wsms-border wsms-border-white/30' : 'wsms-bg-secondary wsms-text-secondary-foreground wsms-border wsms-border-border/50',
                    'hover:wsms-bg-secondary/80 hover:wsms-border-border'
                  )}
                >
                  {secondaryButton.title}
                  {!isInternalUrl(secondaryButton.url) && <ExternalLink className="wsms-h-3 wsms-w-3 wsms-opacity-50" strokeWidth={2} />}
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
