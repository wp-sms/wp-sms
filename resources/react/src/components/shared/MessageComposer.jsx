import * as React from 'react'
import { MessageSquare, Hash, AlertTriangle } from 'lucide-react'
import { cn, __ } from '@/lib/utils'
import { Textarea } from '@/components/ui/textarea'

// SMS character limits
const GSM_SINGLE_LIMIT = 160
const GSM_MULTI_LIMIT = 153
const UNICODE_SINGLE_LIMIT = 70
const UNICODE_MULTI_LIMIT = 67

// GSM 7-bit character set (basic)
const GSM_CHARS = '@£$¥èéùìòÇ\nØø\rÅåΔ_ΦΓΛΩΠΨΣΘΞ !"#¤%&\'()*+,-./0123456789:;<=>?¡ABCDEFGHIJKLMNOPQRSTUVWXYZÄÖÑÜ§¿abcdefghijklmnopqrstuvwxyzäöñüà'
const GSM_EXTENDED = '^{}\\[~]|€'

/**
 * Check if text contains only GSM characters
 */
function isGsmText(text) {
  for (const char of text) {
    if (!GSM_CHARS.includes(char) && !GSM_EXTENDED.includes(char)) {
      return false
    }
  }
  return true
}

/**
 * Calculate SMS segments and character info
 */
function calculateSmsInfo(text) {
  if (!text) {
    return {
      characters: 0,
      segments: 0,
      remaining: GSM_SINGLE_LIMIT,
      encoding: __('Standard'),
      isUnicode: false,
      limit: GSM_SINGLE_LIMIT,
    }
  }

  const isUnicode = !isGsmText(text)
  let charCount = text.length

  // Count extended GSM characters as 2
  if (!isUnicode) {
    for (const char of text) {
      if (GSM_EXTENDED.includes(char)) {
        charCount++
      }
    }
  }

  const singleLimit = isUnicode ? UNICODE_SINGLE_LIMIT : GSM_SINGLE_LIMIT
  const multiLimit = isUnicode ? UNICODE_MULTI_LIMIT : GSM_MULTI_LIMIT

  let segments = 1
  let remaining = singleLimit - charCount

  if (charCount > singleLimit) {
    segments = Math.ceil(charCount / multiLimit)
    const usedInLastSegment = charCount % multiLimit || multiLimit
    remaining = multiLimit - usedInLastSegment
  }

  return {
    characters: charCount,
    segments,
    remaining,
    encoding: isUnicode ? __('Unicode') : __('Standard'),
    isUnicode,
    limit: charCount <= singleLimit ? singleLimit : multiLimit,
  }
}

/**
 * MessageComposer - SMS text editor with character and segment counter
 */
const MessageComposer = React.forwardRef(
  (
    {
      className,
      value = '',
      onChange,
      placeholder = 'Type your message here...',
      disabled = false,
      maxSegments = 10,
      showWarning = true,
      rows = 5,
      ...props
    },
    ref
  ) => {
    const smsInfo = calculateSmsInfo(value)
    const isOverLimit = smsInfo.segments > maxSegments
    const showUnicodeWarning = showWarning && smsInfo.isUnicode && value.length > 0

    return (
      <div className={cn('wsms-space-y-2', className)} {...props}>
        <div className="wsms-relative">
          <Textarea
            ref={ref}
            value={value}
            onChange={(e) => onChange?.(e.target.value)}
            placeholder={placeholder}
            disabled={disabled}
            rows={rows}
            className={cn(
              'wsms-resize-none wsms-pr-4 wsms-pb-10',
              isOverLimit && 'wsms-border-red-500 focus:wsms-ring-red-500/20'
            )}
          />

          {/* Character counter overlay */}
          <div className="wsms-absolute wsms-bottom-2 wsms-left-3 wsms-right-3 wsms-flex wsms-items-center wsms-justify-between wsms-text-[11px] wsms-text-muted-foreground wsms-pointer-events-none">
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <span className="wsms-flex wsms-items-center wsms-gap-1">
                <Hash className="wsms-h-3 wsms-w-3" />
                {smsInfo.characters}
              </span>
              <span className="wsms-flex wsms-items-center wsms-gap-1">
                <MessageSquare className="wsms-h-3 wsms-w-3" />
                {smsInfo.segments} {smsInfo.segments === 1 ? __('segment') : __('segments')}
              </span>
            </div>
            <span className={cn(
              'wsms-px-1.5 wsms-py-0.5 wsms-rounded wsms-text-[10px] wsms-font-medium',
              smsInfo.isUnicode
                ? 'wsms-bg-amber-100 wsms-text-amber-800 dark:wsms-bg-amber-900/40 dark:wsms-text-amber-200'
                : 'wsms-bg-emerald-100 wsms-text-emerald-800 dark:wsms-bg-emerald-900/40 dark:wsms-text-emerald-200'
            )}>
              {smsInfo.encoding}
            </span>
          </div>
        </div>

        {/* Warning messages */}
        {showUnicodeWarning && (
          <div className="wsms-flex wsms-items-start wsms-gap-2 wsms-p-2.5 wsms-rounded-md wsms-bg-amber-50 wsms-border wsms-border-amber-200 dark:wsms-bg-amber-900/30 dark:wsms-border-amber-800">
            <AlertTriangle className="wsms-h-4 wsms-w-4 wsms-text-amber-700 dark:wsms-text-amber-300 wsms-shrink-0 wsms-mt-0.5" aria-hidden="true" />
            <p className="wsms-text-[12px] wsms-text-amber-800 dark:wsms-text-amber-200">
              {__('Your message contains Unicode characters. This reduces the character limit per segment from 160 to 70 characters.')}
            </p>
          </div>
        )}

        {isOverLimit && (
          <div className="wsms-flex wsms-items-start wsms-gap-2 wsms-p-2.5 wsms-rounded-md wsms-bg-red-50 wsms-border wsms-border-red-200 dark:wsms-bg-red-900/30 dark:wsms-border-red-800" role="alert">
            <AlertTriangle className="wsms-h-4 wsms-w-4 wsms-text-red-700 dark:wsms-text-red-300 wsms-shrink-0 wsms-mt-0.5" aria-hidden="true" />
            <p className="wsms-text-[12px] wsms-text-red-800 dark:wsms-text-red-200">
              {__('Message exceeds maximum of %s segments. Please shorten your message.').replace('%s', maxSegments)}
            </p>
          </div>
        )}

        {/* Remaining characters hint */}
        <p className="wsms-text-[11px] wsms-text-muted-foreground">
          {__('%s characters remaining in current segment').replace('%s', smsInfo.remaining)}
        </p>
      </div>
    )
  }
)
MessageComposer.displayName = 'MessageComposer'

export { MessageComposer, calculateSmsInfo }
