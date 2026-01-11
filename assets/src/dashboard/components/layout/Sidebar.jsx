import React, { useState, useEffect, useCallback } from 'react'
import {
  ExternalLink,
  ChevronRight,
  ChevronDown,
  Star,
  Sparkles,
  X,
  Settings2,
  RefreshCw,
} from 'lucide-react'
import { cn, __, getWpSettings, getGatewayDisplayName } from '@/lib/utils'
import { smsApi } from '@/api/smsApi'
import { useSettings, useSetting } from '@/context/SettingsContext'
import { getNavigation } from '@/lib/pageRegistry'

function getLinks() {
  return [
    { label: __('Documentation'), href: 'https://wp-sms-pro.com/documentation/' },
    { label: __('Support'), href: 'https://wordpress.org/support/plugin/wp-sms/' },
  ]
}

const footerUrls = {
  changelog: 'https://wp-sms-pro.com/changelog/',
  rate: 'https://wordpress.org/support/plugin/wp-sms/reviews/#new-post',
}

// Gateway status indicator component with expandable details
function GatewayStatus({ isConfigured, gatewayKey, onConfigure }) {
  const [isExpanded, setIsExpanded] = useState(false)
  const [credit, setCredit] = useState(null)
  const [creditSupported, setCreditSupported] = useState(true)
  const [isLoadingCredit, setIsLoadingCredit] = useState(false)
  const { gateways = {} } = getWpSettings()

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
    } finally {
      setIsLoadingCredit(false)
    }
  }, [isConfigured])

  // Fetch credit when expanded for the first time
  useEffect(() => {
    if (isExpanded && credit === null && !isLoadingCredit) {
      fetchCredit()
    }
  }, [isExpanded, credit, isLoadingCredit, fetchCredit])

  // If not configured, just show the button that navigates to gateway settings
  if (!isConfigured) {
    return (
      <button
        onClick={onConfigure}
        className="wsms-flex wsms-w-full wsms-items-center wsms-gap-2 wsms-px-3 wsms-py-2 wsms-rounded-md wsms-transition-all wsms-text-left wsms-bg-amber-500/10 hover:wsms-bg-amber-500/15"
      >
        <span className="wsms-relative wsms-flex wsms-h-2 wsms-w-2">
          <span className="wsms-relative wsms-inline-flex wsms-rounded-full wsms-h-2 wsms-w-2 wsms-bg-amber-500" />
        </span>
        <span className="wsms-text-[11px] wsms-font-medium wsms-text-amber-700 dark:wsms-text-amber-400">
          {__('Gateway not configured')}
        </span>
      </button>
    )
  }

  return (
    <div className="wsms-rounded-md wsms-bg-emerald-500/10 wsms-transition-all">
      {/* Header - clickable to expand/collapse */}
      <button
        onClick={() => setIsExpanded(!isExpanded)}
        className="wsms-flex wsms-w-full wsms-items-center wsms-justify-between wsms-px-3 wsms-py-2 wsms-text-left hover:wsms-bg-emerald-500/15 wsms-rounded-md wsms-transition-colors"
      >
        <div className="wsms-flex wsms-items-center wsms-gap-2">
          <span className="wsms-relative wsms-flex wsms-h-2 wsms-w-2">
            <span className="wsms-absolute wsms-inline-flex wsms-h-full wsms-w-full wsms-rounded-full wsms-bg-emerald-500 wsms-opacity-75 wsms-animate-ping" />
            <span className="wsms-relative wsms-inline-flex wsms-rounded-full wsms-h-2 wsms-w-2 wsms-bg-emerald-500" />
          </span>
          <span className="wsms-text-[11px] wsms-font-medium wsms-text-emerald-700 dark:wsms-text-emerald-400">
            {__('Gateway Connected')}
          </span>
        </div>
        <ChevronDown
          className={cn(
            'wsms-h-3.5 wsms-w-3.5 wsms-text-emerald-600 dark:wsms-text-emerald-400 wsms-transition-transform wsms-duration-200',
            isExpanded && 'wsms-rotate-180'
          )}
          strokeWidth={2}
        />
      </button>

      {/* Expandable content */}
      <div
        className={cn(
          'wsms-overflow-hidden wsms-transition-all wsms-duration-200 wsms-ease-out',
          isExpanded ? 'wsms-max-h-[150px] wsms-opacity-100' : 'wsms-max-h-0 wsms-opacity-0'
        )}
      >
        <div className="wsms-px-3 wsms-pb-2.5 wsms-space-y-2">
          {/* Gateway name */}
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-text-[11px]">
            <span className="wsms-text-muted-foreground">{__('Gateway')}</span>
            <span className="wsms-font-medium wsms-text-foreground">{gatewayDisplayName}</span>
          </div>

          {/* Credit */}
          {creditSupported && (
            <div className="wsms-flex wsms-items-center wsms-justify-between wsms-text-[11px]">
              <span className="wsms-text-muted-foreground">{__('Credit')}</span>
              <div className="wsms-flex wsms-items-center wsms-gap-1.5">
                {isLoadingCredit ? (
                  <RefreshCw className="wsms-h-3 wsms-w-3 wsms-animate-spin wsms-text-muted-foreground" />
                ) : (
                  <>
                    <span className="wsms-font-medium wsms-text-foreground">
                      {credit !== null ? credit : '—'}
                    </span>
                    <button
                      onClick={(e) => {
                        e.stopPropagation()
                        fetchCredit()
                      }}
                      className="wsms-p-0.5 wsms-rounded hover:wsms-bg-emerald-500/20 wsms-transition-colors"
                      title={__('Refresh credit')}
                    >
                      <RefreshCw className="wsms-h-3 wsms-w-3 wsms-text-muted-foreground hover:wsms-text-foreground" />
                    </button>
                  </>
                )}
              </div>
            </div>
          )}

          {/* Configure button */}
          <button
            onClick={onConfigure}
            className="wsms-flex wsms-w-full wsms-items-center wsms-justify-center wsms-gap-1.5 wsms-px-2.5 wsms-py-1.5 wsms-text-[11px] wsms-font-medium wsms-text-emerald-700 dark:wsms-text-emerald-400 wsms-bg-emerald-500/10 hover:wsms-bg-emerald-500/20 wsms-rounded-md wsms-transition-colors"
          >
            <Settings2 className="wsms-h-3 wsms-w-3" />
            {__('Configure')}
          </button>
        </div>
      </div>
    </div>
  )
}

