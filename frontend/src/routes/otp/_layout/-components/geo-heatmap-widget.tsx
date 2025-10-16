import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import type { GeoHeatmapData } from '@/types/report'

interface GeoHeatmapWidgetProps {
  label: string
  data: GeoHeatmapData
  className?: string
}

export function GeoHeatmapWidget({ label, data, className }: GeoHeatmapWidgetProps) {
  // Sort countries by attempts (descending)
  const sortedCountries = [...data.countries].sort((a, b) => b.attempts - a.attempts)

  return (
    <Card className={cn('h-full', className)}>
      <CardHeader>
        <CardTitle>{label}</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="overflow-auto max-h-[400px]">
          <table className="w-full">
            <thead className="sticky top-0 bg-background border-b">
              <tr className="text-sm text-muted-foreground">
                <th className="text-left py-2 px-2 font-medium">Country</th>
                <th className="text-right py-2 px-2 font-medium">Attempts</th>
                <th className="text-right py-2 px-2 font-medium">Success</th>
                <th className="text-right py-2 px-2 font-medium">Rate</th>
                <th className="text-right py-2 px-2 font-medium">Avg Duration</th>
                <th className="text-left py-2 px-2 font-medium">Top Channel</th>
              </tr>
            </thead>
            <tbody>
              {sortedCountries.map((country, index) => {
                // Determine color based on success rate
                const rateColor =
                  country.successRate >= 90
                    ? 'text-green-600 dark:text-green-400'
                    : country.successRate >= 70
                      ? 'text-yellow-600 dark:text-yellow-400'
                      : 'text-red-600 dark:text-red-400'

                return (
                  <tr
                    key={country.code}
                    className={cn(
                      'border-b text-sm hover:bg-muted/50 transition-colors',
                      index % 2 === 0 ? 'bg-muted/20' : ''
                    )}
                  >
                    <td className="py-2 px-2">
                      <div className="flex items-center gap-2">
                        <span className="text-lg" title={country.code}>
                          {getFlagEmoji(country.code)}
                        </span>
                        <span className="font-medium">{country.code}</span>
                      </div>
                    </td>
                    <td className="text-right py-2 px-2 font-mono">{country.attempts.toLocaleString()}</td>
                    <td className="text-right py-2 px-2 font-mono">{country.success.toLocaleString()}</td>
                    <td className={cn('text-right py-2 px-2 font-mono font-semibold', rateColor)}>
                      {country.successRate.toFixed(1)}%
                    </td>
                    <td className="text-right py-2 px-2 font-mono">{formatDuration(country.avgDuration)}</td>
                    <td className="py-2 px-2">
                      <div className="flex items-center gap-1">
                        <span className="text-muted-foreground">{country.topChannel}</span>
                        <span className="text-xs text-muted-foreground">
                          ({country.topChannelPercentage.toFixed(0)}%)
                        </span>
                      </div>
                    </td>
                  </tr>
                )
              })}
            </tbody>
          </table>
          {sortedCountries.length === 0 && (
            <div className="text-center py-8 text-muted-foreground">No data available</div>
          )}
        </div>
      </CardContent>
    </Card>
  )
}

function getFlagEmoji(countryCode: string): string {
  if (countryCode.length !== 2) return ''
  const codePoints = countryCode
    .toUpperCase()
    .split('')
    .map((char) => 127397 + char.charCodeAt(0))
  return String.fromCodePoint(...codePoints)
}

function formatDuration(seconds: number): string {
  if (seconds < 60) {
    return `${seconds.toFixed(1)}s`
  }
  const minutes = Math.floor(seconds / 60)
  const remainingSeconds = seconds % 60
  return `${minutes}m ${remainingSeconds.toFixed(0)}s`
}
