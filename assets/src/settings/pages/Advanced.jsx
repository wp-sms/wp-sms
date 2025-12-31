import React from 'react'
import { Settings, Webhook, Database, Bell } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { useSetting } from '@/context/SettingsContext'

export default function Advanced() {
  // Webhooks
  const [webhookOutgoing, setWebhookOutgoing] = useSetting('new_sms_webhook', '')
  const [webhookSubscriber, setWebhookSubscriber] = useSetting('new_subscriber_webhook', '')
  const [webhookIncoming, setWebhookIncoming] = useSetting('new_incoming_sms_webhook', '')

  // Data retention - Outbox
  const [storeOutbox, setStoreOutbox] = useSetting('store_outbox_messages', '1')
  const [outboxRetention, setOutboxRetention] = useSetting('outbox_retention_days', '90')
  // Data retention - Inbox
  const [storeInbox, setStoreInbox] = useSetting('store_inbox_messages', '1')
  const [inboxRetention, setInboxRetention] = useSetting('inbox_retention_days', '90')

  // Reporting
  const [reportStats, setReportStats] = useSetting('report_wpsms_statistics', '')
  const [notifyErrors, setNotifyErrors] = useSetting('notify_errors_to_admin_email', '1')
  const [displayNotifications, setDisplayNotifications] = useSetting('display_notifications', '1')
  const [shareAnonymousData, setShareAnonymousData] = useSetting('share_anonymous_data', '')

  return (
    <div className="wsms-space-y-6">
      {/* Webhooks */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Webhook className="wsms-h-5 wsms-w-5" />
            Webhooks Configuration
          </CardTitle>
          <CardDescription>
            Set up Webhook URLs to integrate with external services
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-space-y-2">
            <Label htmlFor="webhookOutgoing">Outgoing SMS Webhook</Label>
            <Textarea
              id="webhookOutgoing"
              value={webhookOutgoing}
              onChange={(e) => setWebhookOutgoing(e.target.value)}
              placeholder="https://your-webhook-url.com/outgoing"
              rows={2}
            />
            <p className="wsms-text-xs wsms-text-muted-foreground">
              Configure the Webhook URL for notifications after an SMS dispatch. Enter each URL on a separate line.
            </p>
          </div>

          <div className="wsms-space-y-2">
            <Label htmlFor="webhookSubscriber">Subscriber Registration Webhook</Label>
            <Textarea
              id="webhookSubscriber"
              value={webhookSubscriber}
              onChange={(e) => setWebhookSubscriber(e.target.value)}
              placeholder="https://your-webhook-url.com/subscriber"
              rows={2}
            />
            <p className="wsms-text-xs wsms-text-muted-foreground">
              Provide the Webhook URL triggered when a new subscriber registers.
            </p>
          </div>

          <div className="wsms-space-y-2">
            <Label htmlFor="webhookIncoming">Incoming SMS Webhook</Label>
            <Textarea
              id="webhookIncoming"
              value={webhookIncoming}
              onChange={(e) => setWebhookIncoming(e.target.value)}
              placeholder="https://your-webhook-url.com/incoming"
              rows={2}
            />
            <p className="wsms-text-xs wsms-text-muted-foreground">
              Define the Webhook URL for handling incoming SMS messages. Requires the Two-Way SMS add-on.
            </p>
          </div>
        </CardContent>
      </Card>

      {/* Data Retention */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Database className="wsms-h-5 wsms-w-5" />
            Message Storage & Cleanup
          </CardTitle>
          <CardDescription>
            Configure how long to keep message data
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">Store Outbox Messages</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                If disabled, new SMS will not be logged in the Outbox
              </p>
            </div>
            <Switch
              checked={storeOutbox === '1'}
              onCheckedChange={(checked) => setStoreOutbox(checked ? '1' : '')}
            />
          </div>

          {storeOutbox === '1' && (
            <div className="wsms-space-y-2">
              <Label>Delete Outbox Messages Older Than</Label>
              <Select value={outboxRetention} onValueChange={setOutboxRetention}>
                <SelectTrigger>
                  <SelectValue placeholder="Select retention period" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="30">30 days</SelectItem>
                  <SelectItem value="90">90 days</SelectItem>
                  <SelectItem value="180">180 days</SelectItem>
                  <SelectItem value="365">365 days</SelectItem>
                  <SelectItem value="0">Keep forever</SelectItem>
                </SelectContent>
              </Select>
              <p className="wsms-text-xs wsms-text-muted-foreground">
                Runs daily at 00:00 (site time). Choose how long to retain Outbox messages.
              </p>
            </div>
          )}

          <div className="wsms-border-t wsms-border-border wsms-pt-4 wsms-mt-4">
            <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
              <div>
                <p className="wsms-font-medium">Store Inbox Messages</p>
                <p className="wsms-text-sm wsms-text-muted-foreground">
                  If disabled, incoming SMS will not be logged in the Inbox
                </p>
              </div>
              <Switch
                checked={storeInbox === '1'}
                onCheckedChange={(checked) => setStoreInbox(checked ? '1' : '')}
              />
            </div>

            {storeInbox === '1' && (
              <div className="wsms-space-y-2 wsms-mt-4">
                <Label>Delete Inbox Messages Older Than</Label>
                <Select value={inboxRetention} onValueChange={setInboxRetention}>
                  <SelectTrigger>
                    <SelectValue placeholder="Select retention period" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="30">30 days</SelectItem>
                    <SelectItem value="90">90 days</SelectItem>
                    <SelectItem value="180">180 days</SelectItem>
                    <SelectItem value="365">365 days</SelectItem>
                    <SelectItem value="0">Keep forever</SelectItem>
                  </SelectContent>
                </Select>
                <p className="wsms-text-xs wsms-text-muted-foreground">
                  Runs daily at 00:00 (site time). Choose how long to retain Inbox messages.
                </p>
              </div>
            )}
          </div>
        </CardContent>
      </Card>

      {/* Notifications & Reporting */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Bell className="wsms-h-5 wsms-w-5" />
            Administrative Reporting
          </CardTitle>
          <CardDescription>
            Configure plugin notifications and reporting options
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">SMS Performance Reports</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                Sends weekly SMS performance statistics to the admin email
              </p>
            </div>
            <Switch
              checked={reportStats === '1'}
              onCheckedChange={(checked) => setReportStats(checked ? '1' : '')}
            />
          </div>

          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">SMS Transmission Error Alerts</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                Notifies the admin email upon SMS transmission failures
              </p>
            </div>
            <Switch
              checked={notifyErrors === '1'}
              onCheckedChange={(checked) => setNotifyErrors(checked ? '1' : '')}
            />
          </div>

          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">WP SMS Notifications</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                Display important notifications about new versions, feature updates, and special offers
              </p>
            </div>
            <Switch
              checked={displayNotifications === '1'}
              onCheckedChange={(checked) => setDisplayNotifications(checked ? '1' : '')}
            />
          </div>

          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">Share Anonymous Data</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                Send non-personal, anonymized data to help improve WP SMS
              </p>
            </div>
            <Switch
              checked={shareAnonymousData === '1'}
              onCheckedChange={(checked) => setShareAnonymousData(checked ? '1' : '')}
            />
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
