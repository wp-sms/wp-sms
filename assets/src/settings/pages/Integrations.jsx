import React from 'react'
import { Puzzle, FileText, CheckCircle, AlertCircle, ExternalLink, XCircle } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useSetting } from '@/context/SettingsContext'
import { useAddonSettings, useAddonFieldsForSection } from '@/hooks/useAddonSettings'
import { AddonSection, AddonFieldsInjection } from '@/components/ui/AddonSection'
import { DynamicField } from '@/components/ui/DynamicField'
import { getWpSettings } from '@/lib/utils'

// Get plugin status from localized data
const getPluginStatus = (pluginKey) => {
  const wpSettings = getWpSettings()
  return wpSettings.thirdPartyPlugins?.[pluginKey] || { status: 'unknown', actionUrl: '' }
}

// Status badge component
const PluginStatusBadge = ({ status }) => {
  const variants = {
    active: { label: 'Active', className: 'wsms-bg-green-100 wsms-text-green-800 wsms-border-green-200' },
    inactive: { label: 'Inactive', className: 'wsms-bg-yellow-100 wsms-text-yellow-800 wsms-border-yellow-200' },
    not_installed: { label: 'Not Installed', className: 'wsms-bg-red-100 wsms-text-red-800 wsms-border-red-200' },
    unknown: { label: 'Unknown', className: 'wsms-bg-gray-100 wsms-text-gray-800 wsms-border-gray-200' },
  }

  const variant = variants[status] || variants.unknown

  return (
    <Badge variant="outline" className={`wsms-text-xs wsms-font-medium ${variant.className}`}>
      {variant.label}
    </Badge>
  )
}

// Action link component
const PluginActionLink = ({ status, actionUrl, isExternal, pluginName }) => {
  if (status === 'active' || !actionUrl) return null

  const label = status === 'inactive' ? 'Activate' : 'Install'
  const fullLabel = pluginName ? `${label} ${pluginName}` : label

  return (
    <Button
      variant="link"
      size="sm"
      className="wsms-h-auto wsms-p-0 wsms-text-xs"
      asChild
    >
      <a
        href={actionUrl}
        target={isExternal ? '_blank' : '_self'}
        rel={isExternal ? 'noopener noreferrer' : undefined}
      >
        {fullLabel}
        {isExternal && <ExternalLink className="wsms-ml-1 wsms-h-3 wsms-w-3" />}
      </a>
    </Button>
  )
}

