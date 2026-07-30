import { __, sprintf } from '@wordpress/i18n'
import React, { useState, useEffect, useCallback } from 'react'
import { ChevronRight, X } from 'lucide-react'
import { cn, getWpSettings, getGatewayDisplayName, isAddonDashboardReady } from '@/lib/utils'
import Logo from './Logo'
import useGatewayRegistry from '@/hooks/useGatewayRegistry'
import { smsApi } from '@/api/smsApi'
import { inboxApi } from '@/api/twoWayApi'
import { useSettings, useSavedSetting } from '@/context/SettingsContext'
import { getNavigation } from '@/lib/pageRegistry'

// Single-row gateway status; clicking it opens the gateway settings page
function GatewayStatus({ isConfigured, gatewayKey, onConfigure }) {
  const [credit, setCredit] = useState(null)
  const [creditSupported, setCreditSupported] = useState(null)
  const [isLoadingCredit, setIsLoadingCredit] = useState(true)
  const { gateways } = useGatewayRegistry()

  const gatewayDisplayName = getGatewayDisplayName(gatewayKey, gateways)

  const fetchCredit = useCallback(async () => {
    if (!isConfigured) return
    setIsLoadingCredit(true)
    try {
      const result = await smsApi.getCredit()
      setCredit(result.credit)
      setCreditSupported(result.creditSupported !== false)
    } catch (error) {
      console.error('Failed to fetch credit:', error)
      setCredit(null)
      setCreditSupported(false)
    } finally {
      setIsLoadingCredit(false)
    }
  }, [isConfigured])

  // Fetch credit eagerly on mount to determine connection status
  useEffect(() => {
    if (isConfigured) {
      fetchCredit()
    } else {
      setIsLoadingCredit(false)
      setCreditSupported(false)
    }
  }, [isConfigured, gatewayKey, fetchCredit])

  // Re-fetch credit when gateway test succeeds (from Gateway page)
  useEffect(() => {
    const handler = () => fetchCredit()
    window.addEventListener('wsms:gateway-tested', handler)
    return () => window.removeEventListener('wsms:gateway-tested', handler)
  }, [fetchCredit])

  // Determine connection status: connected only when credit is available
  const isConnected = creditSupported === true && credit !== null

  // Full status, always available to screen readers and as the row's tooltip
  let statusLabel
  if (!isConfigured) {
    statusLabel = __('Gateway not configured', 'wp-sms')
  } else if (isLoadingCredit) {
    statusLabel = __('Checking gateway...', 'wp-sms')
  } else if (isConnected) {
    statusLabel = __('Gateway Connected', 'wp-sms')
  } else {
    statusLabel = __('Gateway not connected', 'wp-sms')
  }

  // When connected, the dot carries the status so the row can spend its width on
  // the gateway name and credit instead of repeating "Gateway Connected"
  let visibleLabel
  if (isConnected) {
    visibleLabel = sprintf(
      /* translators: 1: gateway name. 2: remaining credit. */
      __('%1$s · %2$s credit', 'wp-sms'),
      gatewayDisplayName,
      credit
    )
  } else if (isConfigured && !isLoadingCredit) {
    visibleLabel = sprintf(
      /* translators: %s: gateway name. */
      __('%s · not connected', 'wp-sms'),
      gatewayDisplayName
    )
  } else {
    visibleLabel = statusLabel
  }

  return (
    <button
      onClick={onConfigure}
      title={`${statusLabel} — ${__('open gateway settings', 'wp-sms')}`}
      className={cn(
        'wsms-flex wsms-w-full wsms-items-start wsms-gap-2 wsms-px-3 wsms-py-2 wsms-rounded-md wsms-text-start wsms-transition-colors',
        isConnected
          ? 'wsms-bg-emerald-500/10 hover:wsms-bg-emerald-500/15'
          : 'wsms-bg-amber-500/10 hover:wsms-bg-amber-500/15'
      )}
    >
      <span
        aria-hidden="true"
        className={cn(
          'wsms-mt-[5px] wsms-inline-flex wsms-shrink-0 wsms-rounded-full wsms-h-2 wsms-w-2',
          isConnected ? 'wsms-bg-emerald-500' : 'wsms-bg-amber-500'
        )}
      />
      <span
        className={cn(
          'wsms-min-w-0 wsms-flex-1 wsms-text-[11px] wsms-font-medium wsms-leading-snug wsms-break-words',
          isConnected
            ? 'wsms-text-emerald-700 dark:wsms-text-emerald-400'
            : 'wsms-text-amber-700 dark:wsms-text-amber-400'
        )}
      >
        <span className="wsms-sr-only">{`${statusLabel}. `}</span>
        {visibleLabel}
      </span>
    </button>
  )
}

