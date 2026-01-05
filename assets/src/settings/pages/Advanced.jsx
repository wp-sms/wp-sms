import React from 'react'
import { Settings, Webhook, Database, Bell } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { useSetting } from '@/context/SettingsContext'
import { useAddonSettings } from '@/hooks/useAddonSettings'
import { AddonSection } from '@/components/ui/AddonSection'
import { DynamicField } from '@/components/ui/DynamicField'
import { __ } from '@/lib/utils'

export default function Advanced() {
  // Get add-on settings for this page
  const { sections: addonSections, fieldsBySection, standaloneFields } = useAddonSettings('advanced')
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
            {__('Webhooks')}
          </CardTitle>
          <CardDescription>
            {__('Integrate with external services via webhook notifications')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-space-y-2">
            <Label htmlFor="webhookOutgoing">{__('Outgoing SMS Webhook')}</Label>
            <Textarea
              id="webhookOutgoing"
              value={webhookOutgoing}
              onChange={(e) => setWebhookOutgoing(e.target.value)}
              placeholder="https://your-app.com/webhooks/sms-sent"
              rows={2}
            />
            <p className="wsms-text-xs wsms-text-muted-foreground">
              {__('Called after each SMS is sent. Enter one URL per line.')}
            </p>
          </div>

          <div className="wsms-space-y-2">
            <Label htmlFor="webhookSubscriber">{__('New Subscriber Webhook')}</Label>
            <Textarea
              id="webhookSubscriber"
              value={webhookSubscriber}
              onChange={(e) => setWebhookSubscriber(e.target.value)}
              placeholder="https://your-app.com/webhooks/new-subscriber"
              rows={2}
            />
            <p className="wsms-text-xs wsms-text-muted-foreground">
              {__('Called when someone subscribes to your SMS newsletter.')}
            </p>
          </div>

          <div className="wsms-space-y-2">
            <Label htmlFor="webhookIncoming">{__('Incoming SMS Webhook')}</Label>
            <Textarea
              id="webhookIncoming"
              value={webhookIncoming}
              onChange={(e) => setWebhookIncoming(e.target.value)}
              placeholder="https://your-app.com/webhooks/sms-received"
              rows={2}
            />
            <p className="wsms-text-xs wsms-text-muted-foreground">
              {__('Called when you receive an SMS reply. Requires Two-Way SMS add-on.')}
            </p>
          </div>
        </CardContent>
      </Card>

      {/* Data Retention */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Database className="wsms-h-5 wsms-w-5" />
            {__('Message Storage')}
          </CardTitle>
          <CardDescription>
            {__('Configure message logging and automatic cleanup')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">{__('Log Sent Messages')}</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                {__('Save all sent SMS messages in the Outbox for tracking.')}
              </p>
            </div>
            <Switch
              checked={storeOutbox === '1'}
              onCheckedChange={(checked) => setStoreOutbox(checked ? '1' : '')}
              aria-label={__('Log sent messages')}
            />
          </div>

          {storeOutbox === '1' && (
            <div className="wsms-space-y-2">
              <Label>{__('Auto-delete Sent Messages')}</Label>
              <Select value={outboxRetention} onValueChange={setOutboxRetention}>
                <SelectTrigger aria-label={__('Outbox retention period')}>
                  <SelectValue placeholder={__('Select retention period')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="30">{__('After 30 days')}</SelectItem>
                  <SelectItem value="90">{__('After 90 days')}</SelectItem>
                  <SelectItem value="180">{__('After 180 days')}</SelectItem>
                  <SelectItem value="365">{__('After 365 days')}</SelectItem>
                  <SelectItem value="0">{__('Keep forever')}</SelectItem>
                </SelectContent>
              </Select>
              <p className="wsms-text-xs wsms-text-muted-foreground">
                {__('Automatically remove old messages from the Outbox.')}
              </p>
            </div>
          )}

          <div className="wsms-border-t wsms-border-border wsms-pt-4 wsms-mt-4">
            <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
              <div>
                <p className="wsms-font-medium">{__('Log Received Messages')}</p>
                <p className="wsms-text-sm wsms-text-muted-foreground">
                  {__('Save incoming SMS messages in the Inbox.')}
                </p>
              </div>
              <Switch
                checked={storeInbox === '1'}
                onCheckedChange={(checked) => setStoreInbox(checked ? '1' : '')}
                aria-label={__('Log received messages')}
              />
            </div>

            {storeInbox === '1' && (
              <div className="wsms-space-y-2 wsms-mt-4">
                <Label>{__('Auto-delete Received Messages')}</Label>
                <Select value={inboxRetention} onValueChange={setInboxRetention}>
                  <SelectTrigger aria-label={__('Inbox retention period')}>
                    <SelectValue placeholder={__('Select retention period')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="30">{__('After 30 days')}</SelectItem>
                    <SelectItem value="90">{__('After 90 days')}</SelectItem>
                    <SelectItem value="180">{__('After 180 days')}</SelectItem>
                    <SelectItem value="365">{__('After 365 days')}</SelectItem>
                    <SelectItem value="0">{__('Keep forever')}</SelectItem>
                  </SelectContent>
                </Select>
                <p className="wsms-text-xs wsms-text-muted-foreground">
                  {__('Automatically remove old messages from the Inbox.')}
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
            {__('Admin Notifications')}
          </CardTitle>
          <CardDescription>
            {__('Configure email reports and plugin notifications')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">{__('Weekly Statistics Email')}</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                {__('Receive weekly SMS usage reports via email.')}
              </p>
            </div>
            <Switch
              checked={reportStats === '1'}
              onCheckedChange={(checked) => setReportStats(checked ? '1' : '')}
              aria-label={__('Enable weekly statistics email')}
            />
          </div>

          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">{__('Error Notifications')}</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                {__('Email admin when SMS sending fails.')}
              </p>
            </div>
            <Switch
              checked={notifyErrors === '1'}
              onCheckedChange={(checked) => setNotifyErrors(checked ? '1' : '')}
              aria-label={__('Enable error notifications')}
            />
          </div>

          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">{__('Plugin Notifications')}</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                {__('Show update notices and announcements in the admin area.')}
              </p>
            </div>
            <Switch
              checked={displayNotifications === '1'}
              onCheckedChange={(checked) => setDisplayNotifications(checked ? '1' : '')}
              aria-label={__('Enable plugin notifications')}
            />
          </div>

          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">{__('Usage Analytics')}</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                {__('Share anonymous usage data to help improve WSMS.')}
              </p>
            </div>
            <Switch
              checked={shareAnonymousData === '1'}
              onCheckedChange={(checked) => setShareAnonymousData(checked ? '1' : '')}
              aria-label={__('Enable usage analytics')}
            />
          </div>
        </CardContent>
      </Card>

      {/* Add-on Defined Sections */}
      {addonSections.map((section) => (
        <AddonSection
          key={section.id}
          section={section}
          fields={fieldsBySection[section.id] || []}
        />
      ))}

      {/* Standalone Add-on Fields */}
      {standaloneFields.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>{__('Additional Add-on Settings')}</CardTitle>
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
