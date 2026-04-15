import { __ } from '@wordpress/i18n'
import React from 'react'
import * as Icons from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
import { SettingRow, SelectField, TextareaField } from '@/components/ui/form-field'
import { useSetting } from '@/context/SettingsContext'
import { useAddonSettings } from '@/hooks/useAddonSettings'
import { DynamicField } from '@/components/ui/DynamicField'

const { Webhook, Database, Bell, BarChart3, Megaphone } = Icons

function getIconComponent(iconName) {
  if (!iconName) return null
  if (Icons[iconName]) return Icons[iconName]
  const pascal = iconName.replace(/-([a-z])/g, (_, c) => c.toUpperCase())
  return Icons[pascal] || null
}

export default function Advanced() {
  // Get add-on settings for this page
  const { sections: addonSections, fieldsBySection, standaloneFields } = useAddonSettings('advanced')
  // Webhooks
  const [webhookOutgoing, setWebhookOutgoing, webhookOutgoingError] = useSetting('new_sms_webhook', '')
  const [webhookSubscriber, setWebhookSubscriber] = useSetting('new_subscriber_webhook', '')
  const [webhookIncoming, setWebhookIncoming] = useSetting('new_incoming_sms_webhook', '')

  // Data retention - Outbox
  const [storeOutbox, setStoreOutbox] = useSetting('store_outbox_messages', '1')
  const [outboxRetention, setOutboxRetention] = useSetting('outbox_retention_days', '90')

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
            <Webhook className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Webhooks', 'wp-sms')}
          </CardTitle>
          <CardDescription>
            {__('Integrate with external services via webhook notifications', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div data-setting-key="new_sms_webhook">
            <TextareaField
              label={__('Outgoing SMS Webhook', 'wp-sms')}
              error={webhookOutgoingError}
              value={webhookOutgoing}
              onChange={(e) => setWebhookOutgoing(e.target.value)}
              placeholder="https://your-app.com/webhooks/sms-sent"
              rows={2}
              description={__('Called after each SMS is sent. Enter one URL per line.', 'wp-sms')}
            />
          </div>

          <div className="wsms-space-y-2">
            <Label htmlFor="webhookSubscriber">{__('New Subscriber Webhook', 'wp-sms')}</Label>
            <Textarea
              id="webhookSubscriber"
              value={webhookSubscriber}
              onChange={(e) => setWebhookSubscriber(e.target.value)}
              placeholder="https://your-app.com/webhooks/new-subscriber"
              rows={2}
            />
            <p className="wsms-text-[12px] wsms-text-muted-foreground">
              {__('Called when someone subscribes to your SMS newsletter.', 'wp-sms')}
            </p>
          </div>

          <div className="wsms-space-y-2">
            <Label htmlFor="webhookIncoming">{__('Incoming SMS Webhook', 'wp-sms')}</Label>
            <Textarea
              id="webhookIncoming"
              value={webhookIncoming}
              onChange={(e) => setWebhookIncoming(e.target.value)}
              placeholder="https://your-app.com/webhooks/sms-received"
              rows={2}
            />
            <p className="wsms-text-[12px] wsms-text-muted-foreground">
              {__('Called when you receive an SMS reply. Requires Two-Way SMS add-on.', 'wp-sms')}
            </p>
          </div>
        </CardContent>
      </Card>

      {/* Data Retention */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Database className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Message Storage', 'wp-sms')}
          </CardTitle>
          <CardDescription>
            {__('Configure message logging and automatic cleanup', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SettingRow
            title={__('Log Sent Messages', 'wp-sms')}
            description={__('Save all sent SMS messages in the Outbox for tracking.', 'wp-sms')}
            checked={storeOutbox === '1'}
            onCheckedChange={(checked) => setStoreOutbox(checked ? '1' : '')}
          />

          {storeOutbox === '1' && (
            <SelectField
              label={__('Auto-delete Sent Messages', 'wp-sms')}
              value={outboxRetention}
              onValueChange={setOutboxRetention}
              placeholder={__('Select retention period', 'wp-sms')}
              description={__('Automatically remove old messages from the Outbox.', 'wp-sms')}
              options={[
                { value: '30', label: __('After 30 days', 'wp-sms') },
                { value: '90', label: __('After 90 days', 'wp-sms') },
                { value: '180', label: __('After 180 days', 'wp-sms') },
                { value: '365', label: __('After 365 days', 'wp-sms') },
                { value: '0', label: __('Keep forever', 'wp-sms') },
              ]}
            />
          )}
        </CardContent>
      </Card>

      {/* Administrative Reporting */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <BarChart3 className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Administrative Reporting', 'wp-sms')}
          </CardTitle>
          <CardDescription>
            {__('Configure email reports about SMS performance and errors', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SettingRow
            title={__('Weekly Statistics Email', 'wp-sms')}
            description={__('Receive weekly SMS usage reports via email.', 'wp-sms')}
            checked={reportStats === '1'}
            onCheckedChange={(checked) => setReportStats(checked ? '1' : '')}
          />

          <SettingRow
            title={__('Error Notifications', 'wp-sms')}
            description={__('Email admin when SMS sending fails.', 'wp-sms')}
            checked={notifyErrors === '1'}
            onCheckedChange={(checked) => setNotifyErrors(checked ? '1' : '')}
          />
        </CardContent>
      </Card>

      {/* Plugin Notifications */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Bell className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Plugin Notifications', 'wp-sms')}
          </CardTitle>
          <CardDescription>
            {__('Manage plugin update notices and announcements', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SettingRow
            title={__('WSMS Notifications', 'wp-sms')}
            description={__('Show update notices and announcements in the admin area.', 'wp-sms')}
            checked={displayNotifications === '1'}
            onCheckedChange={(checked) => setDisplayNotifications(checked ? '1' : '')}
          />
        </CardContent>
      </Card>

      {/* Anonymous Usage Data */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Megaphone className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Anonymous Usage Data', 'wp-sms')}
          </CardTitle>
          <CardDescription>
            {__('Help improve WSMS by sharing anonymous usage statistics', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SettingRow
            title={__('Share Anonymous Data', 'wp-sms')}
            description={__('Share non-personal, anonymized data to help improve WSMS.', 'wp-sms')}
            checked={shareAnonymousData === '1'}
            onCheckedChange={(checked) => setShareAnonymousData(checked ? '1' : '')}
          />
        </CardContent>
      </Card>

      {/* Add-on Defined Sections */}
      {addonSections.map((section) => {
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

      {/* Standalone Add-on Fields */}
      {standaloneFields.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle>{__('Additional Add-on Settings', 'wp-sms')}</CardTitle>
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