// Single nav item (leaf node)
function NavItem({ item, isActive, onClick, isNested = false, badge }) {
  const Icon = item.icon

  // For nested items, background starts after the line and extends to the right edge
  if (isNested) {
    return (
      <button
        onClick={onClick}
        aria-current={isActive ? 'page' : undefined}
        className={cn(
          'wsms-flex wsms-w-full wsms-items-center wsms-py-0.5 wsms-pe-3 wsms-ps-7 wsms-text-start wsms-transition-colors wsms-duration-150',
          isActive ? 'wsms-text-primary' : 'wsms-text-foreground/70 hover:wsms-text-foreground'
        )}
      >
        <span
          className={cn(
            'wsms-flex wsms-flex-1 wsms-items-center wsms-gap-2.5 wsms-py-1.5 wsms-px-2.5 wsms-rounded-md wsms-transition-colors wsms-duration-150',
            isActive
              ? 'wsms-bg-primary/[0.06] wsms-font-semibold'
              : 'hover:wsms-bg-primary/5'
          )}
        >
          <Icon className="wsms-h-3.5 wsms-w-3.5 wsms-shrink-0" strokeWidth={1.75} />
          <span className="wsms-text-[12px] wsms-font-medium">{item.label}</span>
          {badge > 0 && (
            <span className="wsms-ms-auto wsms-flex wsms-h-[18px] wsms-min-w-[18px] wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-primary wsms-px-1 wsms-text-[10px] wsms-font-semibold wsms-text-primary-foreground wsms-leading-none">
              {badge > 99 ? '99+' : badge}
            </span>
          )}
        </span>
      </button>
    )
  }

  return (
    <button
      onClick={onClick}
      aria-current={isActive ? 'page' : undefined}
      className={cn(
        'wsms-flex wsms-w-full wsms-items-center wsms-gap-3 wsms-rounded-md wsms-text-[13px] wsms-font-medium wsms-transition-all wsms-duration-150 wsms-text-start wsms-px-3 wsms-py-2.5',
        isActive
          ? 'wsms-bg-primary/[0.06] wsms-text-primary wsms-font-semibold'
          : 'wsms-text-foreground/70 hover:wsms-bg-primary/5 hover:wsms-text-foreground wsms-transition-colors wsms-duration-150'
      )}
    >
      <Icon className="wsms-h-4 wsms-w-4 wsms-shrink-0" strokeWidth={1.75} />
      <span>{item.label}</span>
      {item.badgeLabel && (
        <span className="wsms-ms-auto wsms-px-2 wsms-py-0.5 wsms-rounded-full wsms-bg-primary/10 wsms-text-primary wsms-text-[10px] wsms-font-medium">
          {item.badgeLabel}
        </span>
      )}
    </button>
  )
}

