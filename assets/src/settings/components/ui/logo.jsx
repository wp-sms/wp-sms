import React from 'react'
import { cn } from '@/lib/utils'

/**
 * WP SMS Logo component
 */
export function Logo({ className, ...props }) {
  return (
    <svg
      width="26"
      height="36"
      viewBox="0 0 26 36"
      fill="none"
      xmlns="http://www.w3.org/2000/svg"
      className={cn('wsms-text-primary', className)}
      aria-hidden="true"
      {...props}
    >
      <path d="M0 9.53752V17.7335L18.2101 8.17764V0L0 9.53752Z" fill="currentColor" />
      <path d="M0 20.729V28.9067L26 15.2711V7.09351L0 20.729Z" fill="currentColor" />
      <path d="M25.9972 18.2666V26.3524L7.80734 36.0001L7.78711 27.7306L25.9972 18.2666Z" fill="currentColor" />
    </svg>
  )
}

export default Logo
