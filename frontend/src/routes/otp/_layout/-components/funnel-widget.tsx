import { Bar, BarChart, CartesianGrid, LabelList, XAxis, YAxis } from 'recharts'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { ChartContainer, ChartTooltip, ChartTooltipContent } from '@/components/ui/chart'
import { cn } from '@/lib/utils'
import type { JourneyFunnelsData } from '@/types/report'

interface FunnelWidgetProps {
  label: string
  data: JourneyFunnelsData
  funnelType: 'loginFunnel' | 'registrationFunnel'
  className?: string
}

export function FunnelWidget({ label, data, funnelType, className }: FunnelWidgetProps) {
  const funnel = data[funnelType]

  const chartData = funnel.stages.map((stage) => ({
    stage: stage.label,
    count: stage.count,
    dropoff: stage.dropoffPercentage || 0,
  }))

  const chartConfig = {
    count: {
      label: 'Count',
      color: 'hsl(var(--chart-1))',
    },
  }

  return (
    <Card className={cn('h-full', className)}>
      <CardHeader>
        <CardTitle>{label}</CardTitle>
      </CardHeader>
      <CardContent>
        <ChartContainer config={chartConfig} className="h-[300px]">
          <BarChart
            data={chartData}
            layout="vertical"
            margin={{
              left: 20,
              right: 20,
            }}
          >
            <CartesianGrid horizontal={false} />
            <YAxis dataKey="stage" type="category" tickLine={false} axisLine={false} width={120} className="text-xs" />
            <XAxis type="number" hide />
            <ChartTooltip
              cursor={false}
              content={
                <ChartTooltipContent
                  formatter={(value, name, item) => (
                    <>
                      <div className="flex flex-col gap-1">
                        <div className="flex items-center justify-between gap-4">
                          <span className="text-muted-foreground">Count:</span>
                          <span className="font-mono font-medium">{value}</span>
                        </div>
                        {item.payload.dropoff > 0 && (
                          <div className="flex items-center justify-between gap-4">
                            <span className="text-muted-foreground">Drop-off:</span>
                            <span className="font-mono font-medium text-red-500">
                              {item.payload.dropoff.toFixed(1)}%
                            </span>
                          </div>
                        )}
                      </div>
                    </>
                  )}
                />
              }
            />
            <Bar dataKey="count" fill="var(--color-count)" radius={4}>
              <LabelList dataKey="count" position="right" className="fill-foreground" fontSize={12} />
            </Bar>
          </BarChart>
        </ChartContainer>
      </CardContent>
    </Card>
  )
}