// What's New component with changelog link
function WhatsNew({ version }) {
  return (
    <a
      href={footerUrls.changelog}
      target="_blank"
      rel="noopener noreferrer"
      className="wsms-group wsms-flex wsms-items-center wsms-justify-between wsms-w-full wsms-px-3 wsms-py-1.5 wsms-text-[11px] wsms-text-muted-foreground hover:wsms-text-foreground wsms-transition-colors wsms-rounded-md hover:wsms-bg-accent"
    >
      <span className="wsms-flex wsms-items-center wsms-gap-1.5">
        <Sparkles className="wsms-h-3 wsms-w-3 wsms-text-primary/60 group-hover:wsms-text-primary wsms-transition-colors" />
        <span>{__("What's New")}</span>
      </span>
      <span className="wsms-font-medium wsms-text-muted-foreground/70">v{version}</span>
    </a>
  )
}

// Rate plugin component with animated stars
function RatePlugin() {
  return (
    <a
      href={footerUrls.rate}
      target="_blank"
      rel="noopener noreferrer"
      className="wsms-group wsms-flex wsms-items-center wsms-justify-between wsms-w-full wsms-px-3 wsms-py-1.5 wsms-text-[11px] wsms-text-muted-foreground hover:wsms-text-foreground wsms-transition-colors wsms-rounded-md hover:wsms-bg-accent"
    >
      <span>{__('Enjoying WSMS?')}</span>
      <span className="wsms-flex wsms-items-center wsms-gap-0.5">
        {[1, 2, 3, 4, 5].map((star) => (
          <Star
            key={star}
            className="wsms-h-3 wsms-w-3 wsms-text-amber-400/40 group-hover:wsms-text-amber-400 group-hover:wsms-fill-amber-400 wsms-transition-all wsms-duration-150"
            style={{ transitionDelay: `${star * 40}ms` }}
          />
        ))}
      </span>
    </a>
  )
}

