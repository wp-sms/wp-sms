import React, { useState, useMemo, useRef, useEffect } from 'react'
import { Search, CheckCircle, Radio, Send, Loader2, Shield, Zap, BookOpen, ExternalLink, XCircle, RotateCcw, Code, Unplug, Wallet, Star } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Button } from '@/components/ui/button'
import { Label } from '@/components/ui/label'
import { InputField, SelectField, SwitchField, MultiSelectField } from '@/components/ui/form-field'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { Tip, CollapsibleSection, HelpLink, SectionDivider } from '@/components/ui/ux-helpers'
import { useSettings, useSetting } from '@/context/SettingsContext'
import { useToast } from '@/components/ui/toaster'
import { getWpSettings, cn, getGatewayDisplayName, getGatewayLogo, __, countryCodeToFlag } from '@/lib/utils'
import { GatewayCard, GatewayCardMinimal } from '@/components/GatewayCard'
import useGatewayRegistry from '@/hooks/useGatewayRegistry'

export default function Gateway() {
  const { testGatewayConnection, getSetting, updateSetting, hasChanges, isSaving } = useSettings()
  const { toast } = useToast()
  const { countriesByDialCode = {}, gateway: gatewayCapabilities = {} } = getWpSettings()
  const { gateways, regions, source, isLoading: registryLoading } = useGatewayRegistry()

  const isApiSource = source === 'api'

  // Get dynamic gateway fields and help from capabilities
  const gatewayFields = gatewayCapabilities.gatewayFields || {}
  const gatewayHelp = gatewayCapabilities.help || ''
  const gatewayDocumentUrl = gatewayCapabilities.documentUrl || ''

  const [gatewayName, setGatewayName] = useSetting('gateway_name', '')

  // Track the saved gateway (the one capabilities/fields are loaded for)
  const savedGatewayRef = useRef(gatewayName)

  // Track previous saving state to detect save completion
  const wasSavingRef = useRef(false)

  // Detect if user has selected a different gateway but hasn't saved yet
  const hasUnsavedGatewayChange = gatewayName && gatewayName !== savedGatewayRef.current

  // Reload page after gateway switch is saved to load new gateway's configuration fields
  useEffect(() => {
    if (wasSavingRef.current && !isSaving && !hasChanges) {
      if (gatewayName !== savedGatewayRef.current) {
        window.location.reload()
      }
    }
    wasSavingRef.current = isSaving
  }, [isSaving, hasChanges, gatewayName])

  const [deliveryMethod, setDeliveryMethod] = useSetting('sms_delivery_method', 'api_direct_send')
  const [sendUnicode, setSendUnicode] = useSetting('send_unicode', '')
  const [cleanNumbers, setCleanNumbers] = useSetting('clean_numbers', '')
  const [localNumbersOnly, setLocalNumbersOnly] = useSetting('send_only_local_numbers', '')
  const [localNumbersCountries, setLocalNumbersCountries] = useSetting('only_local_numbers_countries', [])

  const [creditInMenu, setCreditInMenu] = useSetting('account_credit_in_menu', '')
  const [creditInSendSms, setCreditInSendSms] = useSetting('account_credit_in_sendsms', '')

  const [searchQuery, setSearchQuery] = useState('')
  const [selectedRegion, setSelectedRegion] = useState('')
  const [testing, setTesting] = useState(false)
  const [connectionTested, setConnectionTested] = useState(false)
  const [connectionSuccess, setConnectionSuccess] = useState(false)
  const [rawResponse, setRawResponse] = useState(null)
  const [showDisconnectConfirm, setShowDisconnectConfirm] = useState(false)
  const [pendingGateway, setPendingGateway] = useState(null)

  // Build region options from registry data (no flags — flags appear in the gateway list)
  const regionOptions = useMemo(() => {
    if (regions.length > 0) {
      return regions.map((r) => ({ value: r.slug, label: r.name }))
    }
    const slugs = [...new Set(gateways.flatMap((g) => g.regions || []))].filter(Boolean)
    return slugs.sort((a, b) => {
      if (a === 'global') return -1
      if (b === 'global') return 1
      return a.localeCompare(b)
    }).map((s) => ({ value: s, label: s.charAt(0).toUpperCase() + s.slice(1) }))
  }, [regions, gateways])

  const filteredGateways = useMemo(() => {
    let list = gateways.filter((g) => g.slug && g.slug.trim() !== '')

    if (selectedRegion && selectedRegion !== 'all') {
      list = list.filter((g) => (g.regions || []).some(
        (r) => r.toLowerCase() === selectedRegion.toLowerCase()
      ))
    }

    if (searchQuery) {
      const query = searchQuery.toLowerCase()
      list = list.filter(
        (g) =>
          g.name.toLowerCase().includes(query) ||
          g.slug.toLowerCase().includes(query) ||
          (g.description && g.description.toLowerCase().includes(query))
      )
    }

    return list
  }, [gateways, searchQuery, selectedRegion])

  // Split into recommended and rest when no search/filter
  const { recommended, rest } = useMemo(() => {
    if (searchQuery || (selectedRegion && selectedRegion !== 'all') || !isApiSource) {
      return { recommended: [], rest: filteredGateways }
    }
    return {
      recommended: filteredGateways.filter((g) => g.recommended),
      rest: filteredGateways.filter((g) => !g.recommended),
    }
  }, [filteredGateways, searchQuery, selectedRegion, isApiSource])

  // Build region name lookup from regions data
  const regionNameMap = useMemo(() => {
    const map = {}
    regions.forEach((r) => { map[r.slug] = r.name })
    return map
  }, [regions])

  // Region-grouped gateways when API data has region info
  const regionGroups = useMemo(() => {
    if (!isApiSource) return null

    const hasRegions = filteredGateways.some((g) => g.regions && g.regions.length > 0)
    if (!hasRegions) return null

    const groups = {}
    const globalList = []

    filteredGateways.forEach((g) => {
      const primaryRegion = (g.regions && g.regions.length > 0) ? g.regions[0] : null
      if (!primaryRegion || primaryRegion === 'global') {
        globalList.push(g)
        return
      }
      if (!groups[primaryRegion]) groups[primaryRegion] = []
      groups[primaryRegion].push(g)
    })

    const sortedSlugs = Object.keys(groups).sort((a, b) => {
      const nameA = regionNameMap[a] || a
      const nameB = regionNameMap[b] || b
      return nameA.localeCompare(nameB)
    })

    return { sortedSlugs, groups, globalList }
  }, [filteredGateways, regionNameMap, isApiSource])

  // Build country name lookup from regions data
  const countryNameMap = useMemo(() => {
    const map = {}
    regions.forEach((r) => {
      (r.countries || []).forEach((c) => { map[c.code] = c.name })
    })
    return map
  }, [regions])

  // Country-grouped gateways when a specific region is selected
  const countryGroups = useMemo(() => {
    if (!isApiSource || !selectedRegion || selectedRegion === 'all') return null

    const groups = {}
    const ungrouped = []

    filteredGateways.forEach((g) => {
      if (!g.countries || g.countries.length === 0) {
        ungrouped.push(g)
        return
      }
      const country = g.countries[0]
      if (!groups[country]) groups[country] = []
      groups[country].push(g)
    })

    const sortedCodes = Object.keys(groups).sort((a, b) => {
      const nameA = countryNameMap[a] || a
      const nameB = countryNameMap[b] || b
      return nameA.localeCompare(nameB)
    })

    return { sortedCodes, groups, ungrouped }
  }, [filteredGateways, selectedRegion, isApiSource, countryNameMap])

  // Helper to get display name from registry gateways
  const displayName = (slug) => getGatewayDisplayName(slug, gateways)

  // Helper to get gateway object
  const getGateway = (slug) => gateways.find((g) => g.slug === slug)

  const handleTestConnection = async () => {
    setTesting(true)
    try {
      const result = await testGatewayConnection()
      setConnectionTested(true)
      setConnectionSuccess(result.success)
      setRawResponse(result.rawResponse || null)
      toast({
        title: result.success ? 'Connection Successful' : 'Connection Failed',
        description: result.success ? `Credit: ${result.credit}` : result.error,
        variant: result.success ? 'success' : 'destructive',
      })
    } catch (error) {
      setConnectionTested(true)
      setConnectionSuccess(false)
      toast({ title: 'Error', description: error.message, variant: 'destructive' })
    }
    setTesting(false)
  }

  // Build feature tags for a gateway
  const getFeatureTags = (gateway) => {
    if (!gateway.features) return []
    const map = { bulk_send: 'Bulk', mms: 'MMS', incoming_sms: 'SMS-in', whatsapp: 'WhatsApp' }
    return Object.entries(map)
      .filter(([key]) => gateway.features[key])
      .map(([, label]) => label)
      .slice(0, 3)
  }

  const handleGatewayClick = (slug) => {
    if (slug === gatewayName) return
    if (!gatewayName) {
      setGatewayName(slug)
      return
    }
    setPendingGateway(slug)
    setShowDisconnectConfirm(false)
  }

  const CardComponent = isApiSource ? GatewayCard : GatewayCardMinimal

  const renderGatewayButton = (gateway) => (
    <CardComponent
      key={gateway.slug}
      gateway={isApiSource ? { ...gateway, logo: getGatewayLogo(gateway) } : gateway}
      isSelected={gatewayName === gateway.slug}
      onClick={handleGatewayClick}
      showFeatures={isApiSource}
      featureTags={isApiSource ? getFeatureTags(gateway) : []}
    />
  )

  return (
    <div className="wsms-space-y-4 wsms-stagger-children">
      {/* Helpful tip for new users */}
      {!gatewayName && (
        <Tip>
          <strong>{__('Need help choosing a gateway?')}</strong> {__('Consider factors like coverage area, pricing, and API features. Most gateways offer free trial credits to test before committing.')}
        </Tip>
      )}

      {/* Gateway Selection */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Radio className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('SMS Gateway')}
          </CardTitle>
          <CardDescription>{__('Select your SMS service provider. Configure credentials below after selecting.')}</CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          {/* Search and Region Filter */}
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <div className="wsms-relative wsms-flex-1">
              <Search className="wsms-absolute wsms-left-3 wsms-top-1/2 wsms-h-4 wsms-w-4 wsms--translate-y-1/2 wsms-text-muted-foreground" />
              <Input
                placeholder={__('Search gateways...')}
                value={searchQuery}
                onChange={(e) => setSearchQuery(e.target.value)}
                className="wsms-pl-9"
              />
            </div>

            <div className="wsms-w-[180px] wsms-shrink-0">
              <SearchableSelect
                value={selectedRegion}
                onValueChange={setSelectedRegion}
                placeholder={__('All Regions')}
                searchPlaceholder={__('Search regions...')}
                options={[
                  { value: 'all', label: __('All Regions') },
                  ...regionOptions,
                ]}
                triggerClassName="wsms-capitalize"
                optionClassName="wsms-capitalize"
              />
            </div>
          </div>

          <div className="wsms-max-h-[280px] wsms-overflow-y-auto wsms-rounded-md wsms-border wsms-border-border wsms-p-4 wsms-scrollbar-thin wsms-bg-muted/30">
            {registryLoading ? (
              <div className="wsms-flex wsms-items-center wsms-justify-center wsms-py-12 wsms-text-muted-foreground">
                <Loader2 className="wsms-h-5 wsms-w-5 wsms-animate-spin wsms-mr-2" />
                <span className="wsms-text-[12px]">{__('Loading gateways...')}</span>
              </div>
            ) : (
              <>
                {countryGroups ? (
                  /* Country-grouped layout when a specific region is selected */
                  <div className="wsms-space-y-5">
                    {countryGroups.sortedCodes.map((code) => (
                      <div key={code}>
                        <p className="wsms-mb-2 wsms-text-[12px] wsms-font-semibold wsms-text-foreground">
                          {countryCodeToFlag(code)} {countryNameMap[code] || code}
                        </p>
                        <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 md:wsms-grid-cols-3 lg:wsms-grid-cols-4">
                          {countryGroups.groups[code].map(renderGatewayButton)}
                        </div>
                      </div>
                    ))}
                    {countryGroups.ungrouped.length > 0 && (
                      <div>
                        <p className="wsms-mb-2 wsms-text-[12px] wsms-font-semibold wsms-text-foreground">
                          {__('Other')}
                        </p>
                        <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 md:wsms-grid-cols-3 lg:wsms-grid-cols-4">
                          {countryGroups.ungrouped.map(renderGatewayButton)}
                        </div>
                      </div>
                    )}
                  </div>
                ) : regionGroups ? (
                  /* Region-grouped layout with flag headers */
                  <div className="wsms-space-y-5">
                    {/* Recommended section at top */}
                    {recommended.length > 0 && (
                      <div>
                        <p className="wsms-mb-2 wsms-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-font-semibold wsms-uppercase wsms-text-muted-foreground wsms-tracking-wide">
                          <Star className="wsms-h-3 wsms-w-3 wsms-text-amber-500" />
                          {__('Recommended')}
                        </p>
                        <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 md:wsms-grid-cols-3 lg:wsms-grid-cols-4">
                          {recommended.map(renderGatewayButton)}
                        </div>
                      </div>
                    )}

                    {/* Global gateways */}
                    {regionGroups.globalList.length > 0 && (
                      <div>
                        <p className="wsms-mb-2 wsms-flex wsms-items-center wsms-gap-1.5 wsms-text-[12px] wsms-font-semibold wsms-text-foreground">
                          {__('Global')} 🌐
                        </p>
                        <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 md:wsms-grid-cols-3 lg:wsms-grid-cols-4">
                          {regionGroups.globalList.map(renderGatewayButton)}
                        </div>
                      </div>
                    )}

                    {/* Region groups with country flags */}
                    {regionGroups.sortedSlugs.map((slug) => {
                      const regionName = regionNameMap[slug] || slug.charAt(0).toUpperCase() + slug.slice(1).replace(/-/g, ' ')
                      return (
                        <div key={slug}>
                          <p className="wsms-mb-2 wsms-text-[12px] wsms-font-semibold wsms-text-foreground">
                            {regionName}
                          </p>
                          <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 md:wsms-grid-cols-3 lg:wsms-grid-cols-4">
                            {regionGroups.groups[slug].map(renderGatewayButton)}
                          </div>
                        </div>
                      )
                    })}
                  </div>
                ) : (
                  <>
                    {/* Recommended Section */}
                    {recommended.length > 0 && (
                      <div className="wsms-mb-4">
                        <p className="wsms-mb-2 wsms-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-font-semibold wsms-uppercase wsms-text-muted-foreground wsms-tracking-wide">
                          <Star className="wsms-h-3 wsms-w-3 wsms-text-amber-500" />
                          {__('Recommended')}
                        </p>
                        <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 md:wsms-grid-cols-3 lg:wsms-grid-cols-4">
                          {recommended.map(renderGatewayButton)}
                        </div>
                      </div>
                    )}

                    {/* Rest of gateways */}
                    {rest.length > 0 && (
                      <div>
                        {recommended.length > 0 && (
                          <p className="wsms-mb-2 wsms-text-[11px] wsms-font-semibold wsms-uppercase wsms-text-muted-foreground wsms-tracking-wide">
                            {__('All Gateways')}
                          </p>
                        )}
                        <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 md:wsms-grid-cols-3 lg:wsms-grid-cols-4">
                          {rest.map(renderGatewayButton)}
                        </div>
                      </div>
                    )}
                  </>
                )}

                {!registryLoading && filteredGateways.length === 0 && (
                  <div className="wsms-py-8 wsms-text-center wsms-text-[12px] wsms-text-muted-foreground">
                    {__('No gateways found')}
                  </div>
                )}
              </>
            )}
          </div>

          {/* Selection Status Bar */}
          <div className={cn(
            "wsms-rounded-lg wsms-border wsms-transition-all wsms-duration-200",
            gatewayName
              ? "wsms-border-primary/30 wsms-bg-primary/5"
              : "wsms-border-dashed wsms-border-border wsms-bg-muted/30"
          )}>
            {gatewayName ? (
              <>
                {/* Selected State */}
                <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-3 wsms-p-3">
                  <div className="wsms-flex wsms-items-center wsms-gap-3">
                    <div className="wsms-flex wsms-h-8 wsms-w-8 wsms-items-center wsms-justify-center wsms-rounded-md wsms-bg-primary/10 wsms-overflow-hidden">
                      {isApiSource && getGatewayLogo(getGateway(gatewayName)) ? (
                        <img
                          src={getGatewayLogo(getGateway(gatewayName))}
                          alt=""
                          className="wsms-h-6 wsms-w-6 wsms-object-contain"
                          onError={(e) => {
                            e.target.style.display = 'none'
                            e.target.parentElement.innerHTML = '<svg class="wsms-h-4 wsms-w-4 wsms-text-primary" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>'
                          }}
                        />
                      ) : (
                        <CheckCircle className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                      )}
                    </div>
                    <div>
                      <p className="wsms-text-[11px] wsms-font-medium wsms-uppercase wsms-tracking-wide wsms-text-muted-foreground">
                        {__('Selected Gateway')}
                      </p>
                      <p className="wsms-text-[13px] wsms-font-semibold wsms-text-foreground">
                        {displayName(gatewayName)}
                      </p>
                      {isApiSource && getGateway(gatewayName)?.description && (
                        <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-line-clamp-1">
                          {getGateway(gatewayName).description}
                        </p>
                      )}
                      {getGateway(gatewayName)?.website && (
                        <a
                          href={`${getGateway(gatewayName).website}?utm_source=wsms&utm_medium=plugin&utm_campaign=gateway-settings`}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-text-primary hover:wsms-text-primary/80"
                        >
                          <ExternalLink className="wsms-h-3 wsms-w-3" />
                          {__('Visit Website')}
                        </a>
                      )}
                    </div>
                  </div>
                  {pendingGateway ? (
                    <div className="wsms-flex wsms-items-center wsms-gap-2">
                      <span className="wsms-text-[12px] wsms-text-muted-foreground">
                        {__('Switch to')} <strong className="wsms-text-foreground">{displayName(pendingGateway)}</strong>?
                      </span>
                      <button
                        onClick={() => {
                          setGatewayName(pendingGateway)
                          setPendingGateway(null)
                        }}
                        className="wsms-rounded-md wsms-px-2.5 wsms-py-1 wsms-text-[12px] wsms-font-medium wsms-bg-primary wsms-text-primary-foreground wsms-transition-colors hover:wsms-bg-primary/90"
                      >
                        {__('Switch')}
                      </button>
                      <button
                        onClick={() => setPendingGateway(null)}
                        className="wsms-rounded-md wsms-px-2.5 wsms-py-1 wsms-text-[12px] wsms-font-medium wsms-border wsms-border-border wsms-bg-background wsms-transition-colors hover:wsms-bg-accent"
                      >
                        {__('Cancel')}
                      </button>
                    </div>
                  ) : showDisconnectConfirm ? (
                    <div className="wsms-flex wsms-items-center wsms-gap-2">
                      <span className="wsms-text-[12px] wsms-text-muted-foreground">{__('Are you sure?')}</span>
                      <button
                        onClick={() => {
                          setGatewayName('')
                          setShowDisconnectConfirm(false)
                        }}
                        className="wsms-rounded-md wsms-px-2.5 wsms-py-1 wsms-text-[12px] wsms-font-medium wsms-bg-destructive wsms-text-destructive-foreground wsms-transition-colors hover:wsms-bg-destructive/90"
                      >
                        {__('Disconnect')}
                      </button>
                      <button
                        onClick={() => setShowDisconnectConfirm(false)}
                        className="wsms-rounded-md wsms-px-2.5 wsms-py-1 wsms-text-[12px] wsms-font-medium wsms-border wsms-border-border wsms-bg-background wsms-transition-colors hover:wsms-bg-accent"
                      >
                        {__('Cancel')}
                      </button>
                    </div>
                  ) : (
                    <button
                      onClick={() => setShowDisconnectConfirm(true)}
                      className="wsms-flex wsms-items-center wsms-gap-1.5 wsms-rounded-md wsms-px-3 wsms-py-1.5 wsms-text-[12px] wsms-font-medium wsms-text-muted-foreground wsms-transition-colors hover:wsms-bg-destructive/10 hover:wsms-text-destructive"
                    >
                      <Unplug className="wsms-h-3.5 wsms-w-3.5" />
                      {__('Disconnect')}
                    </button>
                  )}
                </div>
                {/* Inline Capabilities */}
                <div className="wsms-border-t wsms-border-primary/20 wsms-px-3 wsms-py-2 wsms-bg-primary/[0.02]">
                  {hasUnsavedGatewayChange ? (
                    <p className="wsms-text-[11px] wsms-text-amber-600 dark:wsms-text-amber-400">
                      {__('Save your changes to see capabilities and configure credentials for this gateway.')}
                    </p>
                  ) : (
                    <div className="wsms-flex wsms-items-center wsms-gap-4 wsms-flex-wrap">
                      <span className="wsms-text-[11px] wsms-font-medium wsms-uppercase wsms-tracking-wide wsms-text-muted-foreground">
                        {__('Capabilities:')}
                      </span>
                      <div className="wsms-flex wsms-items-center wsms-gap-3 wsms-flex-wrap">
                        <span className={cn(
                          "wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-font-medium",
                          gatewayCapabilities.flash === 'enable' ? "wsms-text-success" : "wsms-text-muted-foreground/50"
                        )}>
                          {gatewayCapabilities.flash === 'enable' ? <CheckCircle className="wsms-h-3 wsms-w-3" /> : <XCircle className="wsms-h-3 wsms-w-3" />}
                          {__('Flash SMS')}
                        </span>
                        <span className={cn(
                          "wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-font-medium",
                          gatewayCapabilities.bulk_send !== false ? "wsms-text-success" : "wsms-text-muted-foreground/50"
                        )}>
                          {gatewayCapabilities.bulk_send !== false ? <CheckCircle className="wsms-h-3 wsms-w-3" /> : <XCircle className="wsms-h-3 wsms-w-3" />}
                          {__('Bulk Send')}
                        </span>
                        <span className={cn(
                          "wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-font-medium",
                          gatewayCapabilities.supportMedia === true ? "wsms-text-success" : "wsms-text-muted-foreground/50"
                        )}>
                          {gatewayCapabilities.supportMedia === true ? <CheckCircle className="wsms-h-3 wsms-w-3" /> : <XCircle className="wsms-h-3 wsms-w-3" />}
                          {__('MMS')}
                        </span>
                        <span className={cn(
                          "wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-font-medium",
                          gatewayCapabilities.supportIncoming === true ? "wsms-text-success" : "wsms-text-muted-foreground/50"
                        )}>
                          {gatewayCapabilities.supportIncoming === true ? <CheckCircle className="wsms-h-3 wsms-w-3" /> : <XCircle className="wsms-h-3 wsms-w-3" />}
                          {__('Incoming SMS')}
                        </span>
                      </div>
                    </div>
                  )}
                </div>
              </>
            ) : (
              <div className="wsms-flex wsms-items-center wsms-gap-3 wsms-p-3">
                <div className="wsms-flex wsms-h-8 wsms-w-8 wsms-items-center wsms-justify-center wsms-rounded-md wsms-bg-muted">
                  <Radio className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                </div>
                <div>
                  <p className="wsms-text-[11px] wsms-font-medium wsms-uppercase wsms-tracking-wide wsms-text-muted-foreground">
                    {__('No Gateway Selected')}
                  </p>
                  <p className="wsms-text-[12px] wsms-text-muted-foreground">
                    {__('Choose a provider from the list above')}
                  </p>
                </div>
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Gateway Guide - only show for saved gateway */}
      {gatewayName && !hasUnsavedGatewayChange && (gatewayHelp || gatewayDocumentUrl) && (
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <BookOpen className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              {__('Gateway Guide')}
            </CardTitle>
            <CardDescription>{__('Setup instructions for')} {displayName(gatewayName)}</CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-space-y-3">
              {gatewayHelp && (
                <div
                  className="wsms-text-[13px] wsms-text-foreground [&_a]:wsms-text-primary [&_a]:wsms-underline [&_a]:hover:wsms-text-primary/80"
                  dangerouslySetInnerHTML={{ __html: gatewayHelp }}
                />
              )}
              {gatewayDocumentUrl && (
                <a
                  href={gatewayDocumentUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="wsms-inline-flex wsms-items-center wsms-gap-1.5 wsms-text-[13px] wsms-text-primary hover:wsms-text-primary/80 wsms-font-medium"
                >
                  <ExternalLink className="wsms-h-3.5 wsms-w-3.5" />
                  {__('View Full Documentation')}
                </a>
              )}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Gateway Credentials - only show for saved gateway */}
      {gatewayName && !hasUnsavedGatewayChange && Object.keys(gatewayFields).length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Shield className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              {__('Credentials')}
            </CardTitle>
            <CardDescription>{__('API credentials for')} {displayName(gatewayName)}</CardDescription>
          </CardHeader>
          <CardContent className="wsms-space-y-4">
            <div className="wsms-grid wsms-grid-cols-1 wsms-gap-4 md:wsms-grid-cols-2">
              {Object.entries(gatewayFields).map(([key, field]) => {
                const fieldValue = getSetting(field.id, '')
                const isPassword = key === 'password' || field.id.includes('password')

                if (field.type === 'select' && field.options) {
                  const options = Object.entries(field.options).map(([value, label]) => ({
                    value,
                    label,
                  }))
                  return (
                    <SelectField
                      key={field.id}
                      label={field.name}
                      description={field.desc}
                      value={fieldValue}
                      onValueChange={(value) => updateSetting(field.id, value)}
                      placeholder={field.placeholder || `Select ${field.name}`}
                      options={options}
                    />
                  )
                }

                return (
                  <InputField
                    key={field.id}
                    label={field.name}
                    description={field.desc}
                    type={isPassword ? 'password' : 'text'}
                    value={fieldValue}
                    onChange={(e) => updateSetting(field.id, e.target.value)}
                    placeholder={field.placeholder || ''}
                  />
                )
              })}
            </div>

            <div className="wsms-flex wsms-items-center wsms-justify-between wsms-pt-4 wsms-border-t wsms-border-border">
              <div className="wsms-flex wsms-items-center wsms-gap-3">
                <Button size="sm" onClick={handleTestConnection} disabled={testing}>
                  {testing ? (
                    <>
                      <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-1 wsms-animate-spin" />
                      {__('Testing...')}
                    </>
                  ) : (
                    <>
                      <Send className="wsms-h-4 wsms-w-4 wsms-mr-1" />
                      {__('Test Connection')}
                    </>
                  )}
                </Button>
                <span className="wsms-text-[12px] wsms-text-muted-foreground">
                  {__('Verify your credentials are working')}
                </span>
              </div>
            </div>

            {/* Raw API Response */}
            {connectionTested && rawResponse && (
              <CollapsibleSection
                title={__('API Response')}
                description={__('Raw response from the gateway for debugging')}
                defaultOpen={false}
              >
                <div className="wsms-rounded-lg wsms-bg-muted wsms-p-3 wsms-font-mono wsms-text-[12px] wsms-overflow-x-auto">
                  <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-mb-2 wsms-text-muted-foreground">
                    <Code className="wsms-h-3.5 wsms-w-3.5" />
                    <span>{__('Gateway Response:')}</span>
                  </div>
                  <pre className="wsms-whitespace-pre-wrap wsms-break-all wsms-text-foreground">
                    {rawResponse}
                  </pre>
                </div>
              </CollapsibleSection>
            )}
          </CardContent>
        </Card>
      )}

      {/* Delivery Settings */}
      <Card className="wsms-relative wsms-z-10">
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Zap className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Delivery Settings')}
          </CardTitle>
          <CardDescription>
            {__('Configure how messages are processed and delivered')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SelectField
            label={__('Delivery Method')}
            description={__('Select the dispatch method for SMS messages: instant send via API, delayed send at set times, or batch send for large recipient lists. For lists exceeding 20 recipients, batch sending is automatically selected.')}
            value={deliveryMethod}
            onValueChange={setDeliveryMethod}
            placeholder={__('Select method')}
            options={[
              { value: 'api_direct_send', label: __('Send SMS Instantly: Activates immediate dispatch of messages via API upon request.') },
              { value: 'api_async_send', label: __('Scheduled SMS Delivery: Configures API to send messages at predetermined times.') },
              { value: 'api_queued_send', label: __('Batch SMS Queue: Lines up messages for grouped sending, enhancing efficiency for bulk dispatch.') },
            ]}
          />

          {deliveryMethod === 'api_queued_send' && (
            <Tip variant="info">
              {__('Queue mode requires a cron job to process messages. Configure WP-Cron or set up a real cron job for reliable delivery.')}
            </Tip>
          )}

          <SectionDivider>{__('Message Formatting')}</SectionDivider>

          <div className="wsms-divide-y wsms-divide-border wsms-rounded-lg wsms-border wsms-border-border wsms-overflow-hidden">
            <SwitchField
              label={__('Enable Unicode')}
              description={__('Required for non-Latin characters (Arabic, Chinese, emoji). May reduce characters per SMS.')}
              checked={sendUnicode === '1'}
              onCheckedChange={(checked) => setSendUnicode(checked ? '1' : '')}
              className="wsms-px-4"
            />
            <SwitchField
              label={__('Auto-format Numbers')}
              description={__('Automatically remove spaces and special characters from phone numbers before sending.')}
              checked={cleanNumbers === '1'}
              onCheckedChange={(checked) => setCleanNumbers(checked ? '1' : '')}
              className="wsms-px-4"
            />
          </div>

          <SectionDivider>{__('Country Restrictions')}</SectionDivider>

          <div className="wsms-rounded-lg wsms-border wsms-border-border">
            <SwitchField
              label={__('Restrict to Specific Countries')}
              description={__('Only send SMS to phone numbers from selected countries.')}
              checked={localNumbersOnly === '1'}
              onCheckedChange={(checked) => setLocalNumbersOnly(checked ? '1' : '')}
              className="wsms-px-4"
            />
          </div>

          {localNumbersOnly === '1' && (
            <MultiSelectField
              label={__('Allowed Countries')}
              options={countriesByDialCode}
              value={localNumbersCountries}
              onValueChange={setLocalNumbersCountries}
              placeholder={__('Select countries...')}
              searchPlaceholder={__('Search countries...')}
              description={__('SMS will only be sent to numbers from these countries.')}
            />
          )}
        </CardContent>
      </Card>

      {/* Credit Display */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Wallet className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Credit Display')}
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="wsms-divide-y wsms-divide-border wsms-rounded-lg wsms-border wsms-border-border wsms-overflow-hidden">
            <SwitchField
              label={__('Show Credit in Menu')}
              description={__('Display your SMS credit balance in the WordPress admin menu bar.')}
              checked={creditInMenu === '1'}
              onCheckedChange={(checked) => setCreditInMenu(checked ? '1' : '')}
              className="wsms-px-4"
            />
            <SwitchField
              label={__('Show Credit on Send Page')}
              description={__('Display your remaining SMS credits when composing messages.')}
              checked={creditInSendSms === '1'}
              onCheckedChange={(checked) => setCreditInSendSms(checked ? '1' : '')}
              className="wsms-px-4"
            />
          </div>
        </CardContent>
      </Card>

      {/* Setup Wizard */}
      <Card>
        <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-5 wsms-py-4">
          <div>
            <p className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">
              {__('Setup Wizard')}
            </p>
            <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mt-0.5">
              {__('Re-run the guided setup to update your gateway configuration')}
            </p>
          </div>
          <Button
            variant="outline"
            size="sm"
            onClick={() => window.wpSmsOpenWizard?.()}
          >
            <RotateCcw className="wsms-h-4 wsms-w-4 wsms-mr-1" />
            {__('Re-run Wizard')}
          </Button>
        </div>
      </Card>
    </div>
  )
}
