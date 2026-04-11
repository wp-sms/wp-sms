import { __, sprintf } from '@wordpress/i18n'
import React, { useState } from 'react'
import { CheckCircle2, Send, Users, Undo2, ChevronDown, ChevronRight } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Alert, AlertDescription } from '@/components/ui/alert'

export default function DoneStep({
  headlineRef,
  executeData,
  onClose,
  onRequestRevert,
  onNavigate,
  onRescan,
}) {
  const [showErrors, setShowErrors] = useState(false)

  const {
    total_migrated: totalMigrated = 0,
    sources_touched: sourcesTouched = 0,
    counts = {},
    errors = [],
    backup_timestamp: backupTimestamp = __('just now', 'wp-sms'),
  } = executeData || {}

  const body = sprintf(
    __(
      'We updated %1$d numbers across %2$d sources. A full backup was saved on %3$s and you can undo this at any time.'
    , 'wp-sms'),
    totalMigrated,
    sourcesTouched,
    backupTimestamp
  )

  const copyErrorLog = () => {
    const text = errors.join('\n')
    if (navigator?.clipboard?.writeText) {
      navigator.clipboard.writeText(text).catch(() => {
        /* the toggled list is still visible if writeText fails */
      })
    }
  }

  return (
    <div className="wsms-space-y-5">
      <div className="wsms-text-center wsms-py-2">
        <CheckCircle2
          className="wsms-h-14 wsms-w-14 wsms-text-green-500 wsms-mx-auto wsms-mb-3"
          aria-hidden="true"
        />
        <h2
          ref={headlineRef}
          tabIndex={-1}
          aria-live="polite"
          className="wsms-text-[18px] wsms-font-semibold wsms-text-foreground wsms-mb-2 wsms-outline-none"
        >
          {__('Done. Your numbers now use the standard format.', 'wp-sms')}
        </h2>
        <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-leading-relaxed wsms-max-w-lg wsms-mx-auto">
          {body}
        </p>
      </div>

      {Object.keys(counts).length > 0 && (
        <div className="wsms-flex wsms-flex-wrap wsms-gap-2 wsms-justify-center">
          {Object.entries(counts).map(([key, count]) => {
            if (!count) return null
            return (
              <span
                key={key}
                className="wsms-inline-flex wsms-items-center wsms-gap-1.5 wsms-rounded-full wsms-bg-green-50 wsms-text-green-700 wsms-px-3 wsms-py-1 wsms-text-[12px] wsms-font-medium"
              >
                <span className="wsms-font-semibold">{count}</span>
                <span className="wsms-opacity-80">{key.replace(/_/g, ' ')}</span>
              </span>
            )
          })}
        </div>
      )}

      {errors.length > 0 && (
        <Alert variant="warning">
          <AlertDescription>
            <div className="wsms-flex wsms-flex-col wsms-gap-2">
              <span>{sprintf(__("%d records couldn't be updated.", 'wp-sms'), errors.length)}</span>
              <div className="wsms-flex wsms-gap-3 wsms-text-[12px]">
                <button
                  type="button"
                  onClick={() => setShowErrors((v) => !v)}
                  className="wsms-underline wsms-text-inherit wsms-inline-flex wsms-items-center wsms-gap-1"
                >
                  {showErrors ? (
                    <ChevronDown className="wsms-h-3 wsms-w-3" aria-hidden="true" />
                  ) : (
                    <ChevronRight className="wsms-h-3 wsms-w-3 wsms-rtl:wsms-rotate-180" aria-hidden="true" />
                  )}
                  {__('View details', 'wp-sms')}
                </button>
                <button
                  type="button"
                  onClick={copyErrorLog}
                  className="wsms-underline wsms-text-inherit"
                >
                  {__('Copy error log', 'wp-sms')}
                </button>
              </div>
              {showErrors && (
                <ul className="wsms-list-disc wsms-ps-5 wsms-space-y-0.5 wsms-text-[12px] wsms-max-h-40 wsms-overflow-y-auto">
                  {errors.map((err, i) => (
                    <li key={i}>{err}</li>
                  ))}
                </ul>
              )}
            </div>
          </AlertDescription>
        </Alert>
      )}

      <Card>
        <CardContent className="wsms-py-4">
          <p className="wsms-text-[13px] wsms-font-medium wsms-text-foreground wsms-mb-3">
            {__("What's next?", 'wp-sms')}
          </p>
          <div className="wsms-flex wsms-flex-wrap wsms-gap-2">
            <Button variant="outline" size="sm" onClick={() => onNavigate?.('send-test')}>
              <Send className="wsms-h-3.5 wsms-w-3.5 wsms-me-1.5" aria-hidden="true" />
              {__('Send a test SMS', 'wp-sms')}
            </Button>
            <Button variant="outline" size="sm" onClick={() => onNavigate?.('subscribers')}>
              <Users className="wsms-h-3.5 wsms-w-3.5 wsms-me-1.5" aria-hidden="true" />
              {__('View your subscribers', 'wp-sms')}
            </Button>
          </div>
        </CardContent>
      </Card>

      <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-2 wsms-pt-2">
        <button
          type="button"
          onClick={onRequestRevert}
          className="wsms-inline-flex wsms-items-center wsms-gap-1.5 wsms-text-[12px] wsms-text-muted-foreground hover:wsms-text-foreground wsms-underline-offset-2 hover:wsms-underline"
        >
          <Undo2 className="wsms-h-3.5 wsms-w-3.5" aria-hidden="true" />
          {__('Undo this update', 'wp-sms')}
        </button>
        <div className="wsms-flex wsms-items-center wsms-gap-2">
          <button
            type="button"
            onClick={onRescan}
            className="wsms-text-[12px] wsms-text-muted-foreground hover:wsms-text-foreground wsms-underline-offset-2 hover:wsms-underline"
          >
            {__('Did we miss something? Re-scan to check', 'wp-sms')}
          </button>
          <Button onClick={onClose}>{__('Close', 'wp-sms')}</Button>
        </div>
      </div>
    </div>
  )
}

