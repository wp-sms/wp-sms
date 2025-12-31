import React from 'react'
import { Puzzle, FileText, CheckCircle } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Switch } from '@/components/ui/switch'
import { useSetting } from '@/context/SettingsContext'

export default function Integrations() {
  // Contact Form 7
  const [cf7Metabox, setCf7Metabox] = useSetting('cf7_metabox', '')

  const integrations = [
    {
      id: 'contact-form-7',
      name: 'Contact Form 7',
      description: 'Add SMS notification options to Contact Form 7 forms',
      helpText: 'When enabled, an SMS notification tab will appear in the Contact Form 7 editor allowing you to send SMS when forms are submitted.',
      settingKey: 'cf7_metabox',
      value: cf7Metabox,
      setValue: setCf7Metabox,
    },
  ]

  // Form plugins that are automatically supported (no settings needed)
  const supportedPlugins = [
    { name: 'Gravity Forms', status: 'Automatic support via add-on' },
    { name: 'Formidable Forms', status: 'Automatic support via add-on' },
    { name: 'Forminator', status: 'Automatic support via add-on' },
    { name: 'WooCommerce', status: 'Available via WooCommerce add-on' },
    { name: 'Elementor Forms', status: 'Available via Elementor add-on' },
  ]

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
          {integrations.map((integration) => (
            <div
              key={integration.id}
              className="wsms-rounded-lg wsms-border wsms-p-4"
            >
              <div className="wsms-flex wsms-items-start wsms-justify-between">
                <div className="wsms-flex wsms-items-start wsms-gap-3">
                  <div className="wsms-rounded-lg wsms-bg-primary/10 wsms-p-2">
                    <FileText className="wsms-h-5 wsms-w-5 wsms-text-primary" />
                  </div>
                  <div>
                    <h3 className="wsms-font-medium">{integration.name}</h3>
                    <p className="wsms-mt-1 wsms-text-sm wsms-text-muted-foreground">
                      {integration.description}
                    </p>
                    {integration.helpText && (
                      <p className="wsms-mt-2 wsms-text-xs wsms-text-muted-foreground">
                        {integration.helpText}
                      </p>
                    )}
                  </div>
                </div>
                <Switch
                  checked={integration.value === '1'}
                  onCheckedChange={(checked) => integration.setValue(checked ? '1' : '')}
                />
              </div>
            </div>
          ))}
        </CardContent>
      </Card>

      {/* Other Supported Plugins */}
      <Card>
        <CardHeader>
          <CardTitle>Additional Integrations</CardTitle>
          <CardDescription>
            Other plugins supported through WP SMS add-ons
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="wsms-space-y-3">
            {supportedPlugins.map((plugin) => (
              <div
                key={plugin.name}
                className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-3"
              >
                <div className="wsms-flex wsms-items-center wsms-gap-3">
                  <FileText className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                  <span className="wsms-font-medium">{plugin.name}</span>
                </div>
                <span className="wsms-text-sm wsms-text-muted-foreground">{plugin.status}</span>
              </div>
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
