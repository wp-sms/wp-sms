import React, { useState } from 'react'
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
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { SwitchField } from '@/components/ui/form-field'
import { Tip } from '@/components/ui/ux-helpers'
import { useSettings } from '@/context/SettingsContext'
import { getWpSettings, __, cn } from '@/lib/utils'
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
  const webhookSupported = addonData.webhookSupported || false
  const currentGateway = addonData.currentGateway || ''
  const registerType = addonData.registerType || ''
  const panelUrl = addonData.panelUrl || ''
  const registerWebhookHelp = addonData.registerWebhookHelp || ''

  // Settings values
  const smsForwardEnabled = getAddonSetting('two-way', 'notif_new_inbox_message', false)
  const smsForwardTemplate = getAddonSetting('two-way', 'notif_new_inbox_message_template', __('New SMS from %sender_number%: %sms_content%'))
  const emailForwardEnabled = getAddonSetting('two-way', 'email_new_inbox_message', false)
  const storeMessages = getAddonSetting('two-way', 'store_inbox_messages', true)
  const retentionDays = getAddonSetting('two-way', 'inbox_retention_days', '90')

  // UI state
  const [copied, setCopied] = useState(false)
  const [isResetting, setIsResetting] = useState(false)
  const [isRegistering, setIsRegistering] = useState(false)

  // Copy webhook URL to clipboard
  const handleCopyUrl = async () => {
    try {
      await navigator.clipboard.writeText(webhookUrl)
      setCopied(true)
      toast({
        title: __('Copied'),
        description: __('Webhook URL copied to clipboard'),
      })
      setTimeout(() => setCopied(false), 2000)
    } catch (error) {
      toast({
        title: __('Error'),
        description: __('Failed to copy URL'),
        variant: 'destructive',
      })
    }
  }

  // Reset webhook token
  const handleResetToken = async () => {
    try {
      setIsResetting(true)
      const response = await fetch('/wp-json/wp-sms-two-way/v1/webhook/reset-token', {
        method: 'GET',
        headers: {
          'X-WP-Nonce': wpSettings.nonce,
        },
      })

      if (response.ok) {
        toast({
          title: __('Success'),
          description: __('Webhook token has been reset. Refreshing...'),
        })
        setTimeout(() => window.location.reload(), 1500)
      } else {
        throw new Error(__('Failed to reset token'))
      }
    } catch (error) {
      toast({
        title: __('Error'),
        description: error.message || __('Failed to reset webhook token'),
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
      const response = await fetch('/wp-json/wp-sms-two-way/v1/webhook/register', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-WP-Nonce': wpSettings.nonce,
        },
      })

      const data = await response.json()

      if (response.ok && data.success) {
        toast({
          title: __('Success'),
          description: __('Webhook registered successfully with your gateway.'),
        })
      } else {
        throw new Error(data.message || __('Failed to register webhook'))
      }
    } catch (error) {
      toast({
        title: __('Error'),
        description: error.message || __('Failed to register webhook'),
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
              <Settings className="wsms-h-5 wsms-w-5" />
              {__('Two-Way SMS Settings')}
            </CardTitle>
            <CardDescription>
              {__('Configure your two-way SMS settings and webhook')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-6 wsms-text-center">
              <AlertCircle className="wsms-mx-auto wsms-h-10 wsms-w-10 wsms-text-muted-foreground wsms-mb-3" />
              <h3 className="wsms-font-medium wsms-mb-2">{__('Two-Way SMS Add-on Required')}</h3>
              <p className="wsms-text-sm wsms-text-muted-foreground wsms-mb-4">
                {__('Install and activate the WP SMS Two-Way add-on to configure these settings.')}
              </p>
              <Button variant="outline" asChild>
                <a
                  href="https://wp-sms-pro.com/product/wp-sms-two-way/"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {__('Learn More')}
                  <ExternalLink className="wsms-ml-2 wsms-h-4 wsms-w-4" />
                </a>
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="wsms-space-y-4 wsms-stagger-children">
      {/* Gateway Connection Status */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Link2 className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Gateway Connection')}
          </CardTitle>
          <CardDescription>
            {__('Webhook configuration for receiving incoming SMS messages')}
          </CardDescription>
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
                  {webhookSupported ? __('Connected Gateway') : __('Gateway Status')}
                </p>
                <p className="wsms-text-[13px] wsms-font-semibold wsms-text-foreground">
                  {currentGateway || __('No Gateway Selected')}
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
                  {__('Two-Way SMS')}
                </span>
                {!webhookSupported && currentGateway && (
                  <span className="wsms-text-[11px] wsms-text-muted-foreground">
                    — {__('This gateway does not support incoming messages')}
                  </span>
                )}
                {!webhookSupported && !currentGateway && (
                  <span className="wsms-text-[11px] wsms-text-muted-foreground">
                    — {__('Configure a gateway in General settings first')}
                  </span>
                )}
              </div>
            </div>
          </div>

          {/* Webhook URL Section - Only show if gateway supports two-way */}
          {webhookSupported && (
            <div className="wsms-space-y-3">
              <div className="wsms-flex wsms-items-center wsms-justify-between">
                <Label className="wsms-text-sm wsms-font-medium">{__('Webhook URL')}</Label>
                {registerType && (
                  <span className="wsms-text-xs wsms-text-muted-foreground">
                    {registerType === 'api' ? __('Automatic registration') : __('Manual setup required')}
                  </span>
                )}
              </div>

              <div className="wsms-flex wsms-gap-2">
                <Input
                  value={webhookUrl}
                  readOnly
                  className="wsms-font-mono wsms-text-xs wsms-bg-muted/50"
                />
                <Button
                  variant="outline"
                  size="icon"
                  onClick={handleCopyUrl}
                  disabled={!webhookUrl}
                  title={__('Copy URL')}
                >
                  {copied ? (
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
                    title={__('Reset Token')}
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
                        <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" />
                        {__('Registering...')}
                      </>
                    ) : (
                      <>
                        <ArrowRight className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                        {__('Register Webhook')}
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
                  {__('Copy this URL and add it to your gateway\'s webhook settings.')}{' '}
                  {panelUrl && (
                    <a
                      href={panelUrl}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="wsms-text-primary hover:wsms-underline wsms-inline-flex wsms-items-center wsms-gap-1"
                    >
                      {__('Open Gateway Panel')}
                      <ExternalLink className="wsms-h-3 wsms-w-3" />
                    </a>
                  )}
                </Tip>
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
            {__('Admin Notifications')}
          </CardTitle>
          <CardDescription>
            {__('Choose how you want to be notified when new SMS messages arrive')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="wsms-divide-y wsms-divide-border wsms-rounded-lg wsms-border wsms-border-border wsms-overflow-hidden">
            {/* SMS Forwarding */}
            <div className="wsms-space-y-3 wsms-p-4">
              <div className="wsms-flex wsms-items-center wsms-justify-between">
                <div>
                  <Label htmlFor="sms-forward" className="wsms-text-sm wsms-font-medium wsms-cursor-pointer">
                    {__('Forward to SMS')}
                  </Label>
                  <p className="wsms-text-xs wsms-text-muted-foreground wsms-mt-0.5">
                    {__('Send a copy of incoming messages to your admin mobile number')}
                  </p>
                </div>
                <Switch
                  id="sms-forward"
                  checked={smsForwardEnabled === true}
                  onCheckedChange={(checked) => updateAddonSetting('two-way', 'notif_new_inbox_message', checked)}
                />
              </div>

              {/* SMS Template - Conditionally shown */}
              {smsForwardEnabled && (
                <div className="wsms-space-y-2 wsms-pt-2">
                  <Label htmlFor="sms-template" className="wsms-text-xs wsms-font-medium">
                    {__('Message Template')}
                  </Label>
                  <Textarea
                    id="sms-template"
                    value={smsForwardTemplate || ''}
                    onChange={(e) => updateAddonSetting('two-way', 'notif_new_inbox_message_template', e.target.value)}
                    rows={2}
                    className="wsms-text-xs"
                    placeholder={__('New SMS from %sender_number%: %sms_content%')}
                  />
                  <p className="wsms-text-[10px] wsms-text-muted-foreground">
                    {__('Variables:')} %sender_number%, %sms_content%, %site_name%
                  </p>
                </div>
              )}
            </div>

            {/* Email Forwarding */}
            <div className="wsms-flex wsms-items-center wsms-justify-between wsms-p-4">
              <div>
                <Label htmlFor="email-forward" className="wsms-text-sm wsms-font-medium wsms-cursor-pointer">
                  {__('Forward to Email')}
                </Label>
                <p className="wsms-text-xs wsms-text-muted-foreground wsms-mt-0.5">
                  {__('Send incoming messages to your WordPress admin email')}
                </p>
              </div>
              <Switch
                id="email-forward"
                checked={emailForwardEnabled === true}
                onCheckedChange={(checked) => updateAddonSetting('two-way', 'email_new_inbox_message', checked)}
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Message Storage Section */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Database className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Message Storage')}
          </CardTitle>
          <CardDescription>
            {__('Control how incoming messages are stored in your database')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="wsms-divide-y wsms-divide-border wsms-rounded-lg wsms-border wsms-border-border wsms-overflow-hidden">
            {/* Store Messages Toggle */}
            <SwitchField
              label={__('Save Messages to Inbox')}
              description={__('Store incoming SMS messages for viewing and replying later')}
              checked={storeMessages === true}
              onCheckedChange={(checked) => updateAddonSetting('two-way', 'store_inbox_messages', checked)}
              className="wsms-px-4"
            />

            {/* Retention Period */}
            {storeMessages && (
              <div className="wsms-flex wsms-items-center wsms-justify-between wsms-p-4">
                <div>
                  <Label htmlFor="retention" className="wsms-text-sm wsms-font-medium">
                    {__('Auto-Delete After')}
                  </Label>
                  <p className="wsms-text-xs wsms-text-muted-foreground wsms-mt-0.5">
                    {__('Automatically remove old messages to save database space')}
                  </p>
                </div>
                <Select
                  value={String(retentionDays)}
                  onValueChange={(value) => updateAddonSetting('two-way', 'inbox_retention_days', value)}
                >
                  <SelectTrigger id="retention" className="wsms-w-[140px]">
                    <SelectValue placeholder={__('Select period')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="30">{__('30 days')}</SelectItem>
                    <SelectItem value="90">{__('90 days')}</SelectItem>
                    <SelectItem value="180">{__('180 days')}</SelectItem>
                    <SelectItem value="365">{__('1 year')}</SelectItem>
                    <SelectItem value="0">{__('Keep forever')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
            )}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
