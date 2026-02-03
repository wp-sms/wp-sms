import React from 'react'
import { CheckCircle, Lock } from 'lucide-react'
import { cn, __, getGatewayLogo } from '@/lib/utils'

/**
 * Full gateway card — used when API data is available (logo, features, premium badge)
 */
export function GatewayCard({ gateway, isSelected, isCurrent, onClick, showFeatures = false, featureTags = [] }) {
  return (
    <button
      key={gateway.slug}
      type="button"
      onClick={() => onClick(gateway.slug)}
      className={cn(
        'wsms-flex wsms-items-center wsms-gap-2 wsms-rounded-md wsms-border wsms-px-3 wsms-py-2 wsms-h-[46px] wsms-text-left wsms-text-[12px] wsms-transition-colors',
        isSelected
          ? 'wsms-border-primary wsms-bg-primary/10 wsms-text-primary wsms-font-medium'
          : isCurrent
            ? 'wsms-border-primary/40 wsms-bg-card wsms-text-muted-foreground'
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
        {gateway.website && (
          <span className="wsms-text-[9px] wsms-text-muted-foreground wsms-truncate wsms-block">
            {gateway.website.replace(/^https?:\/\//, '').replace(/\/$/, '')}
          </span>
        )}
      </div>
    </button>
  )
}

/**
 * Minimal gateway card — used when local fallback (just name + selected check)
 */
export function GatewayCardMinimal({ gateway, isSelected, isCurrent, onClick }) {
  return (
    <button
      key={gateway.slug}
      type="button"
      onClick={() => onClick(gateway.slug)}
      className={cn(
        'wsms-flex wsms-items-center wsms-gap-2 wsms-rounded-md wsms-border wsms-px-3 wsms-py-2 wsms-h-[46px] wsms-text-left wsms-text-[12px] wsms-transition-colors',
        isSelected
          ? 'wsms-border-primary wsms-bg-primary/10 wsms-text-primary wsms-font-medium'
          : isCurrent
            ? 'wsms-border-primary/40 wsms-bg-card wsms-text-muted-foreground'
            : 'wsms-border-border wsms-bg-card hover:wsms-bg-accent'
      )}
    >
      {isSelected && <CheckCircle className="wsms-h-3.5 wsms-w-3.5 wsms-shrink-0" />}
      <span className="wsms-truncate">{gateway.name}</span>
    </button>
  )
}

/**
 * Premium gateway card — links to gateway page on wsms.io
 */
export function GatewayCardPro({ gateway }) {
  return (
    <a
      href={`https://wsms.io/gateways/${gateway.slug}/`}
      target="_blank"
      rel="noopener noreferrer"
      className="wsms-flex wsms-items-center wsms-gap-2 wsms-rounded-md wsms-border wsms-border-dashed wsms-border-border wsms-px-3 wsms-py-2 wsms-h-[46px] wsms-text-left wsms-text-[12px] wsms-opacity-75 hover:wsms-opacity-100 wsms-transition-opacity"
    >
      {gateway.logo ? (
        <img
          src={gateway.logo}
          alt=""
          className="wsms-h-5 wsms-w-5 wsms-shrink-0 wsms-rounded-sm wsms-object-contain wsms-grayscale"
          loading="lazy"
          onError={(e) => { e.target.style.display = 'none' }}
        />
      ) : (
        <Lock className="wsms-h-3.5 wsms-w-3.5 wsms-shrink-0 wsms-text-muted-foreground" />
      )}
      <div className="wsms-min-w-0 wsms-flex-1">
        <span className="wsms-truncate wsms-block wsms-text-muted-foreground">{gateway.name}</span>
        {gateway.description && (
          <span className="wsms-text-[9px] wsms-text-muted-foreground/70 wsms-truncate wsms-block">
            {gateway.description}
          </span>
        )}
      </div>
      <span className="wsms-shrink-0 wsms-rounded wsms-bg-muted wsms-px-1.5 wsms-py-0.5 wsms-text-[9px] wsms-font-medium wsms-uppercase wsms-text-muted-foreground">
        {__('Pro')}
      </span>
    </a>
  )
}

/**
 * Premium search results — shown when search yields no free results but matches premium gateways
 */
export function PremiumSearchResults({ gateways, searchQuery }) {
  if (gateways.length === 0) {
    return (
      <p className="wsms-py-8 wsms-text-center wsms-text-[12px] wsms-text-muted-foreground">
        {searchQuery ? __('No gateways found matching') + ` "${searchQuery}".` : __('No gateways found.')}
      </p>
    )
  }

  return (
    <div className="wsms-space-y-4">
      <p className="wsms-text-center wsms-text-[12px] wsms-text-muted-foreground">
        {__('These gateways are available with the Pro add-on:')}
      </p>
      <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 md:wsms-grid-cols-3 lg:wsms-grid-cols-4">
        {gateways.slice(0, 8).map((g) => (
          <GatewayCardPro key={g.slug} gateway={{ ...g, logo: getGatewayLogo(g) }} />
        ))}
      </div>
      <p className="wsms-text-center wsms-text-[12px] wsms-text-muted-foreground">
        <a
          href="https://wsms.io/gateways/"
          target="_blank"
          rel="noopener noreferrer"
          className="wsms-font-medium wsms-text-primary hover:wsms-text-primary/80"
        >
          {__('View all gateways')} &rarr;
        </a>
      </p>
    </div>
  )
}

/**
 * Subtle notice bar for "more gateways available" — shown below the gateway grid
 */
export function MoreGatewaysNotice({ premiumCount, searchQuery }) {
  if (premiumCount <= 0) return null

  return (
    <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-md wsms-bg-muted/50 wsms-border wsms-border-border wsms-px-4 wsms-py-2.5">
      <p className="wsms-text-[12px] wsms-text-muted-foreground">
        {searchQuery
          ? __("Can't find your gateway?") + ` ` + __('We support') + ` ${premiumCount}+ ` + __('additional gateways.')
          : __('Looking for more gateways?') + ` ` + __('We support') + ` ${premiumCount}+ ` + __('additional gateways with the Pro add-on.')
        }
      </p>
      <a
        href="https://wsms.io/gateways/"
        target="_blank"
        rel="noopener noreferrer"
        className="wsms-shrink-0 wsms-text-[12px] wsms-font-medium wsms-text-primary hover:wsms-text-primary/80"
      >
        {__('View all gateways')} &rarr;
      </a>
    </div>
  )
}
