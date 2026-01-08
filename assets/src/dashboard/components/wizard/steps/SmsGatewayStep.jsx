import React, { useState, useMemo } from 'react'
import { Search, CheckCircle, Radio } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { getWpSettings, cn, __ } from '@/lib/utils'

/**
 * SMS Gateway step - Select SMS provider from available gateways
 */
export default function SmsGatewayStep({
  selectedGateway,
  onGatewaySelect,
}) {
  const { gateways = {} } = getWpSettings()
  const [searchQuery, setSearchQuery] = useState('')

  // Build flat gateway list
  const gatewayList = useMemo(() => {
    const list = []
    if (gateways && typeof gateways === 'object') {
      Object.entries(gateways).forEach(([region, providers]) => {
        if (typeof providers === 'object') {
          Object.entries(providers).forEach(([key, name]) => {
            list.push({ key, name })
          })
        }
      })
    }
    return list
  }, [gateways])

  // Filter gateways based on search
  const query = searchQuery.toLowerCase().trim()
  const filteredGateways = gatewayList
    .filter((g) => g.key && g.key.trim() !== '' && !g.name.toLowerCase().includes('please select'))
    .filter((g) => {
      if (!query) return true
      return g.name.toLowerCase().includes(query) || g.key.toLowerCase().includes(query)
    })

  return (
    <div className="wsms-max-w-2xl wsms-mx-auto">
      {/* Header */}
      <div className="wsms-text-center wsms-mb-6">
        <h2 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-1">
          {__('Choose Your SMS Gateway')}
        </h2>
        <p className="wsms-text-[12px] wsms-text-muted-foreground">
          {__('Select your SMS provider from 200+ supported gateways.')}
        </p>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Radio className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('SMS Gateway')}
          </CardTitle>
          <CardDescription>{__('Search and select your gateway provider.')}</CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          {/* Search */}
          <div className="wsms-relative">
            <Search className="wsms-absolute wsms-left-3 wsms-top-1/2 wsms-h-4 wsms-w-4 wsms--translate-y-1/2 wsms-text-muted-foreground" />
            <Input
              placeholder={__('Search gateways...')}
              value={searchQuery}
              onChange={(e) => setSearchQuery(e.target.value)}
              className="wsms-pl-9"
            />
          </div>

          {/* Gateway List */}
          <div className="wsms-max-h-[280px] wsms-overflow-y-auto wsms-rounded-md wsms-border wsms-border-border wsms-p-4 wsms-scrollbar-thin wsms-bg-muted/30">
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
        </CardContent>
      </Card>
    </div>
  )
}
