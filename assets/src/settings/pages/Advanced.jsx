import React from 'react'
import { Settings, Webhook, Database, Bell } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { SettingRow, SelectField } from '@/components/ui/form-field'
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
            <p className="wsms-text-[12px] wsms-text-muted-foreground">
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
            <p className="wsms-text-[12px] wsms-text-muted-foreground">
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
            <p className="wsms-text-[12px] wsms-text-muted-foreground">
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
          <SettingRow
            title={__('Log Sent Messages')}
            description={__('Save all sent SMS messages in the Outbox for tracking.')}
            checked={storeOutbox === '1'}
            onCheckedChange={(checked) => setStoreOutbox(checked ? '1' : '')}
          />

          {storeOutbox === '1' && (
            <SelectField
              label={__('Auto-delete Sent Messages')}
              value={outboxRetention}
              onValueChange={setOutboxRetention}
              placeholder={__('Select retention period')}
              description={__('Automatically remove old messages from the Outbox.')}
              options={[
                { value: '30', label: __('After 30 days') },
                { value: '90', label: __('After 90 days') },
                { value: '180', label: __('After 180 days') },
                { value: '365', label: __('After 365 days') },
                { value: '0', label: __('Keep forever') },
              ]}
            />
          )}

          <div className="wsms-border-t wsms-border-border wsms-pt-4 wsms-mt-4">
            <SettingRow
              title={__('Log Received Messages')}
              description={__('Save incoming SMS messages in the Inbox.')}
              checked={storeInbox === '1'}
              onCheckedChange={(checked) => setStoreInbox(checked ? '1' : '')}
            />

            {storeInbox === '1' && (
              <SelectField
                label={__('Auto-delete Received Messages')}
                value={inboxRetention}
                onValueChange={setInboxRetention}
                placeholder={__('Select retention period')}
                description={__('Automatically remove old messages from the Inbox.')}
                className="wsms-mt-4"
                options={[
                  { value: '30', label: __('After 30 days') },
                  { value: '90', label: __('After 90 days') },
                  { value: '180', label: __('After 180 days') },
                  { value: '365', label: __('After 365 days') },
                  { value: '0', label: __('Keep forever') },
                ]}
              />
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
          <SettingRow
            title={__('Weekly Statistics Email')}
            description={__('Receive weekly SMS usage reports via email.')}
            checked={reportStats === '1'}
            onCheckedChange={(checked) => setReportStats(checked ? '1' : '')}
          />

          <SettingRow
            title={__('Error Notifications')}
            description={__('Email admin when SMS sending fails.')}
            checked={notifyErrors === '1'}
            onCheckedChange={(checked) => setNotifyErrors(checked ? '1' : '')}
          />

          <SettingRow
            title={__('Plugin Notifications')}
            description={__('Show update notices and announcements in the admin area.')}
            checked={displayNotifications === '1'}
            onCheckedChange={(checked) => setDisplayNotifications(checked ? '1' : '')}
          />

          <SettingRow
            title={__('Usage Analytics')}
            description={__('Share anonymous usage data to help improve WSMS.')}
            checked={shareAnonymousData === '1'}
            onCheckedChange={(checked) => setShareAnonymousData(checked ? '1' : '')}
          />
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
