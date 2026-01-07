import React from 'react'
import { MessageSquare, Link, Clock, FileText, CheckCircle } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Badge } from '@/components/ui/badge'
import { SettingRow } from '@/components/ui/form-field'
import { useSetting, useSettings } from '@/context/SettingsContext'

export default function Messaging() {
  const { isAddonActive } = useSettings()
  const hasPro = isAddonActive('pro')

  // Connect to the store_outbox_messages setting (also in Advanced)
  const [storeOutbox, setStoreOutbox] = useSetting('store_outbox_messages', '1')

  return (
    <div className="wsms-space-y-6 wsms-stagger-children">
      {/* URL Shortening - Informational (always enabled via filter) */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Link className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            URL Shortening
          </CardTitle>
          <CardDescription>
            Long URLs in your messages are automatically shortened to save characters
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="wsms-flex wsms-items-center wsms-gap-3 wsms-rounded-lg wsms-bg-success/10 wsms-p-4">
            <CheckCircle className="wsms-h-5 wsms-w-5 wsms-text-success wsms-shrink-0" />
            <div>
              <p className="wsms-font-medium wsms-text-success">Enabled</p>
              <p className="wsms-text-[12px] wsms-text-muted-foreground">
                URLs are automatically shortened using the wp_sms_shorturl filter. You can customize the shortening service via hooks.
              </p>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Message Templates */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <FileText className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            Message Templates
            {!hasPro && <Badge variant="warning">Pro</Badge>}
          </CardTitle>
          <CardDescription>
            Create reusable message templates for common notifications
          </CardDescription>
        </CardHeader>
        <CardContent>
          {hasPro ? (
            <div className="wsms-space-y-4">
              <p className="wsms-text-[12px] wsms-text-muted-foreground">
                Manage your message templates from the dedicated templates section.
              </p>
            </div>
          ) : (
            <div className="wsms-rounded-lg wsms-bg-muted wsms-p-4 wsms-text-center">
              <p className="wsms-text-[12px] wsms-text-muted-foreground">
                Message templates are available in the Pro version.
              </p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Scheduled Messages */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Clock className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            Scheduled Messages
            {!hasPro && <Badge variant="warning">Pro</Badge>}
          </CardTitle>
          <CardDescription>
            Schedule messages to be sent at a specific time
          </CardDescription>
        </CardHeader>
        <CardContent>
          {hasPro ? (
            <div className="wsms-space-y-4">
              <p className="wsms-text-[12px] wsms-text-muted-foreground">
                Scheduled messages can be configured when composing SMS in the Send SMS page.
              </p>
            </div>
          ) : (
            <div className="wsms-rounded-lg wsms-bg-muted wsms-p-4 wsms-text-center">
              <p className="wsms-text-[12px] wsms-text-muted-foreground">
                Scheduled messages are available in the Pro version.
              </p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Message Settings */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <MessageSquare className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            Message Settings
          </CardTitle>
          <CardDescription>
            General messaging configuration options
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SettingRow
            title="Store Sent Messages"
            description="Keep a record of all sent messages in the outbox for tracking and resending"
            checked={storeOutbox === '1'}
            onCheckedChange={(checked) => setStoreOutbox(checked ? '1' : '')}
          />
        </CardContent>
      </Card>
    </div>
  )
}