// Nested group component (for add-on subpages within a parent group)
function NestedNavGroup({ group, currentPage, setCurrentPage }) {
  // Check if any item in this nested group is active
  const hasActiveChild = group.items.some((item) => item.id === currentPage)

  // Initialize expanded state - expanded if has active child
  const [isExpanded, setIsExpanded] = useState(hasActiveChild)

  // Auto-expand when a child becomes active
  useEffect(() => {
    if (hasActiveChild && !isExpanded) {
      setIsExpanded(true)
    }
  }, [hasActiveChild])

  const Icon = group.icon

  return (
    <div className="wsms-ps-7">
      {/* Nested group header */}
      <button
        onClick={() => setIsExpanded(!isExpanded)}
        aria-expanded={isExpanded}
        className={cn(
          'wsms-flex wsms-w-full wsms-items-center wsms-justify-between wsms-rounded-md wsms-px-2.5 wsms-py-1.5 wsms-text-[12px] wsms-font-medium wsms-transition-all wsms-duration-150 wsms-text-start',
          hasActiveChild
            ? 'wsms-text-primary wsms-bg-primary/[0.06]'
            : 'wsms-text-foreground/70 hover:wsms-bg-primary/5 hover:wsms-text-foreground'
        )}
      >
        <div className="wsms-flex wsms-items-center wsms-gap-2">
          <Icon className="wsms-h-3.5 wsms-w-3.5 wsms-shrink-0" strokeWidth={1.75} />
          <span>{group.label}</span>
        </div>
        <ChevronRight
          className={cn(
            'wsms-h-3.5 wsms-w-3.5 wsms-text-muted-foreground wsms-transition-transform wsms-duration-200',
            isExpanded
              ? 'wsms-rotate-90'
              : 'rtl:wsms-rotate-180'
          )}
          strokeWidth={1.5}
        />
      </button>

      {/* Nested expandable content */}
      <div
        className={cn(
          'wsms-overflow-hidden wsms-transition-all wsms-duration-200 wsms-ease-out',
          isExpanded ? 'wsms-max-h-[300px] wsms-opacity-100' : 'wsms-max-h-0 wsms-opacity-0'
        )}
      >
        <div className="wsms-relative wsms-py-0.5 wsms-ps-4">
          {/* Connecting line for nested items */}
          <div className="wsms-absolute wsms-start-[14px] wsms-top-1 wsms-bottom-1 wsms-w-px wsms-bg-border/40" />

          {group.items.map((item) => {
            const ItemIcon = item.icon
            return (
              <button
                key={item.id}
                onClick={() => setCurrentPage(item.id)}
                aria-current={currentPage === item.id ? 'page' : undefined}
                className={cn(
                  'wsms-flex wsms-w-full wsms-items-center wsms-gap-2 wsms-rounded-md wsms-px-2 wsms-py-1.5 wsms-text-[11px] wsms-font-medium wsms-transition-all wsms-duration-150 wsms-text-start',
                  currentPage === item.id
                    ? 'wsms-text-primary wsms-bg-primary/[0.06]'
                    : 'wsms-text-foreground/60 hover:wsms-bg-primary/5 hover:wsms-text-foreground/80'
                )}
              >
                <ItemIcon className="wsms-h-3 wsms-w-3 wsms-shrink-0" strokeWidth={1.75} />
                <span>{item.label}</span>
              </button>
            )
          })}
        </div>
      </div>
    </div>
  )
}

