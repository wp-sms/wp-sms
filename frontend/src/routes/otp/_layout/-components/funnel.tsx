import { ArrowRight } from 'lucide-react'

import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { cn } from '@/lib/utils'

interface FunnelWidgetProps {
  label: string
  data: JourneyFunnelsData
  className?: string
}

export function Funnel({ label, data, className }: FunnelWidgetProps) {
  const loginHasData = data.loginFunnel?.stages && data.loginFunnel.stages.length > 0
  const registrationHasData = data.registrationFunnel?.stages && data.registrationFunnel.stages.length > 0

  if (!loginHasData && !registrationHasData) {
    return null
  }

  const renderFunnel = (stages: FunnelStage[], title: string) => (
    <div className="mb-6 last:mb-0">
      <h3 className="text-sm font-semibold mb-4">{title}</h3>
      <div className="flex items-center gap-4">
        {stages.map((stage: FunnelStage, index: number) => (
          <>
            <div
              key={stage.label}
              className="flex flex-col items-center justify-center bg-muted/50 rounded-lg p-6 min-w-[140px] flex-1"
            >
              <div className="text-sm font-medium text-muted-foreground mb-2">{stage.label}</div>
              <div className="text-2xl font-bold mb-1">{stage.count.toLocaleString()}</div>
              <div className="text-sm text-muted-foreground">
                {stage.dropoffPercentage !== undefined ? `${stage.dropoffPercentage.toFixed(1)}%` : '-'}
              </div>
            </div>
            {index < stages.length - 1 && (
              <ArrowRight key={`arrow-${index}`} className="w-5 h-5 text-muted-foreground flex-shrink-0" />
            )}
          </>
        ))}
      </div>
    </div>
  )

  return (
    <Card className={cn('h-full', className)}>
      <CardHeader>
        <CardTitle>{label}</CardTitle>
      </CardHeader>
      <CardContent>
        {loginHasData && renderFunnel(data.loginFunnel.stages, 'Login Funnel')}
        {registrationHasData && renderFunnel(data.registrationFunnel.stages, 'Registration Funnel')}
      </CardContent>
    </Card>
  )
}
