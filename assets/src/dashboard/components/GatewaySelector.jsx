import React, { useState, useMemo } from 'react'
import { Search, CheckCircle } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { getWpSettings, cn, __ } from '@/lib/utils'

/**
 * Reusable Gateway Selector component with search and region filter
 */
export default function GatewaySelector({
  selectedGateway,
  onGatewaySelect,
  maxHeight = '280px',
}) {
  const { gateways = {} } = getWpSettings()
  const [searchQuery, setSearchQuery] = useState('')
  const [selectedRegion, setSelectedRegion] = useState('')

  // Build flat gateway list with region info
  const gatewayList = useMemo(() => {
    const list = []
    if (gateways && typeof gateways === 'object') {
      Object.entries(gateways).forEach(([region, providers]) => {
        if (typeof providers === 'object') {
          Object.entries(providers).forEach(([key, name]) => {
            list.push({ key, name, region })
          })
        }
      })
    }
    return list
  }, [gateways])

  // Get unique regions for the filter, sorted alphabetically with 'global' first
  const regions = useMemo(() => {
    const uniqueRegions = [...new Set(
      gatewayList
        .filter((g) => g.key && g.key.trim() !== '' && !g.name.toLowerCase().includes('please select'))
        .map((g) => g.region)
    )].filter(Boolean)

    return uniqueRegions.sort((a, b) => {
      if (a.toLowerCase() === 'global') return -1
      if (b.toLowerCase() === 'global') return 1
      return a.localeCompare(b)
    })
  }, [gatewayList])

  // Filter gateways based on search and region
  const filteredGateways = useMemo(() => {
    let list = gatewayList.filter(
      (g) => g.key && g.key.trim() !== '' && !g.name.toLowerCase().includes('please select')
    )

    // Apply region filter
    if (selectedRegion && selectedRegion !== 'all') {
      list = list.filter((g) => g.region.toLowerCase() === selectedRegion.toLowerCase())
    }

    // Apply search filter
    if (searchQuery) {
      const query = searchQuery.toLowerCase()
      list = list.filter(
        (g) => g.name.toLowerCase().includes(query) || g.key.toLowerCase().includes(query)
      )
    }

    return list
  }, [gatewayList, searchQuery, selectedRegion])

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

        {/* Region Filter */}
        <div className="wsms-w-[180px] wsms-shrink-0">
          <Select value={selectedRegion} onValueChange={setSelectedRegion}>
            <SelectTrigger className="wsms-w-full wsms-capitalize">
              <SelectValue placeholder={__('All Regions')} />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="all">{__('All Regions')}</SelectItem>
              {regions.map((region) => (
                <SelectItem key={region} value={region} className="wsms-capitalize">
                  {region}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        </div>
      </div>

      {/* Gateway List */}
      <div
        className="wsms-overflow-y-auto wsms-rounded-md wsms-border wsms-border-border wsms-p-4 wsms-scrollbar-thin wsms-bg-muted/30"
        style={{ maxHeight }}
      >
        <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 md:wsms-grid-cols-3 lg:wsms-grid-cols-4">
          {filteredGateways.map((gateway, index) => (
            <button
              key={`${gateway.key}-${index}`}
              type="button"
              onClick={() => onGatewaySelect(gateway.key)}
              className={cn(
                'wsms-flex wsms-items-center wsms-gap-2 wsms-rounded-md wsms-border wsms-px-3 wsms-py-2 wsms-text-left wsms-text-[12px] wsms-transition-colors',
                selectedGateway === gateway.key
                  ? 'wsms-border-primary wsms-bg-primary/10 wsms-text-primary wsms-font-medium'
                  : 'wsms-border-border wsms-bg-card hover:wsms-bg-accent'
              )}
            >
              {selectedGateway === gateway.key && (
                <CheckCircle className="wsms-h-3.5 wsms-w-3.5 wsms-shrink-0" />
              )}
              <span className="wsms-truncate">{gateway.name}</span>
            </button>
          ))}
        </div>

        {filteredGateways.length === 0 && (
          <div className="wsms-py-8 wsms-text-center wsms-text-[12px] wsms-text-muted-foreground">
            {__('No gateways found')}
          </div>
        )}
      </div>

      {/* Selected Gateway */}
      {selectedGateway && (
        <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-px-3 wsms-py-2 wsms-rounded-md wsms-bg-primary/5 wsms-border wsms-border-primary/20">
          <CheckCircle className="wsms-h-4 wsms-w-4 wsms-text-primary" />
          <span className="wsms-text-[12px] wsms-text-foreground">
            <span className="wsms-text-muted-foreground">{__('Selected:')}</span>{' '}
            <span className="wsms-font-medium">
              {gatewayList.find((g) => g.key === selectedGateway)?.name || selectedGateway}
            </span>
          </span>
        </div>
      )}
    </div>
  )
}