// Collapsible group component
function NavGroup({ group, currentPage, setCurrentPage, conditions, badges = {} }) {
  // Check if any item in this group is active (including nested groups)
  const hasActiveChild = group.items.some((item) => {
    if (item.type === 'nested-group') {
      return item.items.some((nestedItem) => nestedItem.id === currentPage)
    }
    return item.id === currentPage
  })

  // Initialize expanded state - expanded if has active child or defaultExpanded
  const [isExpanded, setIsExpanded] = useState(hasActiveChild || group.defaultExpanded || false)

  // Auto-expand when a child becomes active
  useEffect(() => {
    if (hasActiveChild && !isExpanded) {
      setIsExpanded(true)
    }
  }, [hasActiveChild])

  // Filter items based on conditions
  const filteredItems = group.items.filter((item) => {
    if (!item.condition) return true
    return conditions[item.condition]
  })

  // Don't render empty groups
  if (filteredItems.length === 0) return null

  const Icon = group.icon

  // Sum all badges for child items to show on collapsed group header
  const groupBadgeTotal = filteredItems.reduce((sum, item) => sum + (badges[item.id] || 0), 0)

  return (
    <div className="wsms-space-y-0.5">
      {/* Group header - clickable to expand/collapse */}
      <button
        onClick={() => setIsExpanded(!isExpanded)}
        aria-expanded={isExpanded}
        className={cn(
          'wsms-flex wsms-w-full wsms-items-center wsms-justify-between wsms-rounded-md wsms-px-3 wsms-py-2.5 wsms-text-[13px] wsms-font-medium wsms-transition-all wsms-duration-150 wsms-text-start',
          hasActiveChild
            ? 'wsms-text-primary wsms-bg-primary/5'
            : 'wsms-text-foreground/80 hover:wsms-bg-accent hover:wsms-text-foreground'
        )}
      >
        <div className="wsms-flex wsms-items-center wsms-gap-3">
          <Icon className="wsms-h-[18px] wsms-w-[18px] wsms-shrink-0" strokeWidth={1.5} />
          <span>{group.label}</span>
        </div>
        <div className="wsms-flex wsms-items-center wsms-gap-1.5">
          {groupBadgeTotal > 0 && !isExpanded && (
            <span className="wsms-flex wsms-h-[18px] wsms-min-w-[18px] wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-primary wsms-px-1 wsms-text-[10px] wsms-font-semibold wsms-text-primary-foreground wsms-leading-none">
              {groupBadgeTotal > 99 ? '99+' : groupBadgeTotal}
            </span>
          )}
          <ChevronRight
            className={cn(
              'wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-transition-transform wsms-duration-200',
              isExpanded
                ? 'wsms-rotate-90'
                : 'rtl:wsms-rotate-180'
            )}
            strokeWidth={1.5}
          />
        </div>
      </button>

      {/* Expandable content with smooth animation */}
      <div
        className={cn(
          'wsms-overflow-hidden wsms-transition-all wsms-duration-200 wsms-ease-out',
          isExpanded ? 'wsms-max-h-[800px] wsms-opacity-100' : 'wsms-max-h-0 wsms-opacity-0'
        )}
      >
        <div className="wsms-relative wsms-py-1">
          {/* Subtle connecting line */}
          <div className="wsms-absolute wsms-start-[22px] wsms-top-2 wsms-bottom-2 wsms-w-px wsms-bg-border/50" />

          {filteredItems.map((item, index) =>
            item.type === 'separator' ? (
              <div
                key={`separator-${index}`}
                className="wsms-flex wsms-items-center wsms-gap-2 wsms-px-7 wsms-py-2 wsms-mt-1"
              >
                <div className="wsms-flex-1 wsms-h-px wsms-bg-border/60" />
                <span className="wsms-text-[10px] wsms-font-medium wsms-text-muted-foreground/70 wsms-uppercase wsms-tracking-wider">
                  {item.label}
                </span>
                <div className="wsms-flex-1 wsms-h-px wsms-bg-border/60" />
              </div>
            ) : item.type === 'section-header' ? (
              <div
                key={`section-header-${index}`}
                className="wsms-flex wsms-items-center wsms-gap-2 wsms-px-7 wsms-py-2 wsms-mt-1"
              >
                {item.icon && <item.icon className="wsms-h-3.5 wsms-w-3.5 wsms-text-muted-foreground/70" strokeWidth={1.75} />}
                <span className="wsms-text-[11px] wsms-font-semibold wsms-text-muted-foreground/80 wsms-uppercase wsms-tracking-wide">
                  {item.label}
                </span>
              </div>
            ) : item.type === 'nested-group' ? (
              <NestedNavGroup
                key={item.id}
                group={item}
                currentPage={currentPage}
                setCurrentPage={setCurrentPage}
              />
            ) : (
              <NavItem
                key={item.id}
                item={item}
                isActive={currentPage === item.id}
                onClick={() => setCurrentPage(item.id)}
                isNested={true}
                badge={badges[item.id]}
              />
            )
          )}
        </div>
      </div>
    </div>
  )
}

