import * as React from 'react'
import { __, _n, sprintf } from '@wordpress/i18n'
import { Send, Users, MessageSquare, Hash, Zap, Image, Check, AlertTriangle, Loader2, XCircle, RefreshCw, CheckCircle2 } from 'lucide-react'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
  DialogBody,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import { cn } from '@/lib/utils'

/**
 * PhoneMockup - Simple phone frame showing SMS preview
 */
function PhoneMockup({ message, senderId, isFlash, hasMedia }) {
  const currentTime = new Date().toLocaleTimeString('en-US', {
    hour: 'numeric',
    minute: '2-digit',
    hour12: true,
  })

  return (
    <div className="wsms-relative wsms-mx-auto wsms-w-[220px]">
      {/* Phone Frame */}
      <div className="wsms-relative wsms-rounded-[24px] wsms-p-1.5 wsms-bg-zinc-800 wsms-shadow-lg">
        {/* Phone Screen */}
        <div className="wsms-relative wsms-rounded-[20px] wsms-overflow-hidden wsms-bg-white dark:wsms-bg-slate-900">
          {/* Status Bar */}
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-px-4 wsms-py-1.5 wsms-bg-slate-100 dark:wsms-bg-slate-800">
            <span className="wsms-text-[10px] wsms-font-medium wsms-text-slate-600 dark:wsms-text-slate-300">
              {currentTime}
            </span>
            <div className="wsms-flex wsms-items-center wsms-gap-1">
              <div className="wsms-w-4 wsms-h-2 wsms-rounded-sm wsms-bg-slate-400 dark:wsms-bg-slate-500" />
            </div>
          </div>

          {/* Messages Header */}
          <div className="wsms-px-3 wsms-py-2 wsms-border-b wsms-border-slate-200 dark:wsms-border-slate-700">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <div className="wsms-w-7 wsms-h-7 wsms-rounded-full wsms-bg-primary wsms-flex wsms-items-center wsms-justify-center">
                <MessageSquare className="wsms-h-3.5 wsms-w-3.5 wsms-text-primary-foreground" />
              </div>
              <div className="wsms-flex-1 wsms-min-w-0">
                <p className="wsms-text-[12px] wsms-font-medium wsms-text-foreground wsms-truncate">
                  {senderId || 'SMS'}
                </p>
              </div>
              {isFlash && (
                <span className="wsms-px-1.5 wsms-py-0.5 wsms-rounded wsms-text-[9px] wsms-font-medium wsms-bg-amber-100 wsms-text-amber-700">
                  {__('Flash', 'wp-sms')}
                </span>
              )}
            </div>
          </div>

          {/* Message Area */}
          <div className="wsms-min-h-[160px] wsms-max-h-[200px] wsms-p-3 wsms-overflow-y-auto wsms-bg-slate-50 dark:wsms-bg-slate-900/50">
            {/* Message Bubble */}
            <div className="wsms-flex wsms-justify-start">
              <div className="wsms-max-w-[90%] wsms-rounded-xl wsms-rounded-tl-sm wsms-px-3 wsms-py-2 wsms-bg-white dark:wsms-bg-slate-800 wsms-shadow-sm wsms-border wsms-border-slate-200 dark:wsms-border-slate-700">
                {hasMedia && (
                  <div className="wsms-mb-2 wsms-p-2 wsms-rounded wsms-bg-slate-100 dark:wsms-bg-slate-700 wsms-flex wsms-items-center wsms-gap-1.5">
                    <Image className="wsms-h-3 wsms-w-3 wsms-text-slate-500" />
                    <span className="wsms-text-[10px] wsms-text-slate-500">{__('Media', 'wp-sms')}</span>
                  </div>
                )}
                <p className="wsms-text-[12px] wsms-text-foreground wsms-leading-relaxed wsms-whitespace-pre-wrap wsms-break-words">
                  {message || 'Your message...'}
                </p>
                <p className="wsms-text-[9px] wsms-text-muted-foreground wsms-text-right wsms-mt-1">
                  {currentTime}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  )
}

/**
 * StatItem - Compact stat display
 */
function StatItem({ icon: Icon, label, value, highlight = false }) {
  return (
    <div className="wsms-flex wsms-items-center wsms-gap-2">
      <Icon className={cn('wsms-h-4 wsms-w-4', highlight ? 'wsms-text-primary' : 'wsms-text-muted-foreground')} />
      <span className="wsms-text-[13px] wsms-text-muted-foreground">{label}:</span>
      <span className={cn('wsms-text-[14px] wsms-font-semibold', highlight ? 'wsms-text-primary' : 'wsms-text-foreground')}>
        {value}
      </span>
    </div>
  )
}

