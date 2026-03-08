import React from 'react'
import { AlertTriangle, ExternalLink } from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { getWpSettings, __ } from '@/lib/utils'

const ADDON_INFO = {
  pro: {
    name: 'WSMS Pro',
    pluginFile: 'wp-sms-pro',
  },
  woocommerce: {
    name: 'WSMS WooCommerce Pro',
    pluginFile: 'wp-sms-woocommerce-pro',
  },
  'two-way': {
    name: 'WSMS Two-Way',
    pluginFile: 'wp-sms-two-way',
  },
}

/**
 * Reusable card shown when an add-on is active but hasn't been updated
 * to support the new React dashboard.
 *
 * @param {Object} props
 * @param {string} props.addonKey - 'pro' | 'woocommerce' | 'two-way'
 * @param {import('lucide-react').LucideIcon} [props.icon] - Optional icon
 */
export function AddonUpdateRequired({ addonKey, icon: Icon }) {
  const info = ADDON_INFO[addonKey] || { name: addonKey, pluginFile: '' }
  const { adminUrl } = getWpSettings()
  const pluginsPageUrl = `${adminUrl}plugins.php?s=${encodeURIComponent(info.pluginFile)}`

  return (
    <div className="wsms-space-y-6">
      <Card className="wsms-border-amber-300 dark:wsms-border-amber-700">
        <CardContent className="wsms-py-16">
          <div className="wsms-flex wsms-flex-col wsms-items-center wsms-text-center wsms-max-w-md wsms-mx-auto">
            <div className="wsms-flex wsms-h-16 wsms-w-16 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-amber-100 dark:wsms-bg-amber-900/30 wsms-mb-6">
              {Icon ? (
                <Icon className="wsms-h-8 wsms-w-8 wsms-text-amber-600 dark:wsms-text-amber-400" strokeWidth={1.5} />
              ) : (
                <AlertTriangle className="wsms-h-8 wsms-w-8 wsms-text-amber-600 dark:wsms-text-amber-400" strokeWidth={1.5} />
              )}
            </div>
            <h3 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-2">
              {__('Update Required')}
            </h3>
            <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-6">
              {__('The %s add-on needs to be updated to work with the new dashboard. Please update it to the latest version.').replace('%s', info.name)}
            </p>
            <Button variant="outline" asChild>
              <a href={pluginsPageUrl}>
                {__('Go to Plugins')}
                <ExternalLink className="wsms-ms-2 wsms-h-4 wsms-w-4" />
              </a>
            </Button>
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
