import React from 'react'
import { ShoppingCart, AlertCircle, ExternalLink } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { useSettings } from '@/context/SettingsContext'
import { useAddonSettings } from '@/hooks/useAddonSettings'
import { AddonSection } from '@/components/ui/AddonSection'
import { DynamicField } from '@/components/ui/DynamicField'
import { getWpSettings } from '@/lib/utils'

export default function WooCommercePro() {
  const { isAddonActive } = useSettings()
  const wpSettings = getWpSettings()

  // Check if WooCommerce Pro add-on is active (key is 'woocommerce' in getActiveAddons())
  const hasWooCommercePro = isAddonActive('woocommerce')

  // Check if WooCommerce plugin is active
  const wooCommerceStatus = wpSettings.thirdPartyPlugins?.['woocommerce'] || { status: 'unknown' }
  const isWooCommerceActive = wooCommerceStatus.status === 'active'

  // Get add-on settings for this page
  const { sections: addonSections, fieldsBySection, standaloneFields, hasAddonContent } = useAddonSettings('woocommerce-pro')

  // Sort sections by priority
  const sortedSections = [...addonSections].sort((a, b) => (a.priority || 0) - (b.priority || 0))

  // Show placeholder if WooCommerce Pro add-on is not active
  if (!hasWooCommercePro) {
    return (
      <div className="wsms-space-y-6">
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <ShoppingCart className="wsms-h-5 wsms-w-5" />
              WooCommerce Pro
            </CardTitle>
            <CardDescription>
              Advanced WooCommerce SMS notifications and automation
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-6 wsms-text-center">
              <AlertCircle className="wsms-mx-auto wsms-h-10 wsms-w-10 wsms-text-muted-foreground wsms-mb-3" />
              <h3 className="wsms-font-medium wsms-mb-2">WooCommerce Pro Add-on Required</h3>
              <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mb-4">
                Install and activate the WP SMS WooCommerce Pro add-on to access these features.
              </p>
              <Button variant="outline" asChild>
                <a
                  href="https://wp-sms-pro.com/product/wp-sms-woocommerce-pro/"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Learn More
                  <ExternalLink className="wsms-ml-2 wsms-h-4 wsms-w-4" />
                </a>
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  // Show placeholder if WooCommerce is not active
  if (!isWooCommerceActive) {
    return (
      <div className="wsms-space-y-6">
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <ShoppingCart className="wsms-h-5 wsms-w-5" />
              WooCommerce Pro
            </CardTitle>
            <CardDescription>
              Advanced WooCommerce SMS notifications and automation
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-6 wsms-text-center">
              <AlertCircle className="wsms-mx-auto wsms-h-10 wsms-w-10 wsms-text-muted-foreground wsms-mb-3" />
              <h3 className="wsms-font-medium wsms-mb-2">WooCommerce Required</h3>
              <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mb-4">
                WooCommerce must be installed and activated to use these features.
              </p>
              {wooCommerceStatus.actionUrl && (
                <Button variant="outline" asChild>
                  <a href={wooCommerceStatus.actionUrl}>
                    {wooCommerceStatus.status === 'inactive' ? 'Activate WooCommerce' : 'Install WooCommerce'}
                  </a>
                </Button>
              )}
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  // Show message if no content is available
  if (!hasAddonContent) {
    return (
      <div className="wsms-space-y-6">
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <ShoppingCart className="wsms-h-5 wsms-w-5" />
              WooCommerce Pro
            </CardTitle>
            <CardDescription>
              Advanced WooCommerce SMS notifications and automation
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-6 wsms-text-center">
              <p className="wsms-text-[12px] wsms-text-muted-foreground">
                No settings available. Please ensure the WooCommerce Pro add-on is properly configured.
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="wsms-space-y-6">
      {/* Page Header */}
      <div className="wsms-mb-6">
        <h1 className="wsms-text-2xl wsms-font-bold wsms-flex wsms-items-center wsms-gap-2">
          <ShoppingCart className="wsms-h-6 wsms-w-6" />
          WooCommerce Pro
        </h1>
        <p className="wsms-text-muted-foreground wsms-mt-1">
          Configure advanced WooCommerce SMS notifications and automation features.
        </p>
      </div>

      {/* Add-on Defined Sections */}
      {sortedSections.map((section) => (
        <AddonSection
          key={section.id}
          section={section}
          fields={fieldsBySection[section.id] || []}
        />
      ))}

      {/* Standalone Add-on Fields (fields without a section) */}
      {standaloneFields.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>Additional Settings</CardTitle>
          </CardHeader>
          <CardContent className="wsms-space-y-4">
            {standaloneFields.map((field) => (
              <DynamicField key={field.id} field={field} />
            ))}
          </CardContent>
        </Card>
      )}
    </div>
  )
}