/**
 * SmsPreviewDialog - Confirmation dialog before sending SMS
 */
export function SmsPreviewDialog({
  open,
  onOpenChange,
  message,
  senderId,
  recipients,
  recipientCount,
  smsInfo,
  isFlash,
  hasMedia,
  onConfirm,
  onRetry,
  onDone,
  isSending,
  status = 'preview',
  resultMessage = '',
}) {
  const totalSms = recipientCount * smsInfo.segments
  const { groups = [], roles = [], users = [], numbers = [] } = recipients

  return (
    <Dialog open={open} onOpenChange={status === 'preview' ? (isSending ? undefined : onOpenChange) : onDone}>
      <DialogContent size="lg" showClose={!isSending}>
        {status === 'success' ? (
          <>
            <DialogHeader>
              <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
                <CheckCircle2 className="wsms-h-4 wsms-w-4 wsms-text-emerald-600" />
                {__('Message Sent', 'wp-sms')}
              </DialogTitle>
            </DialogHeader>
            <DialogBody>
              <div className="wsms-flex wsms-flex-col wsms-items-center wsms-justify-center wsms-py-8 wsms-transition-all wsms-duration-300">
                <div className="wsms-flex wsms-items-center wsms-justify-center wsms-w-16 wsms-h-16 wsms-rounded-full wsms-bg-emerald-100 dark:wsms-bg-emerald-900/40 wsms-mb-4">
                  <CheckCircle2 className="wsms-h-8 wsms-w-8 wsms-text-emerald-600 dark:wsms-text-emerald-400" />
                </div>
                <p className="wsms-text-[15px] wsms-font-medium wsms-text-foreground wsms-text-center wsms-mb-1">
                  {resultMessage}
                </p>
              </div>
            </DialogBody>
            <DialogFooter />
          </>
        ) : status === 'error' ? (
          <>
            <DialogHeader>
              <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
                <XCircle className="wsms-h-4 wsms-w-4 wsms-text-red-600" />
                {__('Sending Failed', 'wp-sms')}
              </DialogTitle>
            </DialogHeader>
            <DialogBody>
              <div className="wsms-flex wsms-flex-col wsms-items-center wsms-justify-center wsms-py-8 wsms-transition-all wsms-duration-300">
                <div className="wsms-flex wsms-items-center wsms-justify-center wsms-w-16 wsms-h-16 wsms-rounded-full wsms-bg-red-100 dark:wsms-bg-red-900/40 wsms-mb-4">
                  <XCircle className="wsms-h-8 wsms-w-8 wsms-text-red-600 dark:wsms-text-red-400" />
                </div>
                <p className="wsms-text-[15px] wsms-font-medium wsms-text-foreground wsms-text-center wsms-mb-1">
                  {resultMessage}
                </p>
              </div>
            </DialogBody>
            <DialogFooter>
              <Button variant="outline" onClick={onDone} size="sm">
                {__('Close', 'wp-sms')}
              </Button>
              <Button onClick={onRetry} size="sm">
                <RefreshCw className="wsms-h-4 wsms-w-4 wsms-me-1.5" />
                {__('Retry', 'wp-sms')}
              </Button>
            </DialogFooter>
          </>
        ) : (
          <>
            <DialogHeader>
              <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
                <Send className="wsms-h-4 wsms-w-4 wsms-text-primary" />
                {__('Review & Send', 'wp-sms')}
              </DialogTitle>
              <DialogDescription>
                {__('Review your message before sending.', 'wp-sms')}
              </DialogDescription>
            </DialogHeader>

            <DialogBody className="wsms-p-0">
              <div className="wsms-grid md:wsms-grid-cols-2 wsms-gap-0">
                {/* Left: Phone Preview */}
                <div className="wsms-p-5 wsms-bg-muted/30 wsms-flex wsms-items-center wsms-justify-center wsms-border-e wsms-border-border">
                  <PhoneMockup
                    message={message}
                    senderId={senderId}
                    isFlash={isFlash}
                    hasMedia={hasMedia}
                  />
                </div>

                {/* Right: Details */}
                <div className="wsms-p-5 wsms-space-y-4">
                  {/* Stats */}
                  <div className="wsms-space-y-2.5 wsms-p-3 wsms-rounded-lg wsms-bg-muted/30">
                    <StatItem icon={Users} label={__('Recipients', 'wp-sms')} value={recipientCount} />
                    <StatItem icon={MessageSquare} label={__('Segments', 'wp-sms')} value={smsInfo.segments} />
                    <StatItem icon={Hash} label={__('Characters', 'wp-sms')} value={smsInfo.characters || smsInfo.length} />
                    <div className="wsms-pt-2 wsms-border-t wsms-border-border">
                      <StatItem icon={Send} label={__('Total SMS', 'wp-sms')} value={totalSms} highlight />
                    </div>
                  </div>

                  {/* Recipient Breakdown */}
                  {(groups.length > 0 || roles.length > 0 || users.length > 0 || numbers.length > 0) && (
                    <div className="wsms-space-y-2">
                      <p className="wsms-text-[12px] wsms-font-medium wsms-text-muted-foreground wsms-uppercase">
                        {__('Sending to', 'wp-sms')}
                      </p>
                      <div className="wsms-flex wsms-flex-wrap wsms-gap-2">
                        {groups.length > 0 && (
                          <span className="wsms-px-2 wsms-py-1 wsms-rounded-md wsms-bg-muted wsms-text-[12px] wsms-text-foreground">
                            {sprintf(_n('%d group', '%d groups', groups.length, 'wp-sms'), groups.length)}
                          </span>
                        )}
                        {roles.length > 0 && (
                          <span className="wsms-px-2 wsms-py-1 wsms-rounded-md wsms-bg-muted wsms-text-[12px] wsms-text-foreground">
                            {sprintf(_n('%d role', '%d roles', roles.length, 'wp-sms'), roles.length)}
                          </span>
                        )}
                        {users.length > 0 && (
                          <span className="wsms-px-2 wsms-py-1 wsms-rounded-md wsms-bg-muted wsms-text-[12px] wsms-text-foreground">
                            {sprintf(_n('%d user', '%d users', users.length, 'wp-sms'), users.length)}
                          </span>
                        )}
                        {numbers.length > 0 && (
                          <span className="wsms-px-2 wsms-py-1 wsms-rounded-md wsms-bg-muted wsms-text-[12px] wsms-text-foreground">
                            {sprintf(_n('%d number', '%d numbers', numbers.length, 'wp-sms'), numbers.length)}
                          </span>
                        )}
                      </div>
                    </div>
                  )}

                  {/* Warnings */}
                  {(smsInfo.isUnicode || isFlash) && (
                    <div className="wsms-space-y-2">
                      {smsInfo.isUnicode && (
                        <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-p-2 wsms-rounded-md wsms-bg-amber-500/10 wsms-text-[12px] wsms-text-amber-700 dark:wsms-text-amber-400">
                          <AlertTriangle className="wsms-h-3.5 wsms-w-3.5 wsms-shrink-0" />
                          {__('Unicode encoding - reduced character limit', 'wp-sms')}
                        </div>
                      )}
                      {isFlash && (
                        <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-p-2 wsms-rounded-md wsms-bg-amber-500/10 wsms-text-[12px] wsms-text-amber-700 dark:wsms-text-amber-400">
                          <Zap className="wsms-h-3.5 wsms-w-3.5 wsms-shrink-0" />
                          {__('Flash SMS - displays directly on screen', 'wp-sms')}
                        </div>
                      )}
                    </div>
                  )}
                </div>
              </div>
            </DialogBody>

            <DialogFooter>
              <Button
                variant="outline"
                onClick={() => onOpenChange(false)}
                disabled={isSending}
                size="sm"
              >
                {__('Cancel', 'wp-sms')}
              </Button>
              <Button onClick={onConfirm} disabled={isSending} size="sm">
                {isSending ? (
                  <>
                    <Loader2 className="wsms-h-4 wsms-w-4 wsms-me-1.5 wsms-animate-spin" />
                    {__('Sending...', 'wp-sms')}
                  </>
                ) : (
                  <>
                    <Check className="wsms-h-4 wsms-w-4 wsms-me-1.5" />
                    {__('Send Message', 'wp-sms')}
                  </>
                )}
              </Button>
            </DialogFooter>
          </>
        )}
      </DialogContent>
    </Dialog>
  )
}
