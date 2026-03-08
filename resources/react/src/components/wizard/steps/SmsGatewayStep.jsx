import React from 'react'
import { Radio } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { __ } from '@/lib/utils'
import GatewaySelector from '@/components/GatewaySelector'

/**
 * SMS Gateway step - Select SMS provider from available gateways
 */
export default function SmsGatewayStep({
  selectedGateway,
  onGatewaySelect,
}) {
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
        <CardContent>
          <GatewaySelector
            selectedGateway={selectedGateway}
            onGatewaySelect={onGatewaySelect}
          />
        </CardContent>
      </Card>
    </div>
  )
}