// Single nav item (leaf node)
function NavItem({ item, isActive, onClick, isNested = false }) {
  const Icon = item.icon

  // For nested items, background starts after the line and extends to the right edge
  if (isNested) {
    return (
      <button
        onClick={onClick}
        className={cn(
          'wsms-flex wsms-w-full wsms-items-center wsms-py-0.5 wsms-pr-3 wsms-pl-7 wsms-text-left wsms-transition-colors wsms-duration-150',
          isActive ? 'wsms-text-primary' : 'wsms-text-foreground/70 hover:wsms-text-foreground'
        )}
      >
        <span
          className={cn(
            'wsms-flex wsms-flex-1 wsms-items-center wsms-gap-2.5 wsms-py-1.5 wsms-px-2.5 wsms-rounded-md wsms-transition-colors wsms-duration-150',
            isActive
              ? 'wsms-bg-primary/10 wsms-font-semibold'
              : 'hover:wsms-bg-primary/5'
          )}
        >
          <Icon className="wsms-h-3.5 wsms-w-3.5 wsms-shrink-0" strokeWidth={1.75} />
          <span className="wsms-text-[12px] wsms-font-medium">{item.label}</span>
        </span>
      </button>
    )
  }

  return (
    <button
      onClick={onClick}
      className={cn(
        'wsms-flex wsms-w-full wsms-items-center wsms-gap-3 wsms-rounded-md wsms-text-[13px] wsms-font-medium wsms-transition-all wsms-duration-150 wsms-text-left wsms-px-3 wsms-py-2.5',
        isActive
          ? 'wsms-bg-primary/10 wsms-text-primary wsms-font-semibold'
          : 'wsms-text-foreground/70 hover:wsms-bg-primary/5 hover:wsms-text-foreground wsms-transition-colors wsms-duration-150'
      )}
    >
      <Icon className="wsms-h-4 wsms-w-4 wsms-shrink-0" strokeWidth={1.75} />
      <span>{item.label}</span>
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
    <div className="wsms-pl-7">
      {/* Nested group header */}
      <button
        onClick={() => setIsExpanded(!isExpanded)}
        className={cn(
          'wsms-flex wsms-w-full wsms-items-center wsms-justify-between wsms-rounded-md wsms-px-2.5 wsms-py-1.5 wsms-text-[12px] wsms-font-medium wsms-transition-all wsms-duration-150 wsms-text-left',
          hasActiveChild
            ? 'wsms-text-primary wsms-bg-primary/10'
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
            isExpanded && 'wsms-rotate-90'
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
        <div className="wsms-relative wsms-py-0.5 wsms-pl-4">
          {/* Connecting line for nested items */}
          <div className="wsms-absolute wsms-left-[14px] wsms-top-1 wsms-bottom-1 wsms-w-px wsms-bg-border/40" />

          {group.items.map((item) => {
            const ItemIcon = item.icon
            return (
              <button
                key={item.id}
                onClick={() => setCurrentPage(item.id)}
                className={cn(
                  'wsms-flex wsms-w-full wsms-items-center wsms-gap-2 wsms-rounded-md wsms-px-2 wsms-py-1.5 wsms-text-[11px] wsms-font-medium wsms-transition-all wsms-duration-150 wsms-text-left',
                  currentPage === item.id
                    ? 'wsms-text-primary wsms-bg-primary/10'
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
function NavGroup({ group, currentPage, setCurrentPage, conditions }) {
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

  return (
    <div className="wsms-space-y-0.5">
      {/* Group header - clickable to expand/collapse */}
      <button
        onClick={() => setIsExpanded(!isExpanded)}
        className={cn(
          'wsms-flex wsms-w-full wsms-items-center wsms-justify-between wsms-rounded-md wsms-px-3 wsms-py-2.5 wsms-text-[13px] wsms-font-medium wsms-transition-all wsms-duration-150 wsms-text-left',
          hasActiveChild
            ? 'wsms-text-primary wsms-bg-primary/5'
            : 'wsms-text-foreground/80 hover:wsms-bg-accent hover:wsms-text-foreground'
        )}
      >
        <div className="wsms-flex wsms-items-center wsms-gap-3">
          <Icon className="wsms-h-[18px] wsms-w-[18px] wsms-shrink-0" strokeWidth={1.5} />
          <span>{group.label}</span>
        </div>
        <ChevronRight
          className={cn(
            'wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-transition-transform wsms-duration-200',
            isExpanded && 'wsms-rotate-90'
          )}
          strokeWidth={1.5}
        />
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
          <div className="wsms-absolute wsms-left-[22px] wsms-top-2 wsms-bottom-2 wsms-w-px wsms-bg-border/50" />

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
  const version = window.wpSmsSettings?.version || '7.0'
  const { gdprEnabled: initialGdprEnabled, hasProAddon } = getWpSettings()

  // Use useSetting hook for reactive updates when settings change
  const [gatewayName] = useSetting('gateway_name', '')
  const [currentGdprSetting] = useSetting('gdpr_compliance', '')

  const isGatewayConfigured = Boolean(gatewayName)

  // Check GDPR from both initial settings AND current settings context
  // This makes it reactive when user enables GDPR compliance in settings
  const gdprEnabled = initialGdprEnabled || currentGdprSetting === '1'

  // Check for WooCommerce Pro add-on (key is 'woocommerce' in getActiveAddons())
  const hasWooCommercePro = isAddonActive('woocommerce')

  // Check for Two-Way SMS add-on
  const hasTwoWay = isAddonActive('two-way')

  // Check if any add-on is active (for showing ADD-ONS separator)
  const hasAnyAddon = hasWooCommercePro || hasTwoWay

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
  const links = getLinks()

  // Filter navigation items based on conditions
  const filteredNavigation = navigation.filter((item) => {
    if (!item.condition) return true
    return conditions[item.condition]
  })

  return (
    <div className="wsms-flex wsms-flex-col wsms-h-full wsms-min-h-0 wsms-bg-card">
      {/* Mobile close button */}
      {showClose && (
        <div className="wsms-flex wsms-justify-end wsms-p-3 wsms-border-b wsms-border-border">
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

      {/* Footer */}
      <div className="wsms-border-t wsms-border-border wsms-mt-auto wsms-bg-muted/30">
        {/* Quick Links */}
        <div className="wsms-px-3 wsms-pt-2 wsms-pb-1 wsms-space-y-0.5">
          {links.map((link) => (
            <a
              key={link.label}
              href={link.href}
              target="_blank"
              rel="noopener noreferrer"
              className="wsms-flex wsms-items-center wsms-justify-between wsms-px-3 wsms-py-1.5 wsms-text-[11px] wsms-text-muted-foreground hover:wsms-text-foreground wsms-transition-colors wsms-rounded-md hover:wsms-bg-accent"
            >
              <span>{link.label}</span>
              <ExternalLink className="wsms-h-3 wsms-w-3" />
            </a>
          ))}
        </div>

        {/* Gateway Status */}
        <div className="wsms-px-3 wsms-py-1">
          <GatewayStatus
            isConfigured={isGatewayConfigured}
            gatewayKey={gatewayName}
            onConfigure={() => setCurrentPage('gateway')}
          />
        </div>

        {/* Version & Rate */}
        <div className="wsms-px-3 wsms-py-1.5 wsms-space-y-0.5 wsms-border-t wsms-border-border/50">
          <WhatsNew version={version} />
          <RatePlugin />
        </div>
      </div>
    </div>
  )
}
