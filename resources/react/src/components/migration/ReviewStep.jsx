import React, { useState } from 'react'
import {
  ArrowRight,
  ChevronDown,
  ChevronRight,
  Loader2,
  Info,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { __, sprintf, _n } from '@/lib/utils'

export default function ReviewStep({
  headlineRef,
  scanData,
  loading,
  onNext,
  onBack,
  onClose,
  onRevertOldBackup,
  onClearOldBackup,
  onWrongCountry,
}) {
  const [showSources, setShowSources] = useState(true)
  const [showSamples, setShowSamples] = useState(false)

  const {
    total_records: totalRecords = 0,
    total_need_fix: totalNeedFix = 0,
    total_already_intl: totalAlreadyIntl = 0,
    sources = {},
    samples = [],
    backup_exists: backupExists = false,
    backup_timestamp: backupTimestamp = null,
    new_sources_since_last: newSourcesSinceLast = [],
    last_run_had_errors: lastRunHadErrors = false,
    country_code: countryCode = '',
  } = scanData || {}

  const isEmpty = totalRecords === 0
  const isAlreadyMigrated = !isEmpty && totalNeedFix === 0
  const isVeryLarge = totalNeedFix > 10000

  let headline = ''
  let sub = ''
  if (isEmpty) {
    headline = __('Nothing to check yet')
    sub = __(
      "Looks like you don't have any stored phone numbers yet. Come back here once you have subscribers or user mobile data."
    )
  } else if (isAlreadyMigrated) {
    headline = __('Your numbers are already in good shape.')
    sub = sprintf(
      __('We checked %d records and they all use the international format. No action needed.'),
      totalRecords
    )
  } else {
    headline = sprintf(__('We checked %d records'), totalRecords)
    sub = sprintf(
      __('%d numbers need the country code. Everything else is already in good shape.'),
      totalNeedFix
    )
  }

  return (
    <div className="wsms-space-y-5">
      <div>
        <h2
          ref={headlineRef}
          tabIndex={-1}
          aria-live="polite"
          className="wsms-text-[18px] wsms-font-semibold wsms-text-foreground wsms-mb-2 wsms-outline-none"
        >
          {headline}
        </h2>
        <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-leading-relaxed">{sub}</p>
      </div>

      {backupExists && (
        <Alert variant={lastRunHadErrors ? 'destructive' : 'warning'}>
          <AlertDescription>
            <div className="wsms-flex wsms-flex-col wsms-gap-2">
              <span>
                {lastRunHadErrors
                  ? __(
                      "Your previous update had errors. Some records weren't updated. You can re-run to retry, revert to roll back, or clear the old backup if you've handled it manually."
                    )
                  : sprintf(
                      __('A backup from %s exists. Applying new changes will replace it.'),
                      backupTimestamp || __('a previous run')
                    )}
              </span>
              <div className="wsms-flex wsms-flex-wrap wsms-gap-2">
                <button
                  type="button"
                  onClick={onRevertOldBackup}
                  className="wsms-text-[12px] wsms-font-medium wsms-underline wsms-text-inherit"
                >
                  {__('Revert the old update first?')}
                </button>
                <span className="wsms-text-[12px] wsms-text-inherit/60">•</span>
                <button
                  type="button"
                  onClick={onClearOldBackup}
                  className="wsms-text-[12px] wsms-font-medium wsms-underline wsms-text-inherit"
                >
                  {__('Clear old backup')}
                </button>
              </div>
            </div>
          </AlertDescription>
        </Alert>
      )}

      {newSourcesSinceLast.length > 0 && (
        <Alert variant="info">
          <AlertDescription>
            {sprintf(
              _n(
                "We're checking %d new data source since your last update.",
                "We're checking %d new data sources since your last update.",
                newSourcesSinceLast.length
              ),
              newSourcesSinceLast.length
            )}
          </AlertDescription>
        </Alert>
      )}

      {isVeryLarge && (
        <Alert variant="warning">
          <AlertDescription>
            {__(
              "This is a large update. It may take 1-2 minutes to apply. Please don't close the tab while it's running."
            )}
          </AlertDescription>
        </Alert>
      )}

      {!isEmpty && (
        <>
          <div className="wsms-grid wsms-grid-cols-3 wsms-gap-3">
            <StatTile value={totalRecords} label={__('Total reviewed')} tone="default" />
            <StatTile value={totalNeedFix} label={__('Need update')} tone="warning" />
            <StatTile value={totalAlreadyIntl} label={__('Already correct')} tone="success" />
          </div>

          {!isAlreadyMigrated && (
            <div className="wsms-border wsms-rounded-lg wsms-overflow-hidden">
              <button
                type="button"
                onClick={() => setShowSources((v) => !v)}
                className="wsms-w-full wsms-flex wsms-items-center wsms-justify-between wsms-px-4 wsms-py-2.5 wsms-text-[13px] wsms-font-medium wsms-bg-muted/40 hover:wsms-bg-muted/60"
                aria-expanded={showSources}
              >
                <span>{__('View by source')}</span>
                {showSources ? (
                  <ChevronDown className="wsms-h-4 wsms-w-4" aria-hidden="true" />
                ) : (
                  <ChevronRight className="wsms-h-4 wsms-w-4 wsms-rtl:wsms-rotate-180" aria-hidden="true" />
                )}
              </button>
              {showSources && (
                <ul className="wsms-divide-y">
                  {Object.entries(sources).map(([key, source]) => {
                    if (!source.total) return null
                    return (
                      <li
                        key={key}
                        className="wsms-flex wsms-items-center wsms-justify-between wsms-px-4 wsms-py-2.5 wsms-text-[13px]"
                      >
                        <span className="wsms-font-medium wsms-text-foreground">{source.label}</span>
                        <span className="wsms-flex wsms-items-center wsms-gap-3 wsms-text-[12px]">
                          <span className="wsms-text-warning">
                            {sprintf(__('%d need update'), source.need_fix)}
                          </span>
                          <span className="wsms-text-muted-foreground">•</span>
                          <span className="wsms-text-success">
                            {sprintf(__('%d already OK'), source.already_intl)}
                          </span>
                        </span>
                      </li>
                    )
                  })}
                </ul>
              )}
            </div>
          )}

          {!isAlreadyMigrated && samples.length > 0 && (
            <div className="wsms-border wsms-rounded-lg wsms-overflow-hidden">
              <button
                type="button"
                onClick={() => setShowSamples((v) => !v)}
                className="wsms-w-full wsms-flex wsms-items-center wsms-justify-between wsms-px-4 wsms-py-2.5 wsms-text-[13px] wsms-font-medium wsms-bg-muted/40 hover:wsms-bg-muted/60"
                aria-expanded={showSamples}
              >
                <span>{__('What kind of numbers need fixing?')}</span>
                {showSamples ? (
                  <ChevronDown className="wsms-h-4 wsms-w-4" aria-hidden="true" />
                ) : (
                  <ChevronRight className="wsms-h-4 wsms-w-4 wsms-rtl:wsms-rotate-180" aria-hidden="true" />
                )}
              </button>
              {showSamples && (
                <div className="wsms-px-4 wsms-py-3 wsms-text-[12px] wsms-text-muted-foreground wsms-space-y-1">
                  {samples.map((sample, i) => (
                    <div key={i} className="wsms-font-mono">
                      <bdi>{sample}</bdi>
                    </div>
                  ))}
                </div>
              )}
            </div>
          )}

          {!isAlreadyMigrated && countryCode && (
            <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-text-[12px] wsms-text-muted-foreground">
              <Info className="wsms-h-3.5 wsms-w-3.5" aria-hidden="true" />
              <span>
                {sprintf(__('Using country code %s.'), countryCode)}{' '}
                <button
                  type="button"
                  onClick={onWrongCountry}
                  className="wsms-underline wsms-text-primary"
                >
                  {__('Wrong country?')}
                </button>
              </span>
            </div>
          )}
        </>
      )}

      <div className="wsms-flex wsms-items-center wsms-justify-end wsms-gap-2 wsms-pt-2">
        <Button variant="outline" onClick={onBack} disabled={loading}>
          {__('Back')}
        </Button>
        {!isEmpty && !isAlreadyMigrated ? (
          <Button onClick={onNext} disabled={loading}>
            {loading ? (
              <>
                <Loader2 className="wsms-h-4 wsms-w-4 wsms-me-1.5 wsms-animate-spin" aria-hidden="true" />
                {__('Loading...')}
              </>
            ) : (
              <>
                {__('Preview changes')}
                <ArrowRight className="wsms-h-4 wsms-w-4 wsms-ms-1.5 wsms-rtl:wsms-rotate-180" aria-hidden="true" />
              </>
            )}
          </Button>
        ) : (
          <Button onClick={onClose} disabled={loading}>
            {__('Close')}
          </Button>
        )}
      </div>
    </div>
  )
}

function StatTile({ value, label, tone = 'default' }) {
  const toneClasses = {
    default: 'wsms-bg-muted/40 wsms-text-foreground',
    warning: 'wsms-bg-orange-50 wsms-text-orange-700',
    success: 'wsms-bg-green-50 wsms-text-green-700',
  }
  return (
    <div className={`wsms-rounded-lg wsms-p-4 wsms-text-center ${toneClasses[tone]}`}>
      <div className="wsms-text-[24px] wsms-font-semibold wsms-leading-none">{value}</div>
      <div className="wsms-text-[12px] wsms-mt-1.5 wsms-opacity-80">{label}</div>
    </div>
  )
}
