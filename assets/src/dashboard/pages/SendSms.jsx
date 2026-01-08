import React, { useState, useEffect, useCallback } from 'react'
import { Send, Zap, Image, Users, Loader2, CreditCard, User, Radio, MessageSquare, Clock, Eye, CalendarClock, Repeat } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Switch } from '@/components/ui/switch'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { RecipientSelector } from '@/components/shared/RecipientSelector'
import { MessageComposer, calculateSmsInfo } from '@/components/shared/MessageComposer'
import { SmsPreviewDialog } from '@/components/shared/SmsPreviewDialog'
import { MediaSelector } from '@/components/shared/MediaSelector'
import { Tip } from '@/components/ui/ux-helpers'
import { smsApi } from '@/api/smsApi'
import { useSettings } from '@/context/SettingsContext'
import { cn, getGatewayDisplayName, __, getWpSettings } from '@/lib/utils'
import { useToast } from '@/components/ui/toaster'

export default function SendSms() {
  const { setCurrentPage, getSetting } = useSettings()
  const { toast } = useToast()

  // Check for Pro add-on and additional recipient types
  const { hasProAddon, additionalRecipientTypes = [] } = getWpSettings()

  // Get gateway capabilities (static, from page load)
  const gatewaySupportsFlash = window.wpSmsSettings?.gateway?.flash === 'enable'
  const gatewaySupportsMedia = window.wpSmsSettings?.gateway?.supportMedia || false
  const gatewayValidation = window.wpSmsSettings?.gateway?.validateNumber || ''
  const gatewaySupportsBulk = window.wpSmsSettings?.gateway?.bulk_send !== false
  const allGateways = window.wpSmsSettings?.gateways || {}

  // Get settings from context (stays in sync after save)
  const gatewayKey = getSetting('gateway_name', '')
  const gatewayConfigured = !!gatewayKey
  const gatewayName = getGatewayDisplayName(gatewayKey, allGateways)
  const showCreditOnSendPage = getSetting('account_credit_in_sendsms', '') === '1'
  const defaultSender = getSetting('gateway_sender_id', window.wpSmsSettings?.gateway?.from || '')

  // Form state
  const [senderId, setSenderId] = useState(defaultSender)
  const [message, setMessage] = useState('')
  const [recipients, setRecipients] = useState({ groups: [], roles: [], users: [], numbers: [] })
  const [flashSms, setFlashSms] = useState(false)
  const [mediaUrl, setMediaUrl] = useState('')

  // Scheduling state (Pro feature)
  const [scheduleEnabled, setScheduleEnabled] = useState(false)
  const [scheduledDate, setScheduledDate] = useState('')
  const [repeatEnabled, setRepeatEnabled] = useState(false)
  const [repeatInterval, setRepeatInterval] = useState(1)
  const [repeatIntervalUnit, setRepeatIntervalUnit] = useState('day')
  const [repeatEndDate, setRepeatEndDate] = useState('')
  const [repeatForever, setRepeatForever] = useState(false)

  // UI state
  const [isSending, setIsSending] = useState(false)
  const [credit, setCredit] = useState(null)
  const [creditSupported, setCreditSupported] = useState(true)
  const [recipientCount, setRecipientCount] = useState(0)
  const [isLoadingCount, setIsLoadingCount] = useState(false)
  const [showAdvanced, setShowAdvanced] = useState(false)
  const [showPreviewDialog, setShowPreviewDialog] = useState(false)

  // Sync sender ID when settings change (e.g., after saving gateway settings)
  useEffect(() => {
    setSenderId(defaultSender)
  }, [defaultSender])

  // Calculate recipient count (including additional types like WooCommerce, BuddyPress)
  const additionalRecipientsCount = additionalRecipientTypes.reduce(
    (count, type) => count + (recipients[type.id]?.length || 0), 0
  )
  const totalManualRecipients =
    recipients.groups.length + recipients.roles.length + (recipients.users?.length || 0) + recipients.numbers.length + additionalRecipientsCount

  // Debounced recipient count fetch
  useEffect(() => {
    if (totalManualRecipients === 0) {
      setRecipientCount(0)
      return
    }

    const timer = setTimeout(async () => {
      const hasGroupsOrRoles = recipients.groups.length > 0 || recipients.roles.length > 0 || (recipients.users?.length || 0) > 0 || additionalRecipientsCount > 0
      if (hasGroupsOrRoles) {
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
  }, [recipients, totalManualRecipients, additionalRecipientsCount])

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

    try {
      const result = await smsApi.send({
        message,
        recipients,
        from: senderId || undefined,
        flash: flashSms,
        mediaUrl: mediaUrl || undefined,
        // Scheduling options (Pro feature)
        ...(scheduleEnabled && {
          scheduled: scheduledDate,
          repeat: repeatEnabled ? {
            interval: repeatInterval,
            unit: repeatIntervalUnit,
            endDate: repeatForever ? null : repeatEndDate,
            forever: repeatForever,
          } : undefined,
        }),
      })

      setShowPreviewDialog(false)
      toast({
        title: __(`Message sent successfully to ${result.recipientCount || recipientCount} recipient(s)`),
        variant: 'success',
      })

      // Notify other pages (e.g., Outbox) that SMS was sent
      window.dispatchEvent(new CustomEvent('wpsms:sms-sent'))

      // Update credit
      if (result.credit !== undefined) {
        setCredit(result.credit)
      }

      // Reset form
      setMessage('')
      setRecipients({ groups: [], roles: [], users: [], numbers: [] })
      setSenderId(defaultSender)
      setFlashSms(false)
      setMediaUrl('')
      // Reset scheduling options
      setScheduleEnabled(false)
      setScheduledDate('')
      setRepeatEnabled(false)
      setRepeatInterval(1)
      setRepeatIntervalUnit('day')
      setRepeatEndDate('')
      setRepeatForever(false)
    } catch (error) {
      setShowPreviewDialog(false)
      toast({
        title: error.message || __('Failed to send message'),
        variant: 'destructive',
      })
    } finally {
      setIsSending(false)
    }
  }, [canSend, message, recipients, senderId, flashSms, mediaUrl, recipientCount, defaultSender, scheduleEnabled, scheduledDate, repeatEnabled, repeatInterval, repeatIntervalUnit, repeatEndDate, repeatForever, toast])

  return (
    <div className="wsms-space-y-6 wsms-stagger-children">
      {/* Gateway not configured warning */}
      {!gatewayConfigured && (
        <Tip variant="warning">
          <strong>{__('No SMS gateway configured.')}</strong> {__('You need to set up a gateway before you can send messages.')}{' '}
          <button
            onClick={() => setCurrentPage('gateway')}
            className="wsms-underline wsms-font-medium"
          >
            {__('Configure Gateway')} →
          </button>
        </Tip>
      )}

      {/* Status Bar - Full Width */}
      {gatewayConfigured && (
        <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-5 wsms-py-3.5 wsms-rounded-lg wsms-bg-gradient-to-r wsms-from-muted/50 wsms-to-muted/30 wsms-border wsms-border-border">
          <div className="wsms-flex wsms-items-center wsms-gap-6">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <div className="wsms-flex wsms-items-center wsms-justify-center wsms-w-8 wsms-h-8 wsms-rounded-lg wsms-bg-primary/10">
                <Radio className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              </div>
              <div>
                <span className="wsms-text-[10px] wsms-text-muted-foreground wsms-uppercase wsms-tracking-wide">{__('Gateway')}</span>
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
                    <span className="wsms-text-[10px] wsms-text-muted-foreground wsms-uppercase wsms-tracking-wide">{__('Credit')}</span>
                    <p className="wsms-text-[13px] wsms-font-semibold wsms-text-foreground">{credit}</p>
                  </div>
                </div>
              </>
            )}
          </div>
          {gatewayValidation && (
            <div className="wsms-text-[11px] wsms-text-muted-foreground">
              {__('Format:')} <code className="wsms-px-1.5 wsms-py-0.5 wsms-rounded-md wsms-bg-muted wsms-font-mono">{gatewayValidation}</code>
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
                <CardTitle className="wsms-text-sm">{__('Compose Message')}</CardTitle>
              </div>
              {/* Sender ID inline */}
              <div className="wsms-flex wsms-items-center wsms-gap-2">
                <User className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                <Input
                  type="text"
                  value={senderId}
                  onChange={(e) => setSenderId(e.target.value)}
                  placeholder={__('Sender ID')}
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
              placeholder={__('Type your message here...')}
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
                      {__('Flash')}
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
                    {__('Media')}
                  </button>
                )}
              </div>
            )}

            {/* Media Selector - Collapsible */}
            {showAdvanced && gatewaySupportsMedia && (
              <div className="wsms-mt-3">
                <MediaSelector
                  value={mediaUrl}
                  onChange={setMediaUrl}
                  allowedTypes={['image']}
                  buttonText={__('Select Image')}
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
              <CardTitle className="wsms-text-sm">{__('Recipients')}</CardTitle>
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
                  {__("This gateway doesn't support bulk SMS. Only the first number will receive the message.")}
                </p>
              </div>
            )}
          </CardContent>
        </Card>
      </div>

      {/* Scheduling Options - Pro Feature */}
      {hasProAddon && (
        <Card className="wsms-overflow-hidden">
          <CardHeader className="wsms-border-b wsms-border-border wsms-bg-muted/20 wsms-py-3">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <CalendarClock className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              <CardTitle className="wsms-text-sm">{__('Scheduling Options')}</CardTitle>
              <span className="wsms-ml-auto wsms-px-2 wsms-py-0.5 wsms-rounded-full wsms-bg-primary/10 wsms-text-primary wsms-text-[10px] wsms-font-medium">
                {__('Pro')}
              </span>
            </div>
          </CardHeader>
          <CardContent className="wsms-p-4">
            <div className="wsms-space-y-4">
              {/* Schedule Toggle */}
              <div className="wsms-flex wsms-items-center wsms-justify-between wsms-py-2">
                <div className="wsms-flex wsms-items-center wsms-gap-3">
                  <Clock className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                  <div>
                    <label className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">
                      {__('Schedule Message')}
                    </label>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground">
                      {__('Send this message at a specific date and time')}
                    </p>
                  </div>
                </div>
                <Switch
                  checked={scheduleEnabled}
                  onCheckedChange={setScheduleEnabled}
                />
              </div>

              {/* Schedule Date/Time */}
              {scheduleEnabled && (
                <div className="wsms-pl-7 wsms-space-y-4 wsms-border-l-2 wsms-border-primary/20 wsms-ml-2">
                  <div className="wsms-space-y-1.5">
                    <label className="wsms-text-[12px] wsms-font-medium wsms-text-foreground">
                      {__('Schedule Date & Time')}
                    </label>
                    <Input
                      type="datetime-local"
                      value={scheduledDate}
                      onChange={(e) => setScheduledDate(e.target.value)}
                      className="wsms-max-w-xs"
                      min={new Date().toISOString().slice(0, 16)}
                    />
                    <p className="wsms-text-[11px] wsms-text-muted-foreground">
                      {__('Timezone')}: {Intl.DateTimeFormat().resolvedOptions().timeZone}
                    </p>
                  </div>

                  {/* Repeat Toggle */}
                  <div className="wsms-flex wsms-items-center wsms-justify-between wsms-py-2 wsms-border-t wsms-border-border">
                    <div className="wsms-flex wsms-items-center wsms-gap-3">
                      <Repeat className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                      <div>
                        <label className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">
                          {__('Repeat Message')}
                        </label>
                        <p className="wsms-text-[11px] wsms-text-muted-foreground">
                          {__('Automatically send this message on a recurring schedule')}
                        </p>
                      </div>
                    </div>
                    <Switch
                      checked={repeatEnabled}
                      onCheckedChange={setRepeatEnabled}
                    />
                  </div>

                  {/* Repeat Options */}
                  {repeatEnabled && (
                    <div className="wsms-space-y-4 wsms-pl-7 wsms-border-l-2 wsms-border-primary/10 wsms-ml-2">
                      {/* Repeat Interval */}
                      <div className="wsms-space-y-1.5">
                        <label className="wsms-text-[12px] wsms-font-medium wsms-text-foreground">
                          {__('Repeat Every')}
                        </label>
                        <div className="wsms-flex wsms-items-center wsms-gap-2">
                          <Input
                            type="number"
                            min={1}
                            value={repeatInterval}
                            onChange={(e) => setRepeatInterval(parseInt(e.target.value) || 1)}
                            className="wsms-w-20"
                          />
                          <Select value={repeatIntervalUnit} onValueChange={setRepeatIntervalUnit}>
                            <SelectTrigger className="wsms-w-32">
                              <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                              <SelectItem value="day">{__('Day(s)')}</SelectItem>
                              <SelectItem value="week">{__('Week(s)')}</SelectItem>
                              <SelectItem value="month">{__('Month(s)')}</SelectItem>
                              <SelectItem value="year">{__('Year(s)')}</SelectItem>
                            </SelectContent>
                          </Select>
                        </div>
                      </div>

                      {/* End Date */}
                      <div className="wsms-space-y-1.5">
                        <label className="wsms-text-[12px] wsms-font-medium wsms-text-foreground">
                          {__('End Date')}
                        </label>
                        <div className="wsms-flex wsms-items-center wsms-gap-4">
                          <Input
                            type="date"
                            value={repeatEndDate}
                            onChange={(e) => setRepeatEndDate(e.target.value)}
                            disabled={repeatForever}
                            className="wsms-max-w-[160px]"
                            min={scheduledDate.split('T')[0] || new Date().toISOString().split('T')[0]}
                          />
                          <label className="wsms-flex wsms-items-center wsms-gap-2 wsms-cursor-pointer">
                            <input
                              type="checkbox"
                              checked={repeatForever}
                              onChange={(e) => setRepeatForever(e.target.checked)}
                              className="wsms-rounded wsms-border-border wsms-text-primary focus:wsms-ring-primary"
                            />
                            <span className="wsms-text-[12px] wsms-text-foreground">
                              {__('Repeat Forever')}
                            </span>
                          </label>
                        </div>
                      </div>
                    </div>
                  )}
                </div>
              )}
            </div>
          </CardContent>
        </Card>
      )}

      {/* Bottom Action Bar */}
      <Card className="wsms-overflow-hidden">
        <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-5 wsms-py-3">
          {/* Left: Summary Stats */}
          <div className="wsms-flex wsms-items-center wsms-gap-5 wsms-text-[13px]">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <Users className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <span className="wsms-text-muted-foreground">{__('Recipients:')}</span>
              <span className="wsms-font-semibold wsms-text-foreground">
                {isLoadingCount ? '...' : recipientCount}
              </span>
            </div>
            <div className="wsms-w-px wsms-h-4 wsms-bg-border" />
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <MessageSquare className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <span className="wsms-text-muted-foreground">{__('Segments:')}</span>
              <span className="wsms-font-semibold wsms-text-foreground">{smsInfo.segments}</span>
            </div>
            <div className="wsms-w-px wsms-h-4 wsms-bg-border" />
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <Send className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              <span className="wsms-text-muted-foreground">{__('Total:')}</span>
              <span className="wsms-font-bold wsms-text-primary">{recipientCount * smsInfo.segments}</span>
            </div>
          </div>

          {/* Right: Validation + Button */}
          <div className="wsms-flex wsms-items-center wsms-gap-4">
            {!canSend && (
              <p className="wsms-text-[12px] wsms-text-amber-700 dark:wsms-text-amber-500">
                {isLoadingCount
                  ? __('Checking recipients...')
                  : !gatewayConfigured
                    ? __('Configure gateway first')
                    : !hasMessage && !hasSelections
                      ? __('Add message and recipients')
                      : !hasMessage
                        ? __('Enter a message')
                        : !hasSelections
                          ? __('Add recipients')
                          : !hasActualRecipients
                            ? __('Selected groups/roles have no subscribers')
                            : null}
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
              {__('Review & Send')}
            </Button>
          </div>
        </div>
      </Card>

      {/* Preview Dialog */}
      <SmsPreviewDialog
        open={showPreviewDialog}
        onOpenChange={setShowPreviewDialog}
        message={message}
        senderId={senderId || 'WSMS'}
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
