import React, { useState, useMemo } from 'react'
import { Search, CheckCircle, Star, Loader2 } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { SearchableSelect } from '@/components/ui/searchable-select'
import { cn, __, countryCodeToFlag, getGatewayLogo } from '@/lib/utils'
import { GatewayCard, GatewayCardMinimal } from '@/components/GatewayCard'
import useGatewayRegistry from '@/hooks/useGatewayRegistry'

/**
 * Reusable Gateway Selector component with search, region filter, and API-sourced data
 */
export default function GatewaySelector({
  selectedGateway,
  onGatewaySelect,
  maxHeight = '280px',
}) {
  const { gateways, regions, source, isLoading, error } = useGatewayRegistry()
  const [searchQuery, setSearchQuery] = useState('')
  const [selectedRegion, setSelectedRegion] = useState('')

  const isApiSource = source === 'api'

  // Build region options (no flags — flags appear in the gateway list)
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

  // Build region name lookup from regions data
  const regionNameMap = useMemo(() => {
    const map = {}
    regions.forEach((r) => { map[r.slug] = r.name })
    return map
  }, [regions])

  // Filter gateways based on search and region
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

  // Split into recommended and rest when no search/filter active
  const { recommended, rest } = useMemo(() => {
    if (searchQuery || (selectedRegion && selectedRegion !== 'all') || !isApiSource) {
      return { recommended: [], rest: filteredGateways }
    }
    return {
      recommended: filteredGateways.filter((g) => g.recommended),
      rest: filteredGateways.filter((g) => !g.recommended),
    }
  }, [filteredGateways, searchQuery, selectedRegion, isApiSource])

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

  const handleSelect = (slug) => onGatewaySelect(slug)

  const CardComponent = isApiSource ? GatewayCard : GatewayCardMinimal

  const getFeatureTags = (gateway) => {
    if (!gateway.features) return []
    const map = { bulk_send: 'Bulk', mms: 'MMS', incoming_sms: 'SMS-in', whatsapp: 'WhatsApp' }
    return Object.entries(map)
      .filter(([key]) => gateway.features[key])
      .map(([, label]) => label)
      .slice(0, 3)
  }

  const renderCard = (gateway) => (
    <CardComponent
      key={gateway.slug}
      gateway={isApiSource ? { ...gateway, logo: getGatewayLogo(gateway) } : gateway}
      isSelected={selectedGateway === gateway.slug}
      onClick={handleSelect}
      showFeatures={isApiSource}
      featureTags={isApiSource ? getFeatureTags(gateway) : []}
    />
  )

  if (isLoading) {
    return (
      <div className="wsms-flex wsms-items-center wsms-justify-center wsms-py-12 wsms-text-muted-foreground">
        <Loader2 className="wsms-h-5 wsms-w-5 wsms-animate-spin wsms-mr-2" />
        <span className="wsms-text-[12px]">{__('Loading gateways...')}</span>
      </div>
    )
  }

  if (error && gateways.length === 0) {
    return (
      <div className="wsms-py-8 wsms-text-center wsms-text-[12px] wsms-text-destructive">
        {__('Failed to load gateways. Please refresh the page.')}
      </div>
    )
  }

  return (
    <div className="wsms-space-y-4">
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

      {/* Gateway List */}
      <div
        className="wsms-overflow-y-auto wsms-rounded-md wsms-border wsms-border-border wsms-p-4 wsms-scrollbar-thin wsms-bg-muted/30"
        style={{ maxHeight }}
      >
        {countryGroups ? (
          <div className="wsms-space-y-5">
            {countryGroups.sortedCodes.map((code) => (
              <div key={code}>
                <p className="wsms-mb-2 wsms-text-[12px] wsms-font-semibold wsms-text-foreground">
                  {countryCodeToFlag(code)} {countryNameMap[code] || code}
                </p>
                <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 md:wsms-grid-cols-3 lg:wsms-grid-cols-4">
                  {countryGroups.groups[code].map(renderCard)}
                </div>
              </div>
            ))}
            {countryGroups.ungrouped.length > 0 && (
              <div>
                <p className="wsms-mb-2 wsms-text-[12px] wsms-font-semibold wsms-text-foreground">
                  {__('Other')}
                </p>
                <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 md:wsms-grid-cols-3 lg:wsms-grid-cols-4">
                  {countryGroups.ungrouped.map(renderCard)}
                </div>
              </div>
            )}
          </div>
        ) : regionGroups ? (
          <div className="wsms-space-y-5">
            {/* Recommended */}
            {recommended.length > 0 && (
              <div>
                <p className="wsms-mb-2 wsms-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-font-semibold wsms-uppercase wsms-text-muted-foreground wsms-tracking-wide">
                  <Star className="wsms-h-3 wsms-w-3 wsms-text-amber-500" />
                  {__('Recommended')}
                </p>
                <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 md:wsms-grid-cols-3 lg:wsms-grid-cols-4">
                  {recommended.map(renderCard)}
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
                  {regionGroups.globalList.map(renderCard)}
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
                    {regionGroups.groups[slug].map(renderCard)}
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
                  {recommended.map(renderCard)}
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
                  {rest.map(renderCard)}
                </div>
              </div>
            )}
          </>
        )}

        {filteredGateways.length === 0 && (
          <div className="wsms-py-8 wsms-text-center wsms-text-[12px] wsms-text-muted-foreground">
            {__('No gateways found')}
          </div>
        )}
      </div>

      {/* Selected Gateway */}
      {selectedGateway && (
        <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-px-3 wsms-py-2 wsms-rounded-md wsms-bg-primary/5 wsms-border wsms-border-primary/20">
          {isApiSource && (() => {
            const gw = gateways.find((g) => g.slug === selectedGateway)
            return gw?.logo ? (
              <img src={gw.logo} alt="" className="wsms-h-5 wsms-w-5 wsms-rounded-sm wsms-object-contain" onError={(e) => { e.target.style.display = 'none' }} />
            ) : (
              <CheckCircle className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            )
          })()}
          {(!isApiSource) && <CheckCircle className="wsms-h-4 wsms-w-4 wsms-text-primary" />}
          <span className="wsms-text-[12px] wsms-text-foreground">
            <span className="wsms-text-muted-foreground">{__('Selected:')}</span>{' '}
            <span className="wsms-font-medium">
              {gateways.find((g) => g.slug === selectedGateway)?.name || selectedGateway}
            </span>
          </span>
        </div>
      )}
    </div>
  )
}
