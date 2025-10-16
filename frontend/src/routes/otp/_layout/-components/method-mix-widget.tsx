import { Cell, Label, Pie, PieChart } from 'recharts'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import {
  ChartContainer,
  ChartLegend,
  ChartLegendContent,
  ChartTooltip,
  ChartTooltipContent,
} from '@/components/ui/chart'
import { cn } from '@/lib/utils'
import type { MethodMixData } from '@/types/report'

interface MethodMixWidgetProps {
  label: string
  data: MethodMixData
  className?: string
}

export function MethodMixWidget({ label, data, className }: MethodMixWidgetProps) {
  // Transform data for Recharts
  const chartData = data.labels.map((label, index) => ({
    name: label,
    value: data.data[index],
    fill: data.colors[index],
  }))

  // Calculate total for percentage display
  const totalValue = data.data.reduce((sum, value) => sum + value, 0)

  // Generate chart config
  const chartConfig = data.labels.reduce(
    (config, label, index) => {
      config[label] = {
        label: label,
        color: data.colors[index],
      }
      return config
    },
    {} as Record<string, { label: string; color: string }>
  )

  return (
    <Card className={cn('h-full', className)}>
      <CardHeader>
        <CardTitle>{label}</CardTitle>
      </CardHeader>
      <CardContent>
        <ChartContainer config={chartConfig} className="h-[300px]">
          <PieChart>
            <ChartTooltip
              content={
                <ChartTooltipContent
                  formatter={(value, name) => (
                    <div className="flex items-center justify-between gap-4">
                      <span>{name}:</span>
                      <span className="font-mono font-medium">
                        {value} ({((Number(value) / totalValue) * 100).toFixed(1)}%)
                      </span>
                    </div>
                  )}
                  hideLabel
                />
              }
            />
            <Pie data={chartData} dataKey="value" nameKey="name" innerRadius={60} outerRadius={100} strokeWidth={2}>
              {chartData.map((entry, index) => (
                <Cell key={`cell-${index}`} fill={entry.fill} />
              ))}
              <Label
                content={({ viewBox }) => {
                  if (viewBox && 'cx' in viewBox && 'cy' in viewBox) {
                    return (
                      <text x={viewBox.cx} y={viewBox.cy} textAnchor="middle" dominantBaseline="middle">
                        <tspan x={viewBox.cx} y={viewBox.cy} className="fill-foreground text-2xl font-bold">
                          {totalValue.toLocaleString()}
                        </tspan>
                        <tspan x={viewBox.cx} y={(viewBox.cy || 0) + 20} className="fill-muted-foreground text-xs">
                          Total
                        </tspan>
                      </text>
                    )
                  }
                }}
              />
            </Pie>
            <ChartLegend content={<ChartLegendContent />} />
          </PieChart>
        </ChartContainer>
      </CardContent>
    </Card>
  )
}
