import React from 'react'
import { Shield, Eye, Undo2, Search, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { __ } from '@/lib/utils'

export default function IntroStep({ headlineRef, loading, onStart, onCancel }) {
  return (
    <div className="wsms-space-y-5">
      <div>
        <h2
          ref={headlineRef}
          tabIndex={-1}
          aria-live="polite"
          className="wsms-text-[18px] wsms-font-semibold wsms-text-foreground wsms-mb-2 wsms-outline-none"
        >
          {__("Let's get your numbers ready for reliable delivery.")}
        </h2>
        <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-leading-relaxed">
          {__(
            "Some of your stored numbers are in local format (like 202 555 0147) instead of the international format your SMS gateway needs (+1 202 555 0147). We'll find them, show you exactly what will change, and apply the fix in one batch."
          )}
        </p>
      </div>

      <div className="wsms-grid wsms-grid-cols-1 sm:wsms-grid-cols-3 wsms-gap-3">
        <Card>
          <CardContent className="wsms-flex wsms-items-start wsms-gap-3 wsms-py-3">
            <Shield className="wsms-h-5 wsms-w-5 wsms-text-primary wsms-shrink-0 wsms-mt-0.5" aria-hidden="true" />
            <span className="wsms-text-[13px] wsms-text-foreground">
              {__('We back everything up')}
            </span>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="wsms-flex wsms-items-start wsms-gap-3 wsms-py-3">
            <Eye className="wsms-h-5 wsms-w-5 wsms-text-primary wsms-shrink-0 wsms-mt-0.5" aria-hidden="true" />
            <span className="wsms-text-[13px] wsms-text-foreground">
              {__('You review before we change anything')}
            </span>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="wsms-flex wsms-items-start wsms-gap-3 wsms-py-3">
            <Undo2 className="wsms-h-5 wsms-w-5 wsms-text-primary wsms-shrink-0 wsms-mt-0.5" aria-hidden="true" />
            <span className="wsms-text-[13px] wsms-text-foreground">
              {__('One-click undo, anytime')}
            </span>
          </CardContent>
        </Card>
      </div>

      <div className="wsms-flex wsms-items-center wsms-justify-end wsms-gap-2 wsms-pt-2">
        <Button variant="outline" onClick={onCancel} disabled={loading}>
          {__('Not now')}
        </Button>
        <Button onClick={onStart} disabled={loading}>
          {loading ? (
            <>
              <Loader2 className="wsms-h-4 wsms-w-4 wsms-me-1.5 wsms-animate-spin" aria-hidden="true" />
              {__('Checking...')}
            </>
          ) : (
            <>
              <Search className="wsms-h-4 wsms-w-4 wsms-me-1.5" aria-hidden="true" />
              {__('Start check')}
            </>
          )}
        </Button>
      </div>
    </div>
  )
}