export default function Integrations() {
  // Contact Form 7
  const [cf7Metabox, setCf7Metabox] = useSetting('cf7_metabox', '')

  // Get add-on settings for this page
  const { sections: addonSections, fieldsBySection, standaloneFields } = useAddonSettings('integrations')

  // Get add-on fields that should be injected into built-in sections
  const cf7AddonFields = useAddonFieldsForSection('integrations', 'contact-form-7')

  const integrations = [
    {
      id: 'contact-form-7',
      pluginKey: 'contact-form-7',
      name: 'Contact Form 7',
      description: 'Send SMS notifications when Contact Form 7 forms are submitted.',
      helpText: 'Adds an "SMS Notification" tab to the Contact Form 7 editor.',
      settingKey: 'cf7_metabox',
      value: cf7Metabox,
      setValue: setCf7Metabox,
    },
  ]

  // Get plugin status for each integration
  const integrationsWithStatus = integrations.map((integration) => ({
    ...integration,
    pluginStatus: getPluginStatus(integration.pluginKey),
  }))

  // Form plugins that are automatically supported (no settings needed)
  const supportedPlugins = [
    { name: 'Gravity Forms', pluginKey: 'gravity-forms', status: 'Automatic support via add-on' },
    { name: 'Formidable Forms', pluginKey: 'formidable', status: 'Automatic support via add-on' },
    { name: 'Forminator', pluginKey: 'forminator', status: 'Automatic support via add-on' },
    { name: 'WooCommerce', pluginKey: 'woocommerce', status: 'Available via WooCommerce add-on' },
    { name: 'Elementor Forms', pluginKey: 'elementor', status: 'Available via Elementor add-on' },
  ]

  // Add plugin status to supported plugins
  const supportedPluginsWithStatus = supportedPlugins.map((plugin) => ({
    ...plugin,
    pluginStatus: getPluginStatus(plugin.pluginKey),
  }))

  // Map add-on section IDs to required third-party plugins
  const sectionPluginRequirements = {
    'woocommerce': 'woocommerce',
    'woo': 'woocommerce',
    'gravity-forms': 'gravity-forms',
    'gravityforms': 'gravity-forms',
    'formidable': 'formidable',
    'forminator': 'forminator',
    'elementor': 'elementor',
    'contact-form-7': 'contact-form-7',
    'cf7': 'contact-form-7',
  }

  // Check if a section's required plugin is active
  const isSectionPluginActive = (sectionId) => {
    const lowerSectionId = sectionId.toLowerCase()

    // Check each requirement pattern
    for (const [pattern, pluginKey] of Object.entries(sectionPluginRequirements)) {
      if (lowerSectionId.includes(pattern)) {
        const status = getPluginStatus(pluginKey)
        return status.status === 'active'
      }
    }

    // If no matching pattern, assume it's available
    return true
  }

  // Get required plugin info for a section
  const getSectionPluginInfo = (sectionId) => {
    const lowerSectionId = sectionId.toLowerCase()

    for (const [pattern, pluginKey] of Object.entries(sectionPluginRequirements)) {
      if (lowerSectionId.includes(pattern)) {
        return getPluginStatus(pluginKey)
      }
    }

    return null
  }

  return (
    <div className="wsms-space-y-6">
      {/* Active Integrations */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Puzzle className="wsms-h-5 wsms-w-5" />
            Form Plugin Integration
          </CardTitle>
          <CardDescription>
            Configure SMS notifications for form submissions
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          {integrationsWithStatus.map((integration) => {
            const { pluginStatus } = integration
            const isDisabled = pluginStatus.status !== 'active'

            return (
              <div
                key={integration.id}
                className={`wsms-rounded-lg wsms-border wsms-p-4 ${isDisabled ? 'wsms-opacity-75 wsms-bg-muted/30' : ''}`}
              >
                <div className="wsms-flex wsms-items-start wsms-justify-between">
                  <div className="wsms-flex wsms-items-start wsms-gap-3">
                    <div className={`wsms-rounded-lg wsms-p-2 ${isDisabled ? 'wsms-bg-muted' : 'wsms-bg-primary/10'}`}>
                      <FileText className={`wsms-h-5 wsms-w-5 ${isDisabled ? 'wsms-text-muted-foreground' : 'wsms-text-primary'}`} />
                    </div>
                    <div>
                      <div className="wsms-flex wsms-items-center wsms-gap-2">
                        <h3 className="wsms-font-medium">{integration.name}</h3>
                        <PluginStatusBadge status={pluginStatus.status} />
                      </div>
                      <p className="wsms-mt-1 wsms-text-sm wsms-text-muted-foreground">
                        {integration.description}
                      </p>
                      {!isDisabled && integration.helpText && (
                        <p className="wsms-mt-2 wsms-text-xs wsms-text-muted-foreground">
                          {integration.helpText}
                        </p>
                      )}
                    </div>
                  </div>
                  <div className="wsms-flex wsms-items-center wsms-gap-3">
                    {isDisabled && (
                      <PluginActionLink
                        status={pluginStatus.status}
                        actionUrl={pluginStatus.actionUrl}
                        isExternal={pluginStatus.isExternal}
                        pluginName={integration.name}
                      />
                    )}
                    <Switch
                      checked={integration.value === '1'}
                      onCheckedChange={(checked) => integration.setValue(checked ? '1' : '')}
                      disabled={isDisabled}
                      aria-label={`Enable ${integration.name}`}
                    />
                  </div>
                </div>
                {/* Inject add-on fields for this integration */}
                {integration.id === 'contact-form-7' && cf7AddonFields.length > 0 && !isDisabled && (
                  <AddonFieldsInjection fields={cf7AddonFields} />
                )}
              </div>
            )
          })}
        </CardContent>
      </Card>

      {/* Add-on Defined Sections */}
      {addonSections.map((section) => {
        const isPluginActive = isSectionPluginActive(section.id)
        const pluginInfo = getSectionPluginInfo(section.id)

        if (!isPluginActive && pluginInfo) {
          // Show disabled version with install/activate prompt
          return (
            <Card key={section.id} className="wsms-opacity-75">
              <CardHeader>
                <div className="wsms-flex wsms-items-center wsms-justify-between">
                  <div>
                    <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                      {section.title}
                      <PluginStatusBadge status={pluginInfo.status} />
                    </CardTitle>
                    {section.description && (
                      <CardDescription>{section.description}</CardDescription>
                    )}
                  </div>
                </div>
              </CardHeader>
              <CardContent>
                <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-4 wsms-text-center">
                  <p className="wsms-text-sm wsms-text-muted-foreground wsms-mb-2">
                    {pluginInfo.name} is required to use these settings.
                  </p>
                  <PluginActionLink
                    status={pluginInfo.status}
                    actionUrl={pluginInfo.actionUrl}
                    isExternal={pluginInfo.isExternal}
                    pluginName={pluginInfo.name}
                  />
                </div>
              </CardContent>
            </Card>
          )
        }

        return (
          <AddonSection
            key={section.id}
            section={section}
            fields={fieldsBySection[section.id] || []}
          />
        )
      })}

      {/* Standalone Add-on Fields (fields without a section) */}
      {standaloneFields.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>Additional Add-on Settings</CardTitle>
          </CardHeader>
          <CardContent className="wsms-space-y-4">
            {standaloneFields.map((field) => (
              <DynamicField key={field.id} field={field} />
            ))}
          </CardContent>
        </Card>
      )}

      {/* Other Supported Plugins */}
      <Card>
        <CardHeader>
          <CardTitle>Additional Integrations</CardTitle>
          <CardDescription>
            Other plugins supported through WSMS add-ons
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="wsms-space-y-3">
            {supportedPluginsWithStatus.map((plugin) => {
              const { pluginStatus } = plugin
              const isAvailable = pluginStatus.status === 'active'

              return (
                <div
                  key={plugin.name}
                  className={`wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-3 ${!isAvailable ? 'wsms-bg-muted/30' : ''}`}
                >
                  <div className="wsms-flex wsms-items-center wsms-gap-3">
                    <FileText className={`wsms-h-4 wsms-w-4 ${isAvailable ? 'wsms-text-primary' : 'wsms-text-muted-foreground'}`} />
                    <span className="wsms-font-medium">{plugin.name}</span>
                    <PluginStatusBadge status={pluginStatus.status} />
                  </div>
                  <div className="wsms-flex wsms-items-center wsms-gap-4">
                    {!isAvailable && pluginStatus.actionUrl && (
                      <PluginActionLink
                        status={pluginStatus.status}
                        actionUrl={pluginStatus.actionUrl}
                        isExternal={pluginStatus.isExternal}
                        pluginName=""
                      />
                    )}
                    <span className="wsms-text-sm wsms-text-muted-foreground">{plugin.status}</span>
                  </div>
                </div>
              )
            })}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
