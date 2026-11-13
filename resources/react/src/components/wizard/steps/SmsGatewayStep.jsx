import { __ } from '@wordpress/i18n'
import React from 'react'
import GatewaySelector from '@/components/GatewaySelector'

/**
 * SMS Gateway step - Select SMS provider from available gateways
 */
export default function SmsGatewayStep({
  selectedGateway,
  onGatewaySelect,
}) {
  return (
    <div className="wsms-max-w-6xl wsms-mx-auto">
      <GatewaySelector
        selectedGateway={selectedGateway}
        onGatewaySelect={onGatewaySelect}
        maxHeight="max(320px, calc(100vh - 520px))"
      />
    </div>
  )
}
