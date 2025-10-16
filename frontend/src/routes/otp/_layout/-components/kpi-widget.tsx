import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { cn } from '@/lib/utils'
import type { KPI } from '@/types/report'

interface KpiWidgetProps {
  label: string
  kpis: KPI[]
  className?: string
}

function formatValue(value: number, format: KPI['format']) {
  switch (format) {
    case 'percentage':
      return `${value.toFixed(1)}%`
    case 'seconds':
      if (value < 60) {
        return `${value.toFixed(1)}s`
      }
      return `${(value / 60).toFixed(1)}m`
    case 'number':
    default:
      return value.toLocaleString()
  }
}

export function KpiWidget({ label, kpis, className }: KpiWidgetProps) {
  return (
    <Card className={cn('h-full', className)}>
      <CardHeader>
        <CardTitle>{label}</CardTitle>
      </CardHeader>
      <CardContent>
        <div className="grid gap-4">
          {kpis.map((kpi) => (
            <div key={kpi.key} className="flex flex-col gap-1">
              <span className="text-sm text-muted-foreground">{kpi.label}</span>
              <span className="text-2xl font-bold">{formatValue(kpi.value, kpi.format)}</span>
            </div>
          ))}
        </div>
      </CardContent>
    </Card>
  )
}
