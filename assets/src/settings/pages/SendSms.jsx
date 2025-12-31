import React, { useState, useEffect, useCallback } from 'react'
import { Send, Zap, Image, Users, CheckCircle, AlertCircle, Loader2, CreditCard, User, Radio, MessageSquare, Hash, Clock } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import { Input } from '@/components/ui/input'
import { RecipientSelector } from '@/components/shared/RecipientSelector'
import { MessageComposer, calculateSmsInfo } from '@/components/shared/MessageComposer'
import { Tip } from '@/components/ui/ux-helpers'
import { smsApi } from '@/api/smsApi'
import { useSettings } from '@/context/SettingsContext'
import { cn } from '@/lib/utils'

export default function SendSms() {
  const { setCurrentPage } = useSettings()

  // Get gateway sender from settings
  const defaultSender = window.wpSmsSettings?.gateway?.from || ''
  const gatewaySupportsFlash = window.wpSmsSettings?.gateway?.flash === 'enable'
  const gatewaySupportsMedia = window.wpSmsSettings?.gateway?.supportMedia || false
  const gatewayValidation = window.wpSmsSettings?.gateway?.validateNumber || ''
  const gatewaySupportsBulk = window.wpSmsSettings?.gateway?.bulk_send !== false
  const gatewayConfigured = !!window.wpSmsSettings?.settings?.gateway_name
  const gatewayName = window.wpSmsSettings?.settings?.gateway_name || ''

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
  const [recipientCount, setRecipientCount] = useState(0)
  const [isLoadingCount, setIsLoadingCount] = useState(false)
  const [showAdvanced, setShowAdvanced] = useState(false)

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
      } catch (error) {
        console.error('Failed to fetch credit:', error)
      }
    }
    fetchCredit()
  }, [])

  // Validation
  const smsInfo = calculateSmsInfo(message)
  const isValid = message.trim().length > 0 && totalManualRecipients > 0
  const canSend = isValid && !isSending

  // Handle send
  const handleSend = useCallback(async () => {
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
            'wsms-flex wsms-items-center wsms-gap-3 wsms-p-4 wsms-rounded-lg wsms-border',
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
        <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-4 wsms-py-3 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
          <div className="wsms-flex wsms-items-center wsms-gap-6">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <Radio className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              <span className="wsms-text-[12px] wsms-text-muted-foreground">Gateway:</span>
              <span className="wsms-text-[12px] wsms-font-medium wsms-text-foreground">{gatewayName}</span>
            </div>
            {credit !== null && (
              <div className="wsms-flex wsms-items-center wsms-gap-2">
                <CreditCard className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                <span className="wsms-text-[12px] wsms-text-muted-foreground">Credit:</span>
                <span className="wsms-text-[12px] wsms-font-semibold wsms-text-foreground">{credit}</span>
              </div>
            )}
          </div>
          {gatewayValidation && (
            <div className="wsms-text-[11px] wsms-text-muted-foreground">
              Format: <code className="wsms-px-1 wsms-py-0.5 wsms-rounded wsms-bg-muted">{gatewayValidation}</code>
            </div>
          )}
        </div>
      )}

      {/* Main Compose Area - Full Width Card */}
      <Card className="wsms-overflow-hidden">
        <div className="wsms-grid lg:wsms-grid-cols-5">
          {/* Left: Message Compose - Takes 3 columns */}
          <div className="lg:wsms-col-span-3 wsms-border-r wsms-border-border">
            <CardHeader className="wsms-border-b wsms-border-border wsms-bg-muted/20">
              <div className="wsms-flex wsms-items-center wsms-justify-between">
                <div className="wsms-flex wsms-items-center wsms-gap-2">
                  <MessageSquare className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                  <CardTitle className="wsms-text-base">Compose Message</CardTitle>
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
                    className="wsms-w-36 wsms-h-8 wsms-text-[12px]"
                  />
                </div>
              </div>
            </CardHeader>
            <CardContent className="wsms-p-0">
              {/* Message Composer - Full Width */}
              <div className="wsms-p-4">
                <MessageComposer
                  value={message}
                  onChange={setMessage}
                  placeholder="Type your message here..."
                  rows={8}
                  maxSegments={10}
                />
              </div>

              {/* Message Options Bar */}
              <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-4 wsms-py-3 wsms-border-t wsms-border-border wsms-bg-muted/20">
                <div className="wsms-flex wsms-items-center wsms-gap-4">
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
                        Flash SMS
                      </span>
                    </label>
                  )}

                  {/* MMS Toggle */}
                  {gatewaySupportsMedia && (
                    <button
                      onClick={() => setShowAdvanced(!showAdvanced)}
                      className={cn(
                        'wsms-flex wsms-items-center wsms-gap-1 wsms-text-[12px] wsms-transition-colors',
                        showAdvanced ? 'wsms-text-primary' : 'wsms-text-muted-foreground hover:wsms-text-foreground'
                      )}
                    >
                      <Image className="wsms-h-3 wsms-w-3" />
                      Add Media
                    </button>
                  )}
                </div>

                {/* Message Stats */}
                <div className="wsms-flex wsms-items-center wsms-gap-4 wsms-text-[11px] wsms-text-muted-foreground">
                  <span className="wsms-flex wsms-items-center wsms-gap-1">
                    <Hash className="wsms-h-3 wsms-w-3" />
                    {smsInfo.length} chars
                  </span>
                  <span>|</span>
                  <span>{smsInfo.segments} segment{smsInfo.segments !== 1 ? 's' : ''}</span>
                  <span>|</span>
                  <span>{smsInfo.encoding}</span>
                </div>
              </div>

              {/* Media URL Input - Collapsible */}
              {showAdvanced && gatewaySupportsMedia && (
                <div className="wsms-px-4 wsms-py-3 wsms-border-t wsms-border-border wsms-bg-muted/10">
                  <div className="wsms-flex wsms-items-center wsms-gap-3">
                    <Image className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                    <Input
                      type="url"
                      value={mediaUrl}
                      onChange={(e) => setMediaUrl(e.target.value)}
                      placeholder="https://example.com/image.jpg"
                      className="wsms-flex-1"
                    />
                  </div>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mt-2 wsms-ml-7">
                    Add an image or media URL for MMS (if supported by gateway)
                  </p>
                </div>
              )}
            </CardContent>
          </div>

          {/* Right: Recipients - Takes 2 columns */}
          <div className="lg:wsms-col-span-2">
            <CardHeader className="wsms-border-b wsms-border-border wsms-bg-muted/20">
              <div className="wsms-flex wsms-items-center wsms-gap-2">
                <Users className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                <CardTitle className="wsms-text-base">Recipients</CardTitle>
                {recipientCount > 0 && (
                  <span className="wsms-ml-auto wsms-px-2 wsms-py-0.5 wsms-rounded-full wsms-bg-primary/10 wsms-text-primary wsms-text-[11px] wsms-font-medium">
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
                <div className="wsms-mt-4 wsms-p-3 wsms-rounded-lg wsms-bg-amber-500/10 wsms-border wsms-border-amber-500/20">
                  <p className="wsms-text-[11px] wsms-text-amber-700 dark:wsms-text-amber-400">
                    ⚠️ This gateway doesn't support bulk SMS. Only the first number will receive the message.
                  </p>
                </div>
              )}
            </CardContent>
          </div>
        </div>

        {/* Bottom Action Bar - Full Width */}
        <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-6 wsms-px-6 wsms-py-4 wsms-border-t wsms-border-border wsms-bg-muted/30">
          {/* Left: Summary Stats */}
          <div className="wsms-flex wsms-items-center wsms-gap-6">
            <div className="wsms-text-center">
              <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">
                {isLoadingCount ? (
                  <Loader2 className="wsms-h-5 wsms-w-5 wsms-animate-spin wsms-mx-auto" />
                ) : (
                  recipientCount
                )}
              </p>
              <p className="wsms-text-[10px] wsms-text-muted-foreground wsms-uppercase wsms-tracking-wide">Recipients</p>
            </div>
            <div className="wsms-w-px wsms-h-8 wsms-bg-border" />
            <div className="wsms-text-center">
              <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">{smsInfo.segments}</p>
              <p className="wsms-text-[10px] wsms-text-muted-foreground wsms-uppercase wsms-tracking-wide">Segments</p>
            </div>
            <div className="wsms-w-px wsms-h-8 wsms-bg-border" />
            <div className="wsms-text-center">
              <p className="wsms-text-xl wsms-font-bold wsms-text-primary">{recipientCount * smsInfo.segments}</p>
              <p className="wsms-text-[10px] wsms-text-muted-foreground wsms-uppercase wsms-tracking-wide">Total SMS</p>
            </div>
          </div>

          {/* Right: Validation + Send Button */}
          <div className="wsms-flex wsms-items-center wsms-gap-4">
            {/* Validation Messages */}
            {!isValid && (
              <p className="wsms-text-[12px] wsms-text-amber-600">
                {totalManualRecipients === 0 && message.trim() && 'Add recipients'}
                {totalManualRecipients > 0 && !message.trim() && 'Enter a message'}
                {totalManualRecipients === 0 && !message.trim() && 'Add message and recipients'}
              </p>
            )}

            {/* Send Button */}
            <Button
              onClick={handleSend}
              disabled={!canSend}
              size="lg"
              className="wsms-min-w-[160px] wsms-h-11"
            >
              {isSending ? (
                <>
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" />
                  Sending...
                </>
              ) : (
                <>
                  <Send className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                  Send Message
                </>
              )}
            </Button>
          </div>
        </div>
      </Card>
    </div>
  )
}
