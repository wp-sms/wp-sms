import { useMemo, useRef, useState } from 'react'
import { ComposableMap, Geographies, Geography } from 'react-simple-maps'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { cn } from '@/lib/utils'

interface CountryData {
  code: string
  attempts: number
  success: number
  successRate: number
  topChannel: string
}

interface GeoHeatmapData {
  countries: CountryData[]
}

interface GeoHeatmapWidgetProps {
  label: string
  data: GeoHeatmapData
  className?: string
}

interface DataRange {
  min: number
  max: number
  label: string
  color: string
}

const dataRanges: DataRange[] = [
  { min: 0, max: 0, label: 'No data', color: '#f1f3f5' },
  { min: 1, max: 40000, label: '< 40k', color: '#FDE8C8' },
  { min: 40000, max: 80000, label: '40k - 80k', color: '#F7B267' },
  { min: 80000, max: 200000, label: '80k - 200k', color: '#F08A24' },
  { min: 200000, max: Infinity, label: '>200k', color: '#D96E00' },
]

const geoUrl =
  'https://raw.githubusercontent.com/nvkelso/natural-earth-vector/master/geojson/ne_110m_admin_0_countries.geojson'

export function GeoHeatmap({ label, data, className }: GeoHeatmapWidgetProps) {
  const containerRef = useRef<HTMLDivElement>(null)
  const [tooltip, setTooltip] = useState<{
    visible: boolean
    x: number
    y: number
    content: React.ReactNode
  }>({ visible: false, x: 0, y: 0, content: '' })

  const countryLookup = useMemo(() => {
    const m = new Map<string, CountryData>()
    ;(data.countries || []).forEach((c) => {
      const code = String(c.code).toUpperCase()
      m.set(code, c)
    })
    return m
  }, [data])

  const getColorForAttempts = (attempts: number | null): string => {
    if (attempts == null) return '#f1f3f5'
    if (attempts < 40000) return '#FDE8C8'
    if (attempts < 80000) return '#F7B267'
    if (attempts < 200000) return '#F08A24'
    return '#D96E00'
  }

  const getCountryMatch = (geo: {
    properties: Record<string, unknown>
    id?: string
  }): { iso: string; data: CountryData | null } => {
    const props = geo.properties || {}

    const iso2 = String(props.ISO_A2 || props.iso_a2 || '').toUpperCase()
    const iso3 = String(props.ISO_A3 || props.iso_a3 || '').toUpperCase()

    if (iso2 && iso2 !== '-99' && countryLookup.has(iso2)) {
      return { iso: iso2, data: countryLookup.get(iso2)! }
    }

    if (iso3 && iso3 !== '-99' && countryLookup.has(iso3)) {
      return { iso: iso3, data: countryLookup.get(iso3)! }
    }

    return { iso: iso2 || iso3 || '', data: null }
  }

  const makeContent = (name: string, countryData: CountryData | null) => {
    if (!countryData) {
      return (
        <div className="text-sm">
          <div className="font-semibold">{name}</div>
          <div className="mt-1 text-xs text-muted-foreground">No data</div>
        </div>
      )
    }

    return (
      <div className="text-sm">
        <div className="font-semibold mb-2">{name}</div>
        <div className="mt-1 text-xs">Attempts: {countryData.attempts.toLocaleString()}</div>
        <div className="text-xs">Success: {countryData.success.toLocaleString()}</div>
        <div className="text-xs">Success rate: {countryData.successRate.toFixed(1)}%</div>
        <div className="text-xs">Top channel: {countryData.topChannel}</div>
      </div>
    )
  }

  return (
    <Card className={cn('h-full flex flex-col', className)}>
      <CardHeader className="pb-2">
        <CardTitle>{label}</CardTitle>
      </CardHeader>
      <CardContent className="flex-1 flex flex-col px-6 pb-6">
        <div
          ref={containerRef}
          className="flex-1 relative bg-muted/20 rounded-lg overflow-hidden"
          style={{ minHeight: '450px' }}
        >
          <ComposableMap
            projection="geoEqualEarth"
            projectionConfig={{
              rotate: [0, 0, 0],
              center: [15, 15],
              scale: 180,
            }}
            width={800}
            height={400}
            style={{
              width: '100%',
              height: '100%',
            }}
          >
            <Geographies geography={geoUrl}>
              {({ geographies }) =>
                geographies
                  .filter((geo) => {
                    const iso = (
                      geo.properties.ISO_A3 ||
                      geo.properties.ADM0_A3 ||
                      geo.properties.iso_a3 ||
                      geo.id ||
                      ''
                    ).toUpperCase()
                    const name = (geo.properties.NAME || geo.properties.name || '').toLowerCase()
                    return iso !== 'ATA' && !name.includes('antarctica')
                  })
                  .map((geo) => {
                    const { data: countryData } = getCountryMatch(geo)
                    const attempts = countryData?.attempts ?? null
                    const fill = getColorForAttempts(attempts)
                    const name = geo.properties.NAME || geo.properties.name || 'Unknown'
                    const key = geo.rsmKey

                    return (
                      <g
                        key={key}
                        onMouseEnter={(e: React.MouseEvent) => {
                          if (!containerRef.current) return
                          const rect = containerRef.current.getBoundingClientRect()
                          const content = makeContent(name, countryData)
                          setTooltip({
                            visible: true,
                            x: e.clientX - rect.left,
                            y: e.clientY - rect.top,
                            content,
                          })
                        }}
                        onMouseMove={(e: React.MouseEvent) => {
                          if (!containerRef.current || !tooltip.visible) return
                          const rect = containerRef.current.getBoundingClientRect()
                          setTooltip((t) => ({
                            ...t,
                            x: e.clientX - rect.left,
                            y: e.clientY - rect.top,
                          }))
                        }}
                        onMouseLeave={() => setTooltip({ visible: false, x: 0, y: 0, content: '' })}
                      >
                        <Geography
                          geography={geo}
                          style={{
                            default: {
                              fill,
                              outline: 'none',
                              stroke: '#e9edf0',
                              strokeWidth: 0.5,
                              transition: 'fill 180ms ease',
                              pointerEvents: 'all',
                            },
                            hover: {
                              fill: attempts == null ? '#e6e9ec' : '#ffb84d',
                              outline: 'none',
                              cursor: 'pointer',
                            },
                            pressed: { outline: 'none' },
                          }}
                        />
                      </g>
                    )
                  })
              }
            </Geographies>
          </ComposableMap>

          {/* Tooltip */}
          {tooltip.visible && (
            <div
              className="pointer-events-none absolute z-50 max-w-xs p-3 rounded-lg shadow-lg bg-popover text-popover-foreground border"
              style={{
                left: tooltip.x + 12,
                top: tooltip.y + 12,
                transform: 'translate(0, 0)',
              }}
            >
              {tooltip.content}
            </div>
          )}
        </div>

        {/* Legend */}
        <div className="flex items-center justify-center gap-6 mt-6 flex-wrap">
          {dataRanges.map((range) => (
            <div key={range.label} className="flex items-center gap-2">
              <div className="w-4 h-4 rounded-sm border border-gray-300" style={{ backgroundColor: range.color }} />
              <span className="text-sm font-medium text-foreground">{range.label}</span>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )
}
