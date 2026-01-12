import React from 'react'
import { Crown, Check, ExternalLink, Zap, Shield, BarChart3, MessageCircle } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { __ } from '@/lib/utils'

/**
 * All-in-One upsell step - Promote WSMS All-in-One features
 */
export default function AllInOneStep({ onSkip }) {
  const features = [
    {
      icon: MessageCircle,
      title: __('Two-Way SMS'),
      description: __('Receive replies and create auto-responses'),
    },
    {
      icon: BarChart3,
      title: __('WooCommerce'),
      description: __('Order notifications & cart abandonment'),
    },
    {
      icon: Shield,
      title: __('OTP & 2FA'),
      description: __('Secure login with SMS verification'),
    },
    {
      icon: Zap,
      title: __('Automations'),
      description: __('Trigger SMS based on user actions'),
    },
  ]

  const benefits = [
    __('Priority email support'),
    __('Regular updates and new features'),
    __('Access to premium gateways'),
    __('Detailed analytics and reports'),
  ]

  return (
    <div className="wsms-max-w-2xl wsms-mx-auto">
      {/* Header */}
      <div className="wsms-text-center wsms-mb-8">
        <div className="wsms-flex wsms-justify-center wsms-mb-4">
          <div className="wsms-h-14 wsms-w-14 wsms-rounded-2xl wsms-bg-gradient-to-br wsms-from-amber-400 wsms-to-orange-500 wsms-flex wsms-items-center wsms-justify-center wsms-shadow-lg wsms-shadow-orange-500/25">
            <Crown className="wsms-h-7 wsms-w-7 wsms-text-white" />
          </div>
        </div>
        <h2 className="wsms-text-xl wsms-font-bold wsms-text-foreground wsms-mb-2">
          {__('Unlock More with All-in-One')}
        </h2>
        <p className="wsms-text-[13px] wsms-text-muted-foreground">
          {__('Take your SMS communications to the next level with powerful features.')}
        </p>
      </div>

      {/* Features Grid */}
      <div className="wsms-grid wsms-grid-cols-2 wsms-gap-3 wsms-mb-6">
        {features.map((feature, index) => {
          const Icon = feature.icon
          return (
            <div
              key={index}
              className="wsms-rounded-xl wsms-border wsms-border-border wsms-bg-card wsms-p-4 wsms-shadow-sm wsms-transition-shadow hover:wsms-shadow-md"
            >
              <div className="wsms-flex wsms-items-start wsms-gap-3">
                <div className="wsms-flex wsms-h-9 wsms-w-9 wsms-shrink-0 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-primary/10">
                  <Icon className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                </div>
                <div className="wsms-min-w-0">
                  <h3 className="wsms-text-[13px] wsms-font-semibold wsms-text-foreground wsms-mb-0.5">
                    {feature.title}
                  </h3>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-leading-relaxed">
                    {feature.description}
                  </p>
                </div>
              </div>
            </div>
          )
        })}
      </div>

      {/* Benefits List */}
      <div className="wsms-rounded-xl wsms-border wsms-border-amber-200 wsms-bg-amber-50/50 dark:wsms-border-amber-900/30 dark:wsms-bg-amber-950/20 wsms-p-5 wsms-mb-6">
        <h3 className="wsms-text-[13px] wsms-font-semibold wsms-text-foreground wsms-mb-3">
          {__('All All-in-One plans include:')}
        </h3>
        <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2">
          {benefits.map((item, index) => (
            <div key={index} className="wsms-flex wsms-items-center wsms-gap-2">
              <div className="wsms-h-5 wsms-w-5 wsms-rounded-full wsms-bg-amber-500/20 wsms-flex wsms-items-center wsms-justify-center wsms-shrink-0">
                <Check className="wsms-h-3 wsms-w-3 wsms-text-amber-600 dark:wsms-text-amber-400" />
              </div>
              <span className="wsms-text-[12px] wsms-text-foreground">{item}</span>
            </div>
          ))}
        </div>
      </div>

      {/* CTA Buttons */}
      <div className="wsms-flex wsms-flex-col wsms-items-center wsms-gap-3">
        <Button
          asChild
          size="lg"
          className="wsms-min-w-[220px] wsms-bg-gradient-to-r wsms-from-amber-500 wsms-to-orange-500 hover:wsms-from-amber-600 hover:wsms-to-orange-600 wsms-shadow-lg wsms-shadow-orange-500/25"
        >
          <a
            href="https://wp-sms-pro.com/pricing/"
            target="_blank"
            rel="noopener noreferrer"
          >
            {__('View Plans')}
            <ExternalLink className="wsms-h-4 wsms-w-4 wsms-ml-2" />
          </a>
        </Button>
        <Button variant="ghost" onClick={onSkip} className="wsms-text-muted-foreground">
          {__('Skip for now')}
        </Button>
      </div>
    </div>
  )
}
