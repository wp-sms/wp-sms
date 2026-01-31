import React from 'react'
import { CheckCircle } from 'lucide-react'
import { cn } from '@/lib/utils'

/**
 * Full gateway card — used when API data is available (logo, features, premium badge)
 */
export function GatewayCard({ gateway, isSelected, onClick, showFeatures = false, featureTags = [] }) {
  return (
    <button
      key={gateway.slug}
      type="button"
      onClick={() => onClick(gateway.slug)}
      className={cn(
        'wsms-flex wsms-items-center wsms-gap-2 wsms-rounded-md wsms-border wsms-px-3 wsms-py-2 wsms-text-left wsms-text-[12px] wsms-transition-colors',
        isSelected
          ? 'wsms-border-primary wsms-bg-primary/10 wsms-text-primary wsms-font-medium'
          : 'wsms-border-border wsms-bg-card hover:wsms-bg-accent'
      )}
    >
      {gateway.logo ? (
        <img
          src={gateway.logo}
          alt=""
          className="wsms-h-5 wsms-w-5 wsms-shrink-0 wsms-rounded-sm wsms-object-contain"
          loading="lazy"
          onError={(e) => { e.target.style.display = 'none' }}
        />
      ) : isSelected ? (
        <CheckCircle className="wsms-h-3.5 wsms-w-3.5 wsms-shrink-0" />
      ) : null}
      <div className="wsms-min-w-0 wsms-flex-1">
        <span className="wsms-truncate wsms-block">{gateway.name}</span>
        {showFeatures && featureTags.length > 0 && (
          <span className="wsms-text-[9px] wsms-text-muted-foreground wsms-truncate wsms-block">
            {featureTags.join(' · ')}
          </span>
        )}
      </div>
      {gateway.premium && (
        <span className="wsms-ml-auto wsms-shrink-0 wsms-rounded wsms-bg-amber-100 wsms-px-1 wsms-py-0.5 wsms-text-[9px] wsms-font-medium wsms-text-amber-700 dark:wsms-bg-amber-900/30 dark:wsms-text-amber-400">
          PRO
        </span>
      )}
    </button>
  )
}

/**
 * Minimal gateway card — used when local fallback (just name + selected check)
 */
export function GatewayCardMinimal({ gateway, isSelected, onClick }) {
  return (
    <button
      key={gateway.slug}
      type="button"
      onClick={() => onClick(gateway.slug)}
      className={cn(
        'wsms-flex wsms-items-center wsms-gap-2 wsms-rounded-md wsms-border wsms-px-3 wsms-py-2 wsms-text-left wsms-text-[12px] wsms-transition-colors',
        isSelected
          ? 'wsms-border-primary wsms-bg-primary/10 wsms-text-primary wsms-font-medium'
          : 'wsms-border-border wsms-bg-card hover:wsms-bg-accent'
      )}
    >
      {isSelected && <CheckCircle className="wsms-h-3.5 wsms-w-3.5 wsms-shrink-0" />}
      <span className="wsms-truncate">{gateway.name}</span>
    </button>
  )
}
