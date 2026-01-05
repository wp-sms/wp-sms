import React from 'react'
import {
  Megaphone,
  Target,
  Clock,
  Zap,
  Filter,
  Users,
  Package,
  Tag,
  ExternalLink,
  Plus,
  List,
} from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { __, getWpSettings } from '@/lib/utils'

export default function SmsCampaigns() {
  const { adminUrl } = getWpSettings()

  // Links to the legacy SMS Campaigns pages
  const campaignsListUrl = `${adminUrl}edit.php?post_type=sms-campaign`
  const newCampaignUrl = `${adminUrl}post-new.php?post_type=sms-campaign`

  const features = [
    {
      icon: Target,
      title: __('Smart Targeting'),
      description: __('Target customers based on products purchased, order status, coupon usage, or product types.'),
      color: 'wsms-text-blue-500',
      bgColor: 'wsms-bg-blue-500/10',
    },
    {
      icon: Clock,
      title: __('Flexible Scheduling'),
      description: __('Send at a specific date/time or automatically after order placement with custom delays.'),
      color: 'wsms-text-emerald-500',
      bgColor: 'wsms-bg-emerald-500/10',
    },
    {
      icon: Zap,
      title: __('Dynamic Variables'),
      description: __('Personalize messages with customer name, order details, product info, and more.'),
      color: 'wsms-text-amber-500',
      bgColor: 'wsms-bg-amber-500/10',
    },
    {
      icon: Filter,
      title: __('Advanced Conditions'),
      description: __('Combine multiple conditions with AND/OR logic for precise audience targeting.'),
      color: 'wsms-text-purple-500',
      bgColor: 'wsms-bg-purple-500/10',
    },
  ]

  const conditionTypes = [
    { icon: Package, label: __('Products'), description: __('Target specific product purchases') },
    { icon: Tag, label: __('Order Status'), description: __('Filter by order status changes') },
    { icon: Users, label: __('Coupon Used'), description: __('Target coupon code usage') },
    { icon: Filter, label: __('Product Type'), description: __('Simple, variable, grouped, etc.') },
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
                <Megaphone className="wsms-h-6 wsms-w-6 wsms-text-primary" />
              </div>
              <div>
                <h2 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-1">
                  {__('SMS Campaigns')}
                </h2>
                <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-max-w-lg">
                  {__('Create targeted SMS marketing campaigns based on customer behavior. Set conditions, schedule delivery, and track results to maximize engagement.')}
                </p>
              </div>
            </div>
            <div className="wsms-flex wsms-gap-2">
              <Button variant="outline" size="sm" asChild>
                <a href={campaignsListUrl} target="_blank" rel="noopener noreferrer">
                  <List className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                  {__('All Campaigns')}
                  <ExternalLink className="wsms-h-3.5 wsms-w-3.5 wsms-ml-2" />
                </a>
              </Button>
              <Button size="sm" asChild>
                <a href={newCampaignUrl} target="_blank" rel="noopener noreferrer">
                  <Plus className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                  {__('New Campaign')}
                  <ExternalLink className="wsms-h-3.5 wsms-w-3.5 wsms-ml-2" />
                </a>
              </Button>
            </div>
          </div>
        </div>
      </div>

      {/* Condition Types */}
      <div className="wsms-grid wsms-grid-cols-4 wsms-gap-4">
        {conditionTypes.map((condition) => {
          const Icon = condition.icon
          return (
            <Card key={condition.label}>
              <CardContent className="wsms-p-4">
                <div className="wsms-flex wsms-items-center wsms-gap-3">
                  <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-primary/10">
                    <Icon className="wsms-h-5 wsms-w-5 wsms-text-primary" />
                  </div>
                  <div>
                    <p className="wsms-text-[12px] wsms-font-medium wsms-text-foreground">{condition.label}</p>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground">{condition.description}</p>
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
            {__('Campaign Features')}
          </CardTitle>
          <CardDescription>
            {__('Powerful tools for creating effective SMS marketing campaigns')}
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

      {/* How to Create Campaign */}
      <Card>
        <CardHeader>
          <CardTitle>{__('Create a Campaign')}</CardTitle>
          <CardDescription>
            {__('Follow these steps to set up your first SMS campaign')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="wsms-space-y-4">
            <div className="wsms-flex wsms-items-start wsms-gap-4">
              <div className="wsms-flex wsms-h-8 wsms-w-8 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-primary wsms-text-primary-foreground wsms-text-sm wsms-font-semibold wsms-shrink-0">
                1
              </div>
              <div>
                <h4 className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">{__('Set Conditions')}</h4>
                <p className="wsms-text-[12px] wsms-text-muted-foreground">
                  {__('Define which orders should trigger the campaign using product, status, or coupon conditions.')}
                </p>
              </div>
            </div>
            <div className="wsms-flex wsms-items-start wsms-gap-4">
              <div className="wsms-flex wsms-h-8 wsms-w-8 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-primary wsms-text-primary-foreground wsms-text-sm wsms-font-semibold wsms-shrink-0">
                2
              </div>
              <div>
                <h4 className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">{__('Schedule Timing')}</h4>
                <p className="wsms-text-[12px] wsms-text-muted-foreground">
                  {__('Choose to send at a specific date/time or automatically after order placement.')}
                </p>
              </div>
            </div>
            <div className="wsms-flex wsms-items-start wsms-gap-4">
              <div className="wsms-flex wsms-h-8 wsms-w-8 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-primary wsms-text-primary-foreground wsms-text-sm wsms-font-semibold wsms-shrink-0">
                3
              </div>
              <div>
                <h4 className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">{__('Write Message')}</h4>
                <p className="wsms-text-[12px] wsms-text-muted-foreground">
                  {__('Compose your SMS using dynamic variables for personalization.')}
                </p>
              </div>
            </div>
            <div className="wsms-flex wsms-items-start wsms-gap-4">
              <div className="wsms-flex wsms-h-8 wsms-w-8 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-primary wsms-text-primary-foreground wsms-text-sm wsms-font-semibold wsms-shrink-0">
                4
              </div>
              <div>
                <h4 className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">{__('Publish & Monitor')}</h4>
                <p className="wsms-text-[12px] wsms-text-muted-foreground">
                  {__('Activate your campaign and track delivery status in the campaign preview.')}
                </p>
              </div>
            </div>
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
            <Button asChild className="wsms-flex-1">
              <a href={newCampaignUrl} target="_blank" rel="noopener noreferrer">
                <Plus className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                {__('Create New Campaign')}
                <ExternalLink className="wsms-h-3.5 wsms-w-3.5 wsms-ml-2" />
              </a>
            </Button>
            <Button variant="outline" asChild className="wsms-flex-1">
              <a href={campaignsListUrl} target="_blank" rel="noopener noreferrer">
                <List className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                {__('View All Campaigns')}
                <ExternalLink className="wsms-h-3.5 wsms-w-3.5 wsms-ml-2" />
              </a>
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
