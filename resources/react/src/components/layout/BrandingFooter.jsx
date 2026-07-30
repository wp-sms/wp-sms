import { __, sprintf } from '@wordpress/i18n'
import React, { memo, useState } from 'react'
import { Star } from 'lucide-react'

const brandingUrls = {
  docs: 'https://wsms.io/docs/',
  support: 'https://wsms.io/support/',
  changelog: 'https://wsms.io/changelog/',
  rate: 'https://wordpress.org/support/plugin/wp-sms/reviews/#new-post',
}

const linkClass = 'hover:wsms-text-foreground wsms-transition-colors'

function Separator() {
  return (
    <span aria-hidden="true" className="wsms-opacity-50">
      ·
    </span>
  )
}

/**
 * WSMS Logo Icon - Geometric parallelogram shapes
 */
function LogoIcon({ className }) {
  return (
    <svg
      width="26"
      height="36"
      viewBox="0 0 26 36"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      aria-hidden="true"
    >
      <path
        d="M0 9.53752V17.7335L18.2101 8.17764V0L0 9.53752Z"
        fill="currentColor"
      />
      <path
        d="M0 20.729V28.9067L26 15.2711V7.09351L0 20.729Z"
        fill="currentColor"
      />
      <path
        d="M25.9972 18.2666V26.3524L7.80734 36.0001L7.78711 27.7306L25.9972 18.2666Z"
        fill="currentColor"
      />
    </svg>
  )
}

/**
 * Heart Icon with gentle pulse animation
 */
function HeartIcon({ className }) {
  return (
    <svg
      width="14"
      height="14"
      viewBox="0 0 24 24"
      fill="currentColor"
      xmlns="http://www.w3.org/2000/svg"
      className={className}
      aria-hidden="true"
    >
      <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z" />
    </svg>
  )
}

/**
 * RateLink - Review link with stars that light up individually on hover
 *
 * Hovering the link lights all five in sequence; hovering a single star
 * lights up to that star, so each star reacts on its own.
 */
function RateLink() {
  const [isLinkHovered, setIsLinkHovered] = useState(false)
  const [hoveredStar, setHoveredStar] = useState(0)

  // A hovered star wins over the whole-link hover
  const filledCount = hoveredStar || (isLinkHovered ? 5 : 0)

  const resetHover = () => {
    setIsLinkHovered(false)
    setHoveredStar(0)
  }

  return (
    <a
      href={brandingUrls.rate}
      target="_blank"
      rel="noopener noreferrer"
      title={__('Leave a review on WordPress.org', 'wp-sms')}
      className="wsms-inline-flex wsms-items-center wsms-gap-1.5 hover:wsms-text-foreground wsms-transition-colors"
      onMouseEnter={() => setIsLinkHovered(true)}
      onMouseLeave={resetHover}
      onFocus={() => setIsLinkHovered(true)}
      onBlur={resetHover}
    >
      <span>{__('Rate us', 'wp-sms')}</span>
      <span
        className="wsms-inline-flex wsms-items-center wsms-gap-0.5"
        aria-hidden="true"
        onMouseLeave={() => setHoveredStar(0)}
      >
        {[1, 2, 3, 4, 5].map((star) => (
          <span
            key={star}
            className="wsms-inline-flex wsms-p-px"
            onMouseEnter={() => setHoveredStar(star)}
          >
            <Star
              className={
                star <= filledCount
                  ? 'wsms-h-3 wsms-w-3 wsms-text-amber-400 wsms-fill-amber-400 wsms-transition-all wsms-duration-150'
                  : 'wsms-h-3 wsms-w-3 wsms-text-amber-400/40 wsms-transition-all wsms-duration-150'
              }
              // Stagger only the sweep triggered by hovering the link itself
              style={{ transitionDelay: hoveredStar ? '0ms' : `${star * 40}ms` }}
            />
          </span>
        ))}
      </span>
    </a>
  )
}

/**
 * BrandingFooter - Compact community footer
 *
 * One row: logo and community message on one side, help links,
 * review link and version on the other. Stacks on narrow screens.
 */
const BrandingFooter = memo(function BrandingFooter() {
  const version = window.wpSmsSettings?.version || '7.0'

  return (
    <div className="wsms-mt-10 wsms-mb-6 wsms-border-t wsms-border-border/60 wsms-pt-4">
      <div className="wsms-flex wsms-flex-col wsms-items-center wsms-justify-between wsms-gap-2 sm:wsms-flex-row sm:wsms-gap-4">
        {/* Logo and community message */}
        <p className="wsms-flex wsms-items-center wsms-gap-2 wsms-text-[11px] wsms-text-muted-foreground">
          <LogoIcon className="wsms-h-4 wsms-w-auto wsms-shrink-0 wsms-text-muted-foreground/40" />
          <span className="wsms-flex wsms-items-center wsms-gap-1">
            <span>{__('Made with', 'wp-sms')}</span>
            <HeartIcon className="wsms-h-3 wsms-w-3 wsms-text-rose-400/70" />
            <span>{__('for the WordPress community', 'wp-sms')}</span>
          </span>
        </p>

        {/* Help links, review link and version */}
        <p className="wsms-flex wsms-flex-wrap wsms-items-center wsms-justify-center wsms-gap-x-2 wsms-gap-y-1 wsms-text-[11px] wsms-text-muted-foreground">
          <a
            href={brandingUrls.docs}
            target="_blank"
            rel="noopener noreferrer"
            className={linkClass}
          >
            {__('Documentation', 'wp-sms')}
          </a>
          <Separator />
          <a
            href={brandingUrls.support}
            target="_blank"
            rel="noopener noreferrer"
            className={linkClass}
          >
            {__('Support', 'wp-sms')}
          </a>
          <Separator />
          <RateLink />
          <Separator />
          <a
            href={brandingUrls.changelog}
            target="_blank"
            rel="noopener noreferrer"
            title={__("What's New", 'wp-sms')}
            className={linkClass}
          >
            {sprintf(/* translators: %s: plugin version number. */ __('v%s', 'wp-sms'), version)}
          </a>
        </p>
      </div>
    </div>
  )
})

export { BrandingFooter, LogoIcon }
export default BrandingFooter
