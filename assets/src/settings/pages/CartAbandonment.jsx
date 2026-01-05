import React from 'react'
import {
  RotateCcw,
  ShoppingCart,
  MessageSquare,
  DollarSign,
  TrendingUp,
  Clock,
  ExternalLink,
  Settings,
  BarChart3,
} from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { __, getWpSettings } from '@/lib/utils'
import { useSettings } from '@/context/SettingsContext'

export default function CartAbandonment() {
  const { setCurrentPage } = useSettings()
  const { adminUrl } = getWpSettings()

  // Link to the legacy Cart Abandonment page
  const legacyPageUrl = `${adminUrl}admin.php?page=wp-sms-woo-pro-cart-abandonment`

  const features = [
    {
      icon: ShoppingCart,
      title: __('Track Abandoned Carts'),
      description: __('Automatically detect and track carts that customers leave behind before completing checkout.'),
      color: 'wsms-text-blue-500',
      bgColor: 'wsms-bg-blue-500/10',
    },
    {
      icon: MessageSquare,
      title: __('Automated SMS Recovery'),
      description: __('Send personalized SMS messages to remind customers about their abandoned carts.'),
      color: 'wsms-text-emerald-500',
      bgColor: 'wsms-bg-emerald-500/10',
    },
    {
      icon: DollarSign,
      title: __('Coupon Incentives'),
      description: __('Automatically generate and send discount coupons to encourage cart recovery.'),
      color: 'wsms-text-amber-500',
      bgColor: 'wsms-bg-amber-500/10',
    },
    {
      icon: TrendingUp,
      title: __('Recovery Analytics'),
      description: __('Track recovery rates, revenue recovered, and SMS effectiveness with detailed reports.'),
      color: 'wsms-text-purple-500',
      bgColor: 'wsms-bg-purple-500/10',
    },
  ]

  const metrics = [
    { label: __('Recoverable Carts'), icon: ShoppingCart, description: __('Carts waiting for recovery SMS') },
    { label: __('Recovered Revenue'), icon: DollarSign, description: __('Total value of recovered orders') },
    { label: __('SMS Sent'), icon: MessageSquare, description: __('Recovery messages sent') },
    { label: __('Pending SMS'), icon: Clock, description: __('Messages scheduled to send') },
  ]

  return (
    <div className="wsms-space-y-6">
      {/* Hero Section */}
      <div className="wsms-relative wsms-overflow-hidden wsms-rounded-lg wsms-bg-gradient-to-br wsms-from-primary/5 wsms-via-primary/10 wsms-to-transparent wsms-border wsms-border-primary/20">
        <div className="wsms-absolute wsms-top-0 wsms-right-0 wsms-w-32 wsms-h-32 wsms-bg-primary/5 wsms-rounded-full wsms--translate-y-1/2 wsms-translate-x-1/2" />
        <div className="wsms-relative wsms-p-6">
          <div className="wsms-flex wsms-items-start wsms-justify-between">
            <div className="wsms-flex wsms-items-start wsms-gap-4">
              <div className="wsms-flex wsms-h-12 wsms-w-12 wsms-items-center wsms-justify-center wsms-rounded-xl wsms-bg-primary/10 wsms-shrink-0">
                <RotateCcw className="wsms-h-6 wsms-w-6 wsms-text-primary" />
              </div>
              <div>
                <h2 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-1">
                  {__('Cart Abandonment Recovery')}
                </h2>
                <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-max-w-lg">
                  {__('Recover lost sales by automatically sending SMS reminders to customers who abandoned their shopping carts. Include personalized coupons to incentivize completion.')}
                </p>
              </div>
            </div>
            <div className="wsms-flex wsms-gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setCurrentPage('woocommerce-pro')}
              >
                <Settings className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                {__('Settings')}
              </Button>
              <Button size="sm" asChild>
                <a href={legacyPageUrl} target="_blank" rel="noopener noreferrer">
                  <BarChart3 className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                  {__('View Reports')}
                  <ExternalLink className="wsms-h-3.5 wsms-w-3.5 wsms-ml-2" />
                </a>
              </Button>
            </div>
          </div>
        </div>
      </div>

      {/* Metrics Overview */}
      <div className="wsms-grid wsms-grid-cols-4 wsms-gap-4">
        {metrics.map((metric) => {
          const Icon = metric.icon
          return (
            <Card key={metric.label}>
              <CardContent className="wsms-p-4">
                <div className="wsms-flex wsms-items-center wsms-gap-3">
                  <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-primary/10">
                    <Icon className="wsms-h-5 wsms-w-5 wsms-text-primary" />
                  </div>
                  <div>
                    <p className="wsms-text-[12px] wsms-text-muted-foreground">{metric.label}</p>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground/70">{metric.description}</p>
                  </div>
                </div>
              </CardContent>
            </Card>
          )
        })}
      </div>

      {/* Features Grid */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            {__('How It Works')}
          </CardTitle>
          <CardDescription>
            {__('Cart abandonment recovery helps you recapture lost revenue automatically')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4">
            {features.map((feature, index) => {
              const Icon = feature.icon
              return (
                <div
                  key={index}
                  className="wsms-flex wsms-items-start wsms-gap-4 wsms-p-4 wsms-rounded-lg wsms-border wsms-border-border hover:wsms-bg-muted/30 wsms-transition-colors"
                >
                  <div className={`wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg ${feature.bgColor} wsms-shrink-0`}>
                    <Icon className={`wsms-h-5 wsms-w-5 ${feature.color}`} />
                  </div>
                  <div>
                    <h3 className="wsms-text-[13px] wsms-font-medium wsms-text-foreground wsms-mb-1">
                      {feature.title}
                    </h3>
                    <p className="wsms-text-[12px] wsms-text-muted-foreground">
                      {feature.description}
                    </p>
                  </div>
                </div>
              )
            })}
          </div>
        </CardContent>
      </Card>

      {/* Quick Actions */}
      <Card>
        <CardHeader>
          <CardTitle>{__('Quick Actions')}</CardTitle>
        </CardHeader>
        <CardContent>
          <div className="wsms-flex wsms-gap-4">
            <Button variant="outline" asChild className="wsms-flex-1">
              <a href={legacyPageUrl} target="_blank" rel="noopener noreferrer">
                <BarChart3 className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                {__('View Abandoned Carts Report')}
                <ExternalLink className="wsms-h-3.5 wsms-w-3.5 wsms-ml-2" />
              </a>
            </Button>
            <Button
              variant="outline"
              className="wsms-flex-1"
              onClick={() => setCurrentPage('woocommerce-pro')}
            >
              <Settings className="wsms-h-4 wsms-w-4 wsms-mr-2" />
              {__('Configure Recovery Settings')}
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
