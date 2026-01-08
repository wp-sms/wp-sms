import React, { memo } from 'react'
import { __ } from '@/lib/utils'

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
 * BrandingFooter - Warm community-focused footer
 *
 * Celebrates the WordPress community with genuine warmth.
 * Logo + heartfelt message that feels authentic, not corporate.
 */
const BrandingFooter = memo(function BrandingFooter() {
  return (
    <footer className="wsms-mt-20 wsms-mb-10 wsms-flex wsms-flex-col wsms-items-center wsms-justify-center wsms-gap-4">
      {/* Logo container with refined hover interaction */}
      <div className="wsms-group wsms-relative wsms-flex wsms-items-center wsms-justify-center wsms-p-4 wsms-cursor-default">
        {/* Subtle glow effect on hover */}
        <div
          className="wsms-absolute wsms-inset-0 wsms-rounded-full wsms-bg-primary/0 wsms-blur-xl wsms-transition-all wsms-duration-700 group-hover:wsms-bg-primary/5"
          aria-hidden="true"
        />

        {/* Logo with smooth color transition */}
        <LogoIcon
          className="wsms-relative wsms-h-12 wsms-w-auto wsms-text-muted-foreground/25 wsms-transition-all wsms-duration-500 wsms-ease-out group-hover:wsms-text-primary/40 group-hover:wsms-scale-110"
        />
      </div>

      {/* Community message */}
      <p className="wsms-flex wsms-items-center wsms-gap-1.5 wsms-text-[12px] wsms-text-muted-foreground/50">
        <span>{__('Made with')}</span>
        <HeartIcon className="wsms-h-3.5 wsms-w-3.5 wsms-text-rose-400/70" />
        <span>{__('for the WordPress community')}</span>
      </p>
    </footer>
  )
})

export { BrandingFooter, LogoIcon }
export default BrandingFooter
