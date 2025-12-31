import React, { useState, useEffect, useCallback } from 'react'
import { Send, Zap, Image, Users, CheckCircle, AlertCircle, Loader2, CreditCard, User, Radio } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import { Input } from '@/components/ui/input'
import { RecipientSelector } from '@/components/shared/RecipientSelector'
import { MessageComposer, calculateSmsInfo } from '@/components/shared/MessageComposer'
import { Tip, ValidationMessage } from '@/components/ui/ux-helpers'
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

      {/* Main content - Two columns on desktop */}
      <div className="wsms-grid wsms-gap-6 lg:wsms-grid-cols-2">
        {/* Left column - Message */}
        <div className="wsms-space-y-6">
          {/* Sender ID Card */}
          <Card>
            <CardHeader className="wsms-pb-3">
              <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                <User className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                Sender ID
              </CardTitle>
            </CardHeader>
            <CardContent>
              <div className="wsms-space-y-2">
                <Input
                  type="text"
                  value={senderId}
                  onChange={(e) => setSenderId(e.target.value)}
                  placeholder="Enter sender ID"
                  maxLength={18}
                />
                <p className="wsms-text-[11px] wsms-text-muted-foreground">
                  The sender name or number that recipients will see
                </p>
              </div>
            </CardContent>
          </Card>

          {/* Message Card */}
          <Card>
            <CardHeader>
              <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                <Send className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                Compose Message
              </CardTitle>
              <CardDescription>
                Write your SMS message below
              </CardDescription>
            </CardHeader>
            <CardContent>
              <MessageComposer
                value={message}
                onChange={setMessage}
                placeholder="Type your message here..."
                rows={6}
                maxSegments={10}
              />
            </CardContent>
          </Card>

          {/* Options Card */}
          <Card>
            <CardHeader>
              <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                <Zap className="wsms-h-4 wsms-w-4 wsms-text-amber-500" />
                Message Options
              </CardTitle>
            </CardHeader>
            <CardContent className="wsms-space-y-4">
              {/* Gateway warnings */}
              {!gatewaySupportsBulk && (
                <div className="wsms-p-3 wsms-rounded-lg wsms-bg-amber-500/10 wsms-border wsms-border-amber-500/20">
                  <p className="wsms-text-[12px] wsms-text-amber-700 dark:wsms-text-amber-400">
                    This gateway doesn't support bulk SMS. Only the first number will receive the message when sending to groups.
                  </p>
                </div>
              )}

              {gatewayValidation && (
                <div className="wsms-p-3 wsms-rounded-lg wsms-bg-blue-500/10 wsms-border wsms-border-blue-500/20">
                  <p className="wsms-text-[12px] wsms-text-blue-700 dark:wsms-text-blue-400">
                    <span className="wsms-font-medium">Gateway format:</span>{' '}
                    <code className="wsms-px-1 wsms-py-0.5 wsms-rounded wsms-bg-blue-500/10">{gatewayValidation}</code>
                  </p>
                </div>
              )}

              {/* Flash SMS - only show if gateway supports it */}
              {gatewaySupportsFlash && (
                <div className="wsms-flex wsms-items-center wsms-justify-between wsms-p-3 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
                  <div className="wsms-space-y-0.5">
                    <label className="wsms-text-[13px] wsms-font-medium wsms-cursor-pointer">
                      Flash SMS
                    </label>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground">
                      Message appears directly on screen without saving
                    </p>
                  </div>
                  <Switch
                    checked={flashSms}
                    onCheckedChange={setFlashSms}
                  />
                </div>
              )}

              {/* Media URL - only show if gateway supports it */}
              {gatewaySupportsMedia ? (
                <div className="wsms-space-y-2">
                  <label className="wsms-flex wsms-items-center wsms-gap-2 wsms-text-[13px] wsms-font-medium">
                    <Image className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                    Media URL (MMS)
                  </label>
                  <Input
                    type="url"
                    value={mediaUrl}
                    onChange={(e) => setMediaUrl(e.target.value)}
                    placeholder="https://example.com/image.jpg"
                  />
                  <p className="wsms-text-[11px] wsms-text-muted-foreground">
                    Optional: Add an image or media URL for MMS
                  </p>
                </div>
              ) : (
                <div className="wsms-p-3 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
                  <div className="wsms-flex wsms-items-center wsms-gap-2">
                    <Image className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                    <span className="wsms-text-[13px] wsms-text-muted-foreground">
                      This gateway doesn't support MMS media
                    </span>
                  </div>
                </div>
              )}
            </CardContent>
          </Card>
        </div>

        {/* Right column - Recipients */}
        <div className="wsms-space-y-6">
          <Card>
            <CardHeader>
              <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                <Users className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                Select Recipients
              </CardTitle>
              <CardDescription>
                Choose who will receive this message
              </CardDescription>
            </CardHeader>
            <CardContent>
              <RecipientSelector
                value={recipients}
                onChange={setRecipients}
              />
            </CardContent>
          </Card>

          {/* Send Summary Card */}
          <Card className="wsms-border-primary/20 wsms-bg-primary/[0.02]">
            <CardContent className="wsms-py-5">
              <div className="wsms-space-y-4">
                {/* Stats */}
                <div className="wsms-grid wsms-grid-cols-3 wsms-gap-4 wsms-text-center">
                  <div className="wsms-space-y-1">
                    <p className="wsms-text-2xl wsms-font-bold wsms-text-foreground">
                      {isLoadingCount ? (
                        <Loader2 className="wsms-h-6 wsms-w-6 wsms-animate-spin wsms-mx-auto" />
                      ) : (
                        recipientCount
                      )}
                    </p>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground">Recipients</p>
                  </div>
                  <div className="wsms-space-y-1">
                    <p className="wsms-text-2xl wsms-font-bold wsms-text-foreground">
                      {smsInfo.segments}
                    </p>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground">Segments</p>
                  </div>
                  <div className="wsms-space-y-1">
                    <p className="wsms-text-2xl wsms-font-bold wsms-text-foreground">
                      {recipientCount * smsInfo.segments}
                    </p>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground">Total SMS</p>
                  </div>
                </div>

                {/* Credit display */}
                {credit !== null && (
                  <div className="wsms-flex wsms-items-center wsms-justify-center wsms-gap-2 wsms-py-2 wsms-px-3 wsms-rounded-md wsms-bg-muted/50">
                    <CreditCard className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                    <span className="wsms-text-[12px] wsms-text-muted-foreground">
                      Available Credit:
                    </span>
                    <span className="wsms-text-[13px] wsms-font-semibold wsms-text-foreground">
                      {credit}
                    </span>
                  </div>
                )}

                {/* Validation messages */}
                {!isValid && totalManualRecipients === 0 && message.trim() && (
                  <p className="wsms-text-[12px] wsms-text-amber-600 wsms-text-center">
                    Please select at least one recipient
                  </p>
                )}
                {!isValid && totalManualRecipients > 0 && !message.trim() && (
                  <p className="wsms-text-[12px] wsms-text-amber-600 wsms-text-center">
                    Please enter a message
                  </p>
                )}

                {/* Send button */}
                <Button
                  onClick={handleSend}
                  disabled={!canSend}
                  className="wsms-w-full wsms-h-11"
                  size="lg"
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
                      {recipientCount > 0 && (
                        <span className="wsms-ml-2 wsms-px-2 wsms-py-0.5 wsms-rounded-full wsms-bg-white/20 wsms-text-[11px]">
                          to {recipientCount}
                        </span>
                      )}
                    </>
                  )}
                </Button>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  )
}
