import React, { useState, useEffect, useCallback } from 'react'
import { Send, Zap, Image, Users, CheckCircle, AlertCircle, Loader2, CreditCard, User, Radio, MessageSquare, Hash, Clock, Eye, ChevronDown, ChevronUp } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import { Input } from '@/components/ui/input'
import { RecipientSelector } from '@/components/shared/RecipientSelector'
import { MessageComposer, calculateSmsInfo } from '@/components/shared/MessageComposer'
import { SmsPreviewDialog } from '@/components/shared/SmsPreviewDialog'
import { Tip } from '@/components/ui/ux-helpers'
import { smsApi } from '@/api/smsApi'
import { useSettings } from '@/context/SettingsContext'
import { cn, getGatewayDisplayName } from '@/lib/utils'

export default function SendSms() {
  const { setCurrentPage } = useSettings()

  // Get gateway capabilities (static, from page load)
  const defaultSender = window.wpSmsSettings?.gateway?.from || ''
  const gatewaySupportsFlash = window.wpSmsSettings?.gateway?.flash === 'enable'
  const gatewaySupportsMedia = window.wpSmsSettings?.gateway?.supportMedia || false
  const gatewayValidation = window.wpSmsSettings?.gateway?.validateNumber || ''
  const gatewaySupportsBulk = window.wpSmsSettings?.gateway?.bulk_send !== false
  const gatewayConfigured = !!window.wpSmsSettings?.settings?.gateway_name
  const gatewayKey = window.wpSmsSettings?.settings?.gateway_name || ''
  const allGateways = window.wpSmsSettings?.gateways || {}
  const gatewayName = getGatewayDisplayName(gatewayKey, allGateways)
  const showCreditOnSendPage = window.wpSmsSettings?.settings?.account_credit_in_sendsms === '1'

  // Form state
  const [senderId, setSenderId] = useState(defaultSender)
  const [message, setMessage] = useState('')
  const [recipients, setRecipients] = useState({ groups: [], roles: [], numbers: [] })
  const [flashSms, setFlashSms] = useState(false)
  const [mediaUrl, setMediaUrl] = useState('')

  // UI state
  const [isSending, setIsSending] = useState(false)
  const [notification, setNotification] = useState(null)
  const [credit, setCredit] = useState(null)
  const [creditSupported, setCreditSupported] = useState(true)
  const [recipientCount, setRecipientCount] = useState(0)
  const [isLoadingCount, setIsLoadingCount] = useState(false)
  const [showAdvanced, setShowAdvanced] = useState(false)
  const [showPreviewDialog, setShowPreviewDialog] = useState(false)

  // Calculate recipient count
  const totalManualRecipients =
    recipients.groups.length + recipients.roles.length + recipients.numbers.length

  // Debounced recipient count fetch
  useEffect(() => {
    if (totalManualRecipients === 0) {
      setRecipientCount(0)
      return
    }

    const timer = setTimeout(async () => {
      if (recipients.groups.length > 0 || recipients.roles.length > 0) {
        setIsLoadingCount(true)
        try {
          const count = await smsApi.getRecipientCount(recipients)
          setRecipientCount(count.total)
        } catch (error) {
          console.error('Failed to get recipient count:', error)
          // Fallback to manual count
          setRecipientCount(recipients.numbers.length)
        } finally {
          setIsLoadingCount(false)
        }
      } else {
        setRecipientCount(recipients.numbers.length)
      }
    }, 500)

    return () => clearTimeout(timer)
  }, [recipients, totalManualRecipients])

  // Fetch initial credit
  useEffect(() => {
    const fetchCredit = async () => {
      try {
        const result = await smsApi.getCredit()
        setCredit(result.credit)
        setCreditSupported(result.creditSupported)
      } catch (error) {
        console.error('Failed to fetch credit:', error)
        setCreditSupported(false)
      }
    }
    fetchCredit()
  }, [])

  // Validation
  const smsInfo = calculateSmsInfo(message)
  const hasMessage = message.trim().length > 0
  const hasSelections = totalManualRecipients > 0
  const hasActualRecipients = recipientCount > 0
  const isValid = hasMessage && hasSelections
  // Allow send if gateway is configured, has message and actual recipients
  const canSend = gatewayConfigured && hasMessage && hasActualRecipients && !isSending && !isLoadingCount

  // Handle preview button click
  const handlePreview = useCallback(() => {
    if (canSend) {
      setShowPreviewDialog(true)
    }
  }, [canSend])

  // Handle confirmed send
  const handleConfirmedSend = useCallback(async () => {
    if (!canSend) return

    setIsSending(true)
    setNotification(null)

    try {
      const result = await smsApi.send({
        message,
        recipients,
        from: senderId || undefined,
        flash: flashSms,
        mediaUrl: mediaUrl || undefined,
      })

      setShowPreviewDialog(false)
      setNotification({
        type: 'success',
        message: `Message sent successfully to ${result.recipientCount || recipientCount} recipient(s)`,
      })

      // Update credit
      if (result.credit !== undefined) {
        setCredit(result.credit)
      }

      // Reset form
      setMessage('')
      setRecipients({ groups: [], roles: [], numbers: [] })
      setSenderId(defaultSender)
      setFlashSms(false)
      setMediaUrl('')
    } catch (error) {
      setShowPreviewDialog(false)
      setNotification({
        type: 'error',
        message: error.message || 'Failed to send message',
      })
    } finally {
      setIsSending(false)
    }
  }, [canSend, message, recipients, senderId, flashSms, mediaUrl, recipientCount, defaultSender])

  // Clear notification after 5 seconds
  useEffect(() => {
    if (notification) {
      const timer = setTimeout(() => setNotification(null), 5000)
      return () => clearTimeout(timer)
    }
  }, [notification])

  return (
    <div className="wsms-space-y-6 wsms-stagger-children">
      {/* Gateway not configured warning */}
      {!gatewayConfigured && (
        <Tip variant="warning">
          <strong>No SMS gateway configured.</strong> You need to set up a gateway before you can send messages.{' '}
          <button
            onClick={() => setCurrentPage('gateway')}
            className="wsms-underline wsms-font-medium"
          >
            Configure Gateway →
          </button>
        </Tip>
      )}

      {/* Notification */}
      {notification && (
        <div
          className={cn(
            'wsms-flex wsms-items-center wsms-gap-3 wsms-p-4 wsms-rounded-xl wsms-border',
            'wsms-animate-in wsms-fade-in wsms-slide-in-from-top-2 wsms-duration-300',
            notification.type === 'success'
              ? 'wsms-bg-emerald-500/10 wsms-border-emerald-500/20 wsms-text-emerald-700 dark:wsms-text-emerald-400'
              : 'wsms-bg-red-500/10 wsms-border-red-500/20 wsms-text-red-700 dark:wsms-text-red-400'
          )}
        >
          {notification.type === 'success' ? (
            <CheckCircle className="wsms-h-5 wsms-w-5 wsms-shrink-0" />
          ) : (
            <AlertCircle className="wsms-h-5 wsms-w-5 wsms-shrink-0" />
          )}
          <p className="wsms-text-[13px] wsms-font-medium">{notification.message}</p>
        </div>
      )}

      {/* Status Bar - Full Width */}
      {gatewayConfigured && (
        <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-5 wsms-py-3.5 wsms-rounded-xl wsms-bg-gradient-to-r wsms-from-muted/50 wsms-to-muted/30 wsms-border wsms-border-border">
          <div className="wsms-flex wsms-items-center wsms-gap-6">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <div className="wsms-flex wsms-items-center wsms-justify-center wsms-w-8 wsms-h-8 wsms-rounded-lg wsms-bg-primary/10">
                <Radio className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              </div>
              <div>
                <span className="wsms-text-[10px] wsms-text-muted-foreground wsms-uppercase wsms-tracking-wide">Gateway</span>
                <p className="wsms-text-[13px] wsms-font-semibold wsms-text-foreground">{gatewayName}</p>
              </div>
            </div>
            {showCreditOnSendPage && creditSupported && credit !== null && (
              <>
                <div className="wsms-w-px wsms-h-10 wsms-bg-border" />
                <div className="wsms-flex wsms-items-center wsms-gap-2">
                  <div className="wsms-flex wsms-items-center wsms-justify-center wsms-w-8 wsms-h-8 wsms-rounded-lg wsms-bg-emerald-500/10">
                    <CreditCard className="wsms-h-4 wsms-w-4 wsms-text-emerald-600" />
                  </div>
                  <div>
                    <span className="wsms-text-[10px] wsms-text-muted-foreground wsms-uppercase wsms-tracking-wide">Credit</span>
                    <p className="wsms-text-[13px] wsms-font-semibold wsms-text-foreground">{credit}</p>
                  </div>
                </div>
              </>
            )}
          </div>
          {gatewayValidation && (
            <div className="wsms-text-[11px] wsms-text-muted-foreground">
              Format: <code className="wsms-px-1.5 wsms-py-0.5 wsms-rounded-md wsms-bg-muted wsms-font-mono">{gatewayValidation}</code>
            </div>
          )}
        </div>
      )}

      {/* Two Column Layout - Aligned Heights */}
      <div className="wsms-grid lg:wsms-grid-cols-2 wsms-gap-6 wsms-items-start">
        {/* Left Column: Message Compose */}
        <Card className="wsms-overflow-hidden">
          <CardHeader className="wsms-border-b wsms-border-border wsms-bg-muted/20 wsms-py-3">
            <div className="wsms-flex wsms-items-center wsms-justify-between">
              <div className="wsms-flex wsms-items-center wsms-gap-2">
                <MessageSquare className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                <CardTitle className="wsms-text-sm">Compose Message</CardTitle>
              </div>
              {/* Sender ID inline */}
              <div className="wsms-flex wsms-items-center wsms-gap-2">
                <User className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                <Input
                  type="text"
                  value={senderId}
                  onChange={(e) => setSenderId(e.target.value)}
                  placeholder="Sender ID"
                  maxLength={18}
                  className="wsms-w-32 wsms-h-7 wsms-text-[12px]"
                />
              </div>
            </div>
          </CardHeader>
          <CardContent className="wsms-p-4">
            {/* Message Composer */}
            <MessageComposer
              value={message}
              onChange={setMessage}
              placeholder="Type your message here..."
              rows={8}
              maxSegments={10}
            />

            {/* Options Row - Only show if gateway supports options */}
            {(gatewaySupportsFlash || gatewaySupportsMedia) && (
              <div className="wsms-flex wsms-items-center wsms-gap-4 wsms-mt-3 wsms-pt-3 wsms-border-t wsms-border-border">
                {/* Flash SMS */}
                {gatewaySupportsFlash && (
                  <label className="wsms-flex wsms-items-center wsms-gap-2 wsms-cursor-pointer">
                    <Switch
                      checked={flashSms}
                      onCheckedChange={setFlashSms}
                      className="wsms-scale-90"
                    />
                    <span className="wsms-text-[12px] wsms-text-muted-foreground">
                      <Zap className="wsms-h-3 wsms-w-3 wsms-inline wsms-mr-1 wsms-text-amber-500" />
                      Flash
                    </span>
                  </label>
                )}

                {/* MMS Toggle */}
                {gatewaySupportsMedia && (
                  <button
                    onClick={() => setShowAdvanced(!showAdvanced)}
                    className={cn(
                      'wsms-flex wsms-items-center wsms-gap-1 wsms-text-[12px] wsms-transition-colors',
                      showAdvanced
                        ? 'wsms-text-primary'
                        : 'wsms-text-muted-foreground hover:wsms-text-foreground'
                    )}
                  >
                    <Image className="wsms-h-3.5 wsms-w-3.5" />
                    Media
                  </button>
                )}
              </div>
            )}

            {/* Media URL Input - Collapsible */}
            {showAdvanced && gatewaySupportsMedia && (
              <div className="wsms-mt-3">
                <Input
                  type="url"
                  value={mediaUrl}
                  onChange={(e) => setMediaUrl(e.target.value)}
                  placeholder="Media URL (https://...)"
                  className="wsms-text-[12px]"
                />
              </div>
            )}
          </CardContent>
        </Card>

        {/* Right Column: Recipients */}
        <Card className="wsms-overflow-hidden">
          <CardHeader className="wsms-border-b wsms-border-border wsms-bg-muted/20 wsms-py-3">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <Users className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              <CardTitle className="wsms-text-sm">Recipients</CardTitle>
              {recipientCount > 0 && (
                <span className="wsms-ml-auto wsms-px-2 wsms-py-0.5 wsms-rounded-full wsms-bg-primary wsms-text-primary-foreground wsms-text-[11px] wsms-font-medium">
                  {isLoadingCount ? (
                    <Loader2 className="wsms-h-3 wsms-w-3 wsms-animate-spin" />
                  ) : (
                    recipientCount
                  )}
                </span>
              )}
            </div>
          </CardHeader>
          <CardContent className="wsms-p-4">
            <RecipientSelector
              value={recipients}
              onChange={setRecipients}
            />

            {/* Warnings */}
            {!gatewaySupportsBulk && recipients.groups.length > 0 && (
              <div className="wsms-mt-3 wsms-p-2.5 wsms-rounded-lg wsms-bg-amber-500/10 wsms-border wsms-border-amber-500/20">
                <p className="wsms-text-[11px] wsms-text-amber-700 dark:wsms-text-amber-400">
                  This gateway doesn't support bulk SMS. Only the first number will receive the message.
                </p>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Bottom Action Bar */}
      <Card className="wsms-overflow-hidden">
        <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-5 wsms-py-3">
          {/* Left: Summary Stats */}
          <div className="wsms-flex wsms-items-center wsms-gap-5 wsms-text-[13px]">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <Users className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <span className="wsms-text-muted-foreground">Recipients:</span>
              <span className="wsms-font-semibold wsms-text-foreground">
                {isLoadingCount ? '...' : recipientCount}
              </span>
            </div>
            <div className="wsms-w-px wsms-h-4 wsms-bg-border" />
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <MessageSquare className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <span className="wsms-text-muted-foreground">Segments:</span>
              <span className="wsms-font-semibold wsms-text-foreground">{smsInfo.segments}</span>
            </div>
            <div className="wsms-w-px wsms-h-4 wsms-bg-border" />
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <Send className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              <span className="wsms-text-muted-foreground">Total:</span>
              <span className="wsms-font-bold wsms-text-primary">{recipientCount * smsInfo.segments}</span>
            </div>
          </div>

          {/* Right: Validation + Button */}
          <div className="wsms-flex wsms-items-center wsms-gap-4">
            {!canSend && (
              <p className="wsms-text-[12px] wsms-text-amber-700 dark:wsms-text-amber-500">
                {!gatewayConfigured && 'Configure gateway first'}
                {gatewayConfigured && !hasMessage && !hasSelections && 'Add message and recipients'}
                {gatewayConfigured && !hasMessage && hasSelections && 'Enter a message'}
                {gatewayConfigured && hasMessage && !hasSelections && 'Add recipients'}
                {gatewayConfigured && hasMessage && hasSelections && !hasActualRecipients && !isLoadingCount && 'Selected groups/roles have no subscribers'}
                {isLoadingCount && 'Checking recipients...'}
              </p>
            )}
            <Button
              onClick={handlePreview}
              disabled={!canSend}
              className="wsms-gap-2"
            >
              {isLoadingCount ? (
                <Loader2 className="wsms-h-4 wsms-w-4 wsms-animate-spin" />
              ) : (
                <Eye className="wsms-h-4 wsms-w-4" />
              )}
              Review & Send
            </Button>
          </div>
        </div>
      </Card>

      {/* Preview Dialog */}
      <SmsPreviewDialog
        open={showPreviewDialog}
        onOpenChange={setShowPreviewDialog}
        message={message}
        senderId={senderId || 'WP SMS'}
        recipients={recipients}
        recipientCount={recipientCount}
        smsInfo={smsInfo}
        isFlash={flashSms}
        hasMedia={!!mediaUrl}
        onConfirm={handleConfirmedSend}
        isSending={isSending}
      />
    </div>
  )
}