export default function Sidebar({ onClose, showClose }) {
  const { currentPage, setCurrentPage, isAddonActive } = useSettings()
  const { gdprEnabled: initialGdprEnabled, hasProAddon } = getWpSettings()

  // Use saved setting so sidebar only reflects persisted gateway (not unsaved dropdown changes)
  const savedGatewayName = useSavedSetting('gateway_name', '')

  // Use saved setting for GDPR - only update sidebar when settings are saved
  const savedGdprSetting = useSavedSetting('gdpr_compliance', '')

  const isGatewayConfigured = Boolean(savedGatewayName)

  // Check GDPR from both initial settings AND saved settings context
  // This only updates the sidebar when settings are actually saved
  const gdprEnabled = initialGdprEnabled || savedGdprSetting === '1'

  // Check for WooCommerce Pro add-on (key is 'woocommerce' in getActiveAddons())
  const hasWooCommercePro = isAddonActive('woocommerce')

  // Check for Two-Way SMS add-on
  const hasTwoWay = isAddonActive('two-way')

  // Check if any add-on is active (for showing ADD-ONS separator)
  const hasAnyAddon = hasWooCommercePro || hasTwoWay

  // Fetch inbox unread count for nav badge
  const [navBadges, setNavBadges] = useState({})
  useEffect(() => {
    if (!hasTwoWay || !isAddonDashboardReady('two-way')) return
    let cancelled = false
    inboxApi.getStats().then((res) => {
      if (cancelled) return
      if (res.success && res.data?.unread > 0) {
        setNavBadges((prev) => ({ ...prev, 'two-way-inbox': res.data.unread }))
      }
    }).catch(() => {})
    return () => { cancelled = true }
  }, [hasTwoWay, currentPage])

  // Conditions object for filtering
  const conditions = {
    gdprEnabled,
    hasProAddon,
    hasWooCommercePro,
    hasTwoWay,
    hasAnyAddon,
  }

  // Get navigation items with translations applied
  const navigation = getNavigation()

  // Filter navigation items based on conditions
  const filteredNavigation = navigation.filter((item) => {
    if (!item.condition) return true
    return conditions[item.condition]
  })

  return (
    <div className="wsms-flex wsms-flex-col wsms-h-full wsms-min-h-0 wsms-bg-card">
      {/* Mobile header with logo and close button */}
      {showClose && (
        <div className="wsms-flex wsms-items-center wsms-justify-between wsms-p-3 wsms-border-b wsms-border-border">
          <Logo className="wsms-h-7" />
          <button
            onClick={onClose}
            aria-label="Close navigation menu"
            className="wsms-flex wsms-items-center wsms-justify-center wsms-h-8 wsms-w-8 wsms-rounded-md hover:wsms-bg-accent wsms-text-muted-foreground wsms-transition-colors"
          >
            <X className="wsms-h-4 wsms-w-4" aria-hidden="true" />
          </button>
        </div>
      )}

      {/* Navigation */}
      <nav className="wsms-flex-1 wsms-min-h-0 wsms-overflow-y-auto wsms-px-3 wsms-py-4 wsms-scrollbar-thin">
        <div className="wsms-space-y-1">
          {filteredNavigation.map((item, index) =>
            item.type === 'separator' ? (
              <div
                key={`separator-${index}`}
                className="wsms-flex wsms-items-center wsms-gap-2 wsms-px-2 wsms-py-3 wsms-mt-2"
              >
                <div className="wsms-flex-1 wsms-h-px wsms-bg-border" />
                <span className="wsms-text-[10px] wsms-font-semibold wsms-text-muted-foreground/70 wsms-uppercase wsms-tracking-wider">
                  {item.label}
                </span>
                <div className="wsms-flex-1 wsms-h-px wsms-bg-border" />
              </div>
            ) : item.type === 'group' ? (
              <NavGroup
                key={item.id}
                group={item}
                currentPage={currentPage}
                setCurrentPage={setCurrentPage}
                conditions={conditions}
                badges={navBadges}
              />
            ) : (
              <NavItem
                key={item.id}
                item={item}
                isActive={currentPage === item.id}
                onClick={() => setCurrentPage(item.id)}
              />
            )
          )}
        </div>
      </nav>

      {/* Footer: gateway status only — links, rating and version live in BrandingFooter */}
      <div className="wsms-border-t wsms-border-border wsms-mt-auto wsms-bg-muted/30 wsms-px-3 wsms-py-3.5">
        <GatewayStatus
          isConfigured={isGatewayConfigured}
          gatewayKey={savedGatewayName}
          onConfigure={() => setCurrentPage('gateway')}
        />
      </div>
    </div>
  )
}
