import React from 'react'
import { MessageSquare, Link, Clock, FileText } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Badge } from '@/components/ui/badge'
import { useSetting, useSettings } from '@/context/SettingsContext'

export default function Messaging() {
  const { isAddonActive } = useSettings()
  const hasPro = isAddonActive('pro')

  return (
    <div className="wsms-space-y-6">
      {/* URL Shortening */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Link className="wsms-h-5 wsms-w-5" />
            URL Shortening
          </CardTitle>
          <CardDescription>
            Automatically shorten URLs in your messages to save characters
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">Enable URL Shortening</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                Automatically shorten long URLs in SMS messages
              </p>
            </div>
            <Switch />
          </div>
        </CardContent>
      </Card>

      {/* Message Templates */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <FileText className="wsms-h-5 wsms-w-5" />
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
              <p className="wsms-text-sm wsms-text-muted-foreground">
                Manage your message templates from the dedicated templates section.
              </p>
            </div>
          ) : (
            <div className="wsms-rounded-lg wsms-bg-muted wsms-p-4 wsms-text-center">
              <p className="wsms-text-sm wsms-text-muted-foreground">
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
            <Clock className="wsms-h-5 wsms-w-5" />
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
              <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
                <div>
                  <p className="wsms-font-medium">Enable Message Scheduling</p>
                  <p className="wsms-text-sm wsms-text-muted-foreground">
                    Allow scheduling messages for future delivery
                  </p>
                </div>
                <Switch />
              </div>
            </div>
          ) : (
            <div className="wsms-rounded-lg wsms-bg-muted wsms-p-4 wsms-text-center">
              <p className="wsms-text-sm wsms-text-muted-foreground">
                Scheduled messages are available in the Pro version.
              </p>
            </div>
          )}
        </CardContent>
      </Card>

      {/* Character Counter */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <MessageSquare className="wsms-h-5 wsms-w-5" />
            Message Settings
          </CardTitle>
          <CardDescription>
            General messaging configuration options
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">Show Character Counter</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                Display character count and SMS segment count
              </p>
            </div>
            <Switch defaultChecked />
          </div>

          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">Store Sent Messages</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                Keep a record of all sent messages in the outbox
              </p>
            </div>
            <Switch defaultChecked />
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
