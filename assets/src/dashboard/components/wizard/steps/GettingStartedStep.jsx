import React from 'react'
import { Shield, Zap, Globe, CheckCircle } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import PhoneInput from '../components/PhoneInput'
import { __ } from '@/lib/utils'

/**
 * Getting Started step - Welcome message and admin phone number collection
 */
export default function GettingStartedStep({
  phoneNumber,
  countryCode,
  onPhoneChange,
  isValid,
  onValidChange,
}) {
  const features = [
    { icon: Zap, label: __('200+ Gateways') },
    { icon: Globe, label: __('Global Coverage') },
    { icon: Shield, label: __('Secure & Reliable') },
  ]

  return (
    <div className="wsms-max-w-lg wsms-mx-auto">
      {/* Header */}
      <div className="wsms-text-center wsms-mb-6">
        <h2 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-1">
          {__('Welcome to WP SMS')}
        </h2>
        <p className="wsms-text-[12px] wsms-text-muted-foreground">
          {__("Let's get your SMS gateway configured in just a few steps.")}
        </p>
      </div>

      {/* Features */}
      <div className="wsms-flex wsms-items-center wsms-justify-center wsms-gap-5 wsms-mb-6">
        {features.map((feature, index) => {
          const Icon = feature.icon
          return (
            <div key={index} className="wsms-flex wsms-items-center wsms-gap-1.5 wsms-text-muted-foreground">
              <Icon className="wsms-h-3.5 wsms-w-3.5 wsms-text-primary" />
              <span className="wsms-text-[11px] wsms-font-medium">{feature.label}</span>
            </div>
          )
        })}
      </div>

      {/* Phone Input Card */}
      <Card>
        <CardHeader>
          <CardTitle>
            {__('Your Mobile Number')}
            <span className="wsms-text-destructive wsms-ml-0.5">*</span>
          </CardTitle>
          <CardDescription>
            {__("We'll use this for test SMS and admin notifications.")}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <PhoneInput
            value={phoneNumber}
            onChange={onPhoneChange}
            onValidChange={onValidChange}
            initialCountry="us"
          />
          {isValid && phoneNumber && (
            <div className="wsms-flex wsms-items-center wsms-gap-1.5 wsms-mt-3 wsms-text-success">
              <CheckCircle className="wsms-h-3.5 wsms-w-3.5" />
              <span className="wsms-text-[11px] wsms-font-medium">
                {__('Valid phone number')}
              </span>
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  )
}
