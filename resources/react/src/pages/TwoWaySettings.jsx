import { __, sprintf } from '@wordpress/i18n'
import React, { useRef, useState } from 'react'
import {
  Settings,
  AlertCircle,
  ExternalLink,
  Copy,
  Check,
  RefreshCw,
  Loader2,
  CheckCircle,
  XCircle,
  Bell,
  Database,
  Link2,
  ArrowRight,
} from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { TemplateTextarea } from '@/components/shared/TemplateTextarea'
import { AddonUpdateRequired } from '@/components/shared/AddonUpdateRequired'
import { SettingRow, SelectField } from '@/components/ui/form-field'
import { Tip, HelpLink } from '@/components/ui/ux-helpers'
import { useSettings } from '@/context/SettingsContext'
import { getWpSettings, buildRestUrl, cn, isAddonDashboardReady } from '@/lib/utils'
import { useToast } from '@/components/ui/toaster'

export default function TwoWaySettings() {
  const { isAddonActive, getAddonSetting, updateAddonSetting } = useSettings()
  const { toast } = useToast()
  const wpSettings = getWpSettings()

  // Check if Two-Way add-on is active
  const hasTwoWay = isAddonActive('two-way')

  // Get webhook/gateway data from schema's getDynamicData
  const addonData = wpSettings.addonSettings?.['two-way']?.data || {}
  const webhookUrl = addonData.webhookUrl || ''
  const webhookUrlPath = addonData.webhookUrlPath || ''
  const webhookSupported = addonData.webhookSupported || false
  const currentGateway = addonData.currentGateway || ''
  const registerType = addonData.registerType || ''
  const panelUrl = addonData.panelUrl || ''
  const registerWebhookHelp = addonData.registerWebhookHelp || ''
  const docsUrl = addonData.docsUrl || 'https://wsms.io/docs/addon-two-way/'
  const gatewaySetupUrl = addonData.gatewaySetupUrl || 'https://wsms.io/docs/two-way-gateway-setup/'

  // Settings values
  const smsForwardEnabled = getAddonSetting('two-way', 'notif_new_inbox_message', false)
  const smsForwardTemplate = getAddonSetting('two-way', 'notif_new_inbox_message_template', __('New SMS from %sender_number%: %sms_content%', 'wp-sms'))
  const emailForwardEnabled = getAddonSetting('two-way', 'email_new_inbox_message', false)
  const storeMessages = getAddonSetting('two-way', 'store_inbox_messages', true)
  const retentionDays = getAddonSetting('two-way', 'inbox_retention_days', '90')

  // UI state
  const copyResetTimerRef = useRef(null)
  const [copied, setCopied] = useState('')
  const [isResetting, setIsResetting] = useState(false)
  const [isRegistering, setIsRegistering] = useState(false)

  // Copy a webhook URL to clipboard (`which` marks the field that shows the check icon)
  const handleCopyUrl = async (value, which = 'query') => {
    try {
      await navigator.clipboard.writeText(value)
      clearTimeout(copyResetTimerRef.current)
      setCopied(which)
      toast({
        title: __('Copied', 'wp-sms'),
        description: __('Webhook URL copied to clipboard', 'wp-sms'),
        variant: 'success',
      })
      copyResetTimerRef.current = setTimeout(() => setCopied(''), 2000)
    } catch (error) {
      toast({
        title: __('Error', 'wp-sms'),
        description: __('Failed to copy URL', 'wp-sms'),
        variant: 'destructive',
      })
    }
  }

  // Reset webhook token
  const handleResetToken = async () => {
    try {
      setIsResetting(true)
      const response = await fetch(buildRestUrl('wp-sms-two-way/v1/webhook/reset-token'), {
        method: 'GET',
        headers: {
          'X-WP-Nonce': wpSettings.nonce,
        },
      })

      if (response.ok) {
        toast({
          title: __('Success', 'wp-sms'),
          description: __('Webhook token has been reset. Refreshing...', 'wp-sms'),
          variant: 'success',
        })
        setTimeout(() => window.location.reload(), 1500)
      } else {
        throw new Error(__('Failed to reset token', 'wp-sms'))
      }
    } catch (error) {
      toast({
        title: __('Error', 'wp-sms'),
        description: error.message || __('Failed to reset webhook token', 'wp-sms'),
        variant: 'destructive',
      })
    } finally {
      setIsResetting(false)
    }
  }

  // Register webhook via API
  const handleRegisterWebhook = async () => {
    try {
      setIsRegistering(true)
      const response = await fetch(buildRestUrl('wp-sms-two-way/v1/webhook/register'), {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': wpSettings.nonce,
        },
      })

      const data = await response.json()

      if (response.ok && data.success) {
        toast({
          title: __('Success', 'wp-sms'),
          description: __('Webhook registered successfully with your gateway.', 'wp-sms'),
          variant: 'success',
        })
      } else {
        throw new Error(data.message || __('Failed to register webhook', 'wp-sms'))
      }
    } catch (error) {
      toast({
        title: __('Error', 'wp-sms'),
        description: error.message || __('Failed to register webhook', 'wp-sms'),
        variant: 'destructive',
      })
    } finally {
      setIsRegistering(false)
    }
  }

  // Show placeholder if Two-Way add-on is not active
  if (!hasTwoWay) {
    return (
      <div className="wsms-space-y-6">
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Settings className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              {__('Two-Way SMS Settings', 'wp-sms')}
            </CardTitle>
            <CardDescription>
              {__('Configure your two-way SMS settings and webhook', 'wp-sms')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-6 wsms-text-center">
              <AlertCircle className="wsms-mx-auto wsms-h-10 wsms-w-10 wsms-text-muted-foreground wsms-mb-3" />
              <h3 className="wsms-font-medium wsms-mb-2">{__('Two-Way SMS Add-on Required', 'wp-sms')}</h3>
              <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mb-4">
                {__('Install and activate the WSMS Two-Way add-on to configure these settings.', 'wp-sms')}
              </p>
              <Button variant="outline" asChild>
                <a
                  href="https://wsms.io/product/wp-sms-two-way/"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {__('Learn More', 'wp-sms')}
                  <ExternalLink className="wsms-ms-2 wsms-h-4 wsms-w-4" />
                </a>
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  if (!isAddonDashboardReady('two-way')) {
    return <AddonUpdateRequired addonKey="two-way" icon={Settings} />
  }

  return (
    <div className="wsms-space-y-4 wsms-stagger-children">
      {/* Gateway Connection Status */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Link2 className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Gateway Connection', 'wp-sms')}
          </CardTitle>
          <CardDescription>
            {__('Webhook configuration for receiving incoming SMS messages', 'wp-sms')}
          </CardDescription>
          <div className="wsms-flex wsms-flex-wrap wsms-items-center wsms-gap-x-4 wsms-gap-y-1 wsms-pt-2">
            <HelpLink href={gatewaySetupUrl}>
              {currentGateway
                /* translators: %s: gateway name, e.g. Twilio */
                ? sprintf(__('Webhook setup for %s', 'wp-sms'), currentGateway)
                : __('Gateway webhook setup guide', 'wp-sms')}
            </HelpLink>
            <HelpLink href={docsUrl}>{__('Two-Way SMS documentation', 'wp-sms')}</HelpLink>
          </div>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          {/* Status Bar - follows Gateway.jsx pattern */}
          <div className={cn(
            "wsms-rounded-lg wsms-border wsms-transition-all wsms-duration-200",
            webhookSupported
              ? "wsms-border-primary/30 wsms-bg-primary/5"
              : "wsms-border-dashed wsms-border-border wsms-bg-muted/30"
          )}>
            <div className="wsms-flex wsms-items-center wsms-gap-3 wsms-p-3">
              <div className={cn(
                "wsms-flex wsms-h-8 wsms-w-8 wsms-items-center wsms-justify-center wsms-rounded-md",
                webhookSupported ? "wsms-bg-primary/10" : "wsms-bg-muted"
              )}>
                {webhookSupported ? (
                  <CheckCircle className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                ) : (
                  <XCircle className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                )}
              </div>
              <div>
                <p className="wsms-text-[11px] wsms-font-medium wsms-uppercase wsms-tracking-wide wsms-text-muted-foreground">
                  {webhookSupported ? __('Connected Gateway', 'wp-sms') : __('Gateway Status', 'wp-sms')}
                </p>
                <p className="wsms-text-[13px] wsms-font-semibold wsms-text-foreground">
                  {currentGateway || __('No Gateway Selected', 'wp-sms')}
                </p>
              </div>
            </div>
            {/* Inline status info */}
            <div className="wsms-border-t wsms-border-primary/20 wsms-px-3 wsms-py-2 wsms-bg-primary/[0.02]">
              <div className="wsms-flex wsms-items-center wsms-gap-2">
                <span className={cn(
                  "wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-font-medium",
                  webhookSupported ? "wsms-text-success" : "wsms-text-muted-foreground/50"
                )}>
                  {webhookSupported ? <CheckCircle className="wsms-h-3 wsms-w-3" /> : <XCircle className="wsms-h-3 wsms-w-3" />}
                  {__('Two-Way SMS', 'wp-sms')}
                </span>
                {!webhookSupported && currentGateway && (
                  <span className="wsms-text-[11px] wsms-text-muted-foreground">
                    — {__('This gateway does not support incoming messages', 'wp-sms')}
                  </span>
                )}
                {!webhookSupported && !currentGateway && (
                  <span className="wsms-text-[11px] wsms-text-muted-foreground">
                    — {__('Configure a gateway in Gateway settings first', 'wp-sms')}
                  </span>
                )}
              </div>
            </div>
          </div>

          {/* Webhook URL Section - Only show if gateway supports two-way */}
          {webhookSupported && (
            <div className="wsms-space-y-3">
              <div className="wsms-flex wsms-items-center wsms-justify-between">
                <Label className="wsms-text-sm wsms-font-medium">{__('Webhook URL', 'wp-sms')}</Label>
                {registerType && (
                  <span className="wsms-text-xs wsms-text-muted-foreground">
                    {registerType === 'api' ? __('Automatic registration', 'wp-sms') : __('Manual setup required', 'wp-sms')}
                  </span>
                )}
              </div>

              <div className="wsms-flex wsms-gap-2">
                <Input
                  value={webhookUrl}
                  readOnly
                  aria-label={__('Webhook URL', 'wp-sms')}
                  className="wsms-font-mono wsms-text-xs wsms-bg-muted/50"
                />
                <Button
                  variant="outline"
                  size="icon"
                  onClick={() => handleCopyUrl(webhookUrl, 'query')}
                  disabled={!webhookUrl}
                  title={__('Copy webhook URL', 'wp-sms')}
                >
                  {copied === 'query' ? (
                    <Check className="wsms-h-4 wsms-w-4 wsms-text-success" />
                  ) : (
                    <Copy className="wsms-h-4 wsms-w-4" />
                  )}
                </Button>
                {registerType === 'panel' && (
                  <Button
                    variant="outline"
                    size="icon"
                    onClick={handleResetToken}
                    disabled={isResetting}
                    title={__('Reset Token', 'wp-sms')}
                  >
                    {isResetting ? (
                      <RefreshCw className="wsms-h-4 wsms-w-4 wsms-animate-spin" />
                    ) : (
                      <RefreshCw className="wsms-h-4 wsms-w-4" />
                    )}
                  </Button>
                )}
              </div>

              {/* Action Buttons based on register type */}
              {registerType === 'api' && (
                <div className="wsms-flex wsms-items-center wsms-gap-3">
                  <Button
                    variant="default"
                    size="sm"
                    onClick={handleRegisterWebhook}
                    disabled={isRegistering}
                  >
                    {isRegistering ? (
                      <>
                        <Loader2 className="wsms-h-4 wsms-w-4 wsms-me-2 wsms-animate-spin" />
                        {__('Registering...', 'wp-sms')}
                      </>
                    ) : (
                      <>
                        <ArrowRight className="wsms-h-4 wsms-w-4 wsms-me-2 rtl:wsms-scale-x-[-1]" />
                        {__('Register Webhook', 'wp-sms')}
                      </>
                    )}
                  </Button>
                  {registerWebhookHelp && (
                    <span className="wsms-text-xs wsms-text-muted-foreground">{registerWebhookHelp}</span>
                  )}
                </div>
              )}

              {registerType === 'panel' && (
                <Tip variant="info">
                  {__('Copy this URL and add it to your gateway\'s webhook settings.', 'wp-sms')}{' '}
                  {panelUrl && (
                    <a
                      href={panelUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="wsms-text-primary hover:wsms-underline wsms-inline-flex wsms-items-center wsms-gap-1"
                    >
                      {__('Open Gateway Panel', 'wp-sms')}
                      <ExternalLink className="wsms-h-3 wsms-w-3" />
                    </a>
                  )}
                </Tip>
              )}

              {/* Fallback URL for gateways that drop the ?wpsms_token part when saving */}
              {registerType !== 'api' && webhookUrlPath && (
                <div className="wsms-space-y-2 wsms-rounded-lg wsms-border wsms-border-dashed wsms-border-border wsms-bg-muted/20 wsms-p-3">
                  <Label className="wsms-text-[13px] wsms-font-medium">
                    {__('Alternative URL (token in the path)', 'wp-sms')}
                  </Label>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground">
                    {__('Some gateways remove everything after the "?" when they save a webhook URL, which makes incoming messages fail. Use this URL instead if that happens.', 'wp-sms')}
                  </p>
                  <div className="wsms-flex wsms-gap-2">
                    <Input
                      value={webhookUrlPath}
                      readOnly
                      aria-label={__('Alternative webhook URL', 'wp-sms')}
                      className="wsms-font-mono wsms-text-xs wsms-bg-muted/50"
                    />
                    <Button
                      variant="outline"
                      size="icon"
                      onClick={() => handleCopyUrl(webhookUrlPath, 'path')}
                      title={__('Copy alternative webhook URL', 'wp-sms')}
                    >
                      {copied === 'path' ? (
                        <Check className="wsms-h-4 wsms-w-4 wsms-text-success" />
                      ) : (
                        <Copy className="wsms-h-4 wsms-w-4" />
                      )}
                    </Button>
                  </div>
                </div>
              )}
            </div>
          )}
        </CardContent>
      </Card>

      {/* Notifications Section */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Bell className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Admin Notifications', 'wp-sms')}
          </CardTitle>
          <CardDescription>
            {__('Choose how you want to be notified when new SMS messages arrive', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SettingRow
            title={__('Forward to SMS', 'wp-sms')}
            description={__('Send a copy of incoming messages to your admin mobile number', 'wp-sms')}
            checked={smsForwardEnabled === true}
            onCheckedChange={(checked) => updateAddonSetting('two-way', 'notif_new_inbox_message', checked)}
          />
          {smsForwardEnabled && (
            <div className="wsms-space-y-2">
              <Label htmlFor="sms-template" className="wsms-text-[13px] wsms-font-medium">
                {__('Message Template', 'wp-sms')}
              </Label>
              <TemplateTextarea
                id="sms-template"
                value={smsForwardTemplate || ''}
                onChange={(value) => updateAddonSetting('two-way', 'notif_new_inbox_message_template', value)}
                rows={2}
                placeholder={__('New SMS from %sender_number%: %sms_content%', 'wp-sms')}
                variables={['%sender_number%', '%sms_content%', '%site_name%', '%user_name%', '%subscriber_name%']}
              />
            </div>
          )}
          <SettingRow
            title={__('Forward to Email', 'wp-sms')}
            description={__('Send incoming messages to your WordPress admin email', 'wp-sms')}
            checked={emailForwardEnabled === true}
            onCheckedChange={(checked) => updateAddonSetting('two-way', 'email_new_inbox_message', checked)}
          />
        </CardContent>
      </Card>

      {/* Message Storage Section */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Database className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Message Storage', 'wp-sms')}
          </CardTitle>
          <CardDescription>
            {__('Control how incoming messages are stored in your database', 'wp-sms')}
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SettingRow
            title={__('Save Messages to Inbox', 'wp-sms')}
            description={__('Store incoming SMS messages for viewing and replying later', 'wp-sms')}
            checked={storeMessages === true}
            onCheckedChange={(checked) => updateAddonSetting('two-way', 'store_inbox_messages', checked)}
          />
          {storeMessages && (
            <SelectField
              label={__('Auto-Delete After', 'wp-sms')}
              value={String(retentionDays)}
              onValueChange={(value) => updateAddonSetting('two-way', 'inbox_retention_days', value)}
              placeholder={__('Select period', 'wp-sms')}
              description={__('Automatically remove old messages to save database space', 'wp-sms')}
              options={[
                { value: '30', label: __('30 days', 'wp-sms') },
                { value: '90', label: __('90 days', 'wp-sms') },
                { value: '180', label: __('180 days', 'wp-sms') },
                { value: '365', label: __('1 year', 'wp-sms') },
                { value: '0', label: __('Keep forever', 'wp-sms') },
              ]}
            />
          )}
        </CardContent>
      </Card>
    </div>
  )
}
