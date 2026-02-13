import React from 'react'
import * as Icons from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Badge } from '@/components/ui/badge'
import { useSettings } from '@/context/SettingsContext'
import { useAddonSettings } from '@/hooks/useAddonSettings'
import { DynamicField } from '@/components/ui/DynamicField'
import { __ } from '@/lib/utils'

const { Shield, Diamond } = Icons

function getIconComponent(iconName) {
  if (!iconName) return null
  if (Icons[iconName]) return Icons[iconName]
  const pascal = iconName.replace(/-([a-z])/g, (_, c) => c.toUpperCase())
  return Icons[pascal] || null
}

export default function Authentication() {
  const { isAddonActive } = useSettings()
  const hasPro = isAddonActive('pro')
  const hasOtp = isAddonActive('otp')

  // Get add-on settings for this page
  const { sections: addonSections, fieldsBySection, standaloneFields, hasAddonContent } = useAddonSettings('authentication')

  // Sort sections by priority
  const sortedSections = [...addonSections].sort((a, b) => (a.priority || 0) - (b.priority || 0))

  if (!hasPro && !hasOtp) {
    return (
      <div className="wsms-space-y-6">
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Shield className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              Authentication & OTP
              <Badge variant="warning">
                <Diamond className="wsms-me-1 wsms-h-3 wsms-w-3" />
                Add-on Required
              </Badge>
            </CardTitle>
            <CardDescription>
              Secure your users with SMS-based authentication
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-rounded-lg wsms-bg-muted wsms-p-8 wsms-text-center">
              <Shield className="wsms-mx-auto wsms-h-12 wsms-w-12 wsms-text-muted-foreground" />
              <h3 className="wsms-mt-4 wsms-text-lg wsms-font-semibold">
                Pro or OTP Add-on Required
              </h3>
              <p className="wsms-mt-2 wsms-text-sm wsms-text-muted-foreground">
                SMS authentication features require the Pro pack or OTP-MFA add-on.
                Protect user accounts with one-time passwords and two-factor authentication.
              </p>
              <div className="wsms-mt-6 wsms-flex wsms-justify-center wsms-gap-4">
                <Button>
                  Get Pro Pack
                </Button>
                <Button variant="outline">
                  Learn More
                </Button>
              </div>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  if (!hasAddonContent) {
    return (
      <div className="wsms-space-y-6">
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Shield className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              Authentication
            </CardTitle>
            <CardDescription>
              SMS-based login and two-factor authentication settings
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-6 wsms-text-center">
              <p className="wsms-text-[12px] wsms-text-muted-foreground">
                No settings available. Please ensure your add-on is up to date.
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="wsms-space-y-6">
      {sortedSections.map((section) => {
        const IconComponent = getIconComponent(section.icon) || Icons.Puzzle
        const fields = [...(fieldsBySection[section.id] || [])].sort(
          (a, b) => (a.target?.priority || 100) - (b.target?.priority || 100)
        )
        if (fields.length === 0) return null
        return (
          <Card key={section.id}>
            <CardHeader>
              <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                <IconComponent className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                {section.title}
                {section.addonSlug === 'wp-sms-pro' && (
                  <span className="wsms-ms-1 wsms-px-2 wsms-py-0.5 wsms-rounded-full wsms-bg-primary/10 wsms-text-primary wsms-text-[10px] wsms-font-medium">
                    {__('PRO')}
                  </span>
                )}
              </CardTitle>
              {section.description && (
                <CardDescription>{section.description}</CardDescription>
              )}
            </CardHeader>
            <CardContent className="wsms-space-y-4">
              {fields.map((field) => (
                <DynamicField key={field.id} field={field} />
              ))}
            </CardContent>
          </Card>
        )
      })}

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
