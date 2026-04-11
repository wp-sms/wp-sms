import { __ } from '@wordpress/i18n'
import React, { useEffect, useState } from 'react'
import { Loader2 } from 'lucide-react'
import ProgressBar from './ProgressBar'

/**
 * In-flight Execute screen. Displays slow-path hints after 30s ("larger than usual")
 * and 5min ("seems to be stuck") with a force-refresh recovery affordance.
 */
export default function ExecuteStep({ headlineRef, estimatedMs, isDone, onForceRefresh }) {
  const [slowHint, setSlowHint] = useState(false)
  const [stuckHint, setStuckHint] = useState(false)

  useEffect(() => {
    if (isDone) return
    const slowTimer = setTimeout(() => setSlowHint(true), 30_000)
    const stuckTimer = setTimeout(() => setStuckHint(true), 5 * 60_000)
    return () => {
      clearTimeout(slowTimer)
      clearTimeout(stuckTimer)
    }
  }, [isDone])

  return (
    <div className="wsms-space-y-5 wsms-py-4">
      <div className="wsms-text-center">
        <Loader2
          className="wsms-h-10 wsms-w-10 wsms-text-primary wsms-mx-auto wsms-mb-4 wsms-animate-spin"
          aria-hidden="true"
        />
        <h2
          ref={headlineRef}
          tabIndex={-1}
          aria-live="polite"
          className="wsms-text-[18px] wsms-font-semibold wsms-text-foreground wsms-mb-2 wsms-outline-none"
        >
          {__('Applying your changes…', 'wp-sms')}
        </h2>
        <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-leading-relaxed wsms-max-w-md wsms-mx-auto">
          {__(
            "Processing in batches. This usually takes a few seconds. You can safely leave this tab open — we'll keep going."
          , 'wp-sms')}
        </p>
      </div>

      <div className="wsms-max-w-md wsms-mx-auto wsms-space-y-2">
        <ProgressBar estimatedMs={estimatedMs} isDone={isDone} />
        <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-text-center">
          {__("Please don't close this tab until it's done.", 'wp-sms')}
        </p>
      </div>

      {slowHint && !stuckHint && (
        <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-text-center">
          {__(
            "This is a larger update than usual. Please don't close the tab — we'll keep working."
          , 'wp-sms')}
        </p>
      )}

      {stuckHint && (
        <div className="wsms-max-w-md wsms-mx-auto wsms-text-center wsms-space-y-2">
          <p className="wsms-text-[13px] wsms-text-destructive">
            {__(
              'Something seems to be stuck. The update may still be running on the server.'
            , 'wp-sms')}
          </p>
          <button
            type="button"
            onClick={onForceRefresh}
            className="wsms-text-[12px] wsms-underline wsms-text-primary"
          >
            {__('Force refresh status', 'wp-sms')}
          </button>
        </div>
      )}
    </div>
  )
}
