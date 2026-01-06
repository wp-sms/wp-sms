import * as React from 'react'
import { Send, Users, MessageSquare, Hash, Zap, Image, Check, AlertTriangle, Loader2 } from 'lucide-react'
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
            <span className="wsms-text-[9px] wsms-font-medium wsms-text-slate-600 dark:wsms-text-slate-300">
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
                <p className="wsms-text-[11px] wsms-font-medium wsms-text-foreground wsms-truncate">
                  {senderId || 'SMS'}
                </p>
              </div>
              {isFlash && (
                <span className="wsms-px-1.5 wsms-py-0.5 wsms-rounded wsms-text-[8px] wsms-font-medium wsms-bg-amber-100 wsms-text-amber-700">
                  Flash
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
                    <span className="wsms-text-[9px] wsms-text-slate-500">Media</span>
                  </div>
                )}
                <p className="wsms-text-[11px] wsms-text-foreground wsms-leading-relaxed wsms-whitespace-pre-wrap wsms-break-words">
                  {message || 'Your message...'}
                </p>
                <p className="wsms-text-[8px] wsms-text-muted-foreground wsms-text-right wsms-mt-1">
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
      <span className="wsms-text-[12px] wsms-text-muted-foreground">{label}:</span>
      <span className={cn('wsms-text-[13px] wsms-font-semibold', highlight ? 'wsms-text-primary' : 'wsms-text-foreground')}>
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
  isSending,
}) {
  const totalSms = recipientCount * smsInfo.segments
  const { groups = [], roles = [], users = [], numbers = [] } = recipients

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent size="lg" showClose={!isSending}>
        <DialogHeader>
          <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Send className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            Review & Send
          </DialogTitle>
          <DialogDescription>
            Review your message before sending.
          </DialogDescription>
        </DialogHeader>

        <DialogBody className="wsms-p-0">
          <div className="wsms-grid md:wsms-grid-cols-2 wsms-gap-0">
            {/* Left: Phone Preview */}
            <div className="wsms-p-5 wsms-bg-muted/30 wsms-flex wsms-items-center wsms-justify-center wsms-border-r wsms-border-border">
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
                <StatItem icon={Users} label="Recipients" value={recipientCount} />
                <StatItem icon={MessageSquare} label="Segments" value={smsInfo.segments} />
                <StatItem icon={Hash} label="Characters" value={smsInfo.characters || smsInfo.length} />
                <div className="wsms-pt-2 wsms-border-t wsms-border-border">
                  <StatItem icon={Send} label="Total SMS" value={totalSms} highlight />
                </div>
              </div>

              {/* Recipient Breakdown */}
              {(groups.length > 0 || roles.length > 0 || users.length > 0 || numbers.length > 0) && (
                <div className="wsms-space-y-2">
                  <p className="wsms-text-[11px] wsms-font-medium wsms-text-muted-foreground wsms-uppercase">
                    Sending to
                  </p>
                  <div className="wsms-flex wsms-flex-wrap wsms-gap-2">
                    {groups.length > 0 && (
                      <span className="wsms-px-2 wsms-py-1 wsms-rounded-md wsms-bg-muted wsms-text-[11px] wsms-text-foreground">
                        {groups.length} group{groups.length !== 1 ? 's' : ''}
                      </span>
                    )}
                    {roles.length > 0 && (
                      <span className="wsms-px-2 wsms-py-1 wsms-rounded-md wsms-bg-muted wsms-text-[11px] wsms-text-foreground">
                        {roles.length} role{roles.length !== 1 ? 's' : ''}
                      </span>
                    )}
                    {users.length > 0 && (
                      <span className="wsms-px-2 wsms-py-1 wsms-rounded-md wsms-bg-muted wsms-text-[11px] wsms-text-foreground">
                        {users.length} user{users.length !== 1 ? 's' : ''}
                      </span>
                    )}
                    {numbers.length > 0 && (
                      <span className="wsms-px-2 wsms-py-1 wsms-rounded-md wsms-bg-muted wsms-text-[11px] wsms-text-foreground">
                        {numbers.length} number{numbers.length !== 1 ? 's' : ''}
                      </span>
                    )}
                  </div>
                </div>
              )}

              {/* Warnings */}
              {(smsInfo.isUnicode || isFlash) && (
                <div className="wsms-space-y-2">
                  {smsInfo.isUnicode && (
                    <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-p-2 wsms-rounded-md wsms-bg-amber-500/10 wsms-text-[11px] wsms-text-amber-700 dark:wsms-text-amber-400">
                      <AlertTriangle className="wsms-h-3.5 wsms-w-3.5 wsms-shrink-0" />
                      Unicode encoding - reduced character limit
                    </div>
                  )}
                  {isFlash && (
                    <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-p-2 wsms-rounded-md wsms-bg-amber-500/10 wsms-text-[11px] wsms-text-amber-700 dark:wsms-text-amber-400">
                      <Zap className="wsms-h-3.5 wsms-w-3.5 wsms-shrink-0" />
                      Flash SMS - displays directly on screen
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
            Cancel
          </Button>
          <Button onClick={onConfirm} disabled={isSending} size="sm">
            {isSending ? (
              <>
                <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-1.5 wsms-animate-spin" />
                Sending...
              </>
            ) : (
              <>
                <Check className="wsms-h-4 wsms-w-4 wsms-mr-1.5" />
                Send Message
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
