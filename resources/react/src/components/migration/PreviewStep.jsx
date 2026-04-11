import { __, sprintf } from '@wordpress/i18n'
import React, { useMemo, useState } from 'react'
import { ArrowRight, Loader2, Play } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'

export default function PreviewStep({
  headlineRef,
  previewData,
  previewPage,
  loading,
  onApply,
  onBack,
  onPageChange,
  onWrongCountry,
}) {
  const [acknowledged, setAcknowledged] = useState(false)

  // Group sequential rows by source label without resorting — preserves the
  // backend's natural ordering.
  const groups = useMemo(() => {
    if (!previewData?.preview) return []
    const out = []
    let current = null
    for (const row of previewData.preview) {
      if (!current || current.label !== row.label) {
        current = { label: row.label, rows: [] }
        out.push(current)
      }
      current.rows.push(row)
    }
    return out
  }, [previewData])

  const countryCodeDisplay = previewData?.country_code || ''
  const countryCodeClean = String(countryCodeDisplay).replace(/^\+?/, '')
  const bodyCopy = sprintf(
    __(
      "We'll add +%s to numbers that are missing it, and normalize a few trunk-prefix variations. Numbers that are already in international format aren't touched."
    , 'wp-sms'),
    countryCodeClean
  )

  const hasNextPage =
    previewData && previewPage * (previewData.per_page || 20) < (previewData.total || 0)
  const hasPrevPage = previewPage > 1

  return (
    <div className="wsms-space-y-5">
      <div>
        <h2
          ref={headlineRef}
          tabIndex={-1}
          aria-live="polite"
          className="wsms-text-[18px] wsms-font-semibold wsms-text-foreground wsms-mb-2 wsms-outline-none"
        >
          {__('Review the changes', 'wp-sms')}
        </h2>
        <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-leading-relaxed">
          {bodyCopy}{' '}
          <button
            type="button"
            onClick={onWrongCountry}
            className="wsms-underline wsms-text-primary"
          >
            {__('Wrong country?', 'wp-sms')}
          </button>
        </p>
      </div>

      <div className="wsms-border wsms-rounded-lg wsms-overflow-hidden wsms-overflow-x-auto">
        <table className="wsms-w-full wsms-text-[13px]">
          <thead>
            <tr className="wsms-bg-muted/50">
              <th className="wsms-text-start wsms-px-3 wsms-py-2 wsms-font-medium">{__('Name', 'wp-sms')}</th>
              <th className="wsms-text-start wsms-px-3 wsms-py-2 wsms-font-medium">{__('Before', 'wp-sms')}</th>
              <th className="wsms-text-center wsms-px-1 wsms-py-2" />
              <th className="wsms-text-start wsms-px-3 wsms-py-2 wsms-font-medium">{__('After', 'wp-sms')}</th>
            </tr>
          </thead>
          <tbody>
            {groups.map((group) => (
              <React.Fragment key={group.label}>
                <tr className="wsms-bg-muted/30 wsms-border-t">
                  <td
                    colSpan={4}
                    className="wsms-px-3 wsms-py-1.5 wsms-text-[12px] wsms-font-semibold wsms-text-foreground/80 wsms-sticky wsms-top-0"
                  >
                    {sprintf(__('%1$s — %2$d changes', 'wp-sms'), group.label, group.rows.length)}
                  </td>
                </tr>
                {group.rows.map((item, index) => (
                  <tr key={`${item.source}-${item.id}-${index}`} className="wsms-border-t">
                    <td className="wsms-px-3 wsms-py-2 wsms-text-muted-foreground">
                      {item.name || '—'}
                    </td>
                    <td className="wsms-px-3 wsms-py-2">
                      <code className="wsms-font-mono wsms-text-orange-600 wsms-text-[12px] wsms-break-all">
                        <bdi>{item.original}</bdi>
                      </code>
                    </td>
                    <td className="wsms-px-1 wsms-py-2 wsms-text-center">
                      <ArrowRight
                        className="wsms-h-3.5 wsms-w-3.5 wsms-text-muted-foreground wsms-inline wsms-rtl:wsms-rotate-180"
                        aria-hidden="true"
                      />
                    </td>
                    <td className="wsms-px-3 wsms-py-2">
                      <code className="wsms-font-mono wsms-text-green-600 wsms-text-[12px] wsms-break-all">
                        <bdi>{item.migrated}</bdi>
                      </code>
                    </td>
                  </tr>
                ))}
              </React.Fragment>
            ))}
            {groups.length === 0 && (
              <tr>
                <td colSpan={4} className="wsms-px-3 wsms-py-6 wsms-text-center wsms-text-muted-foreground">
                  {__('No numbers to preview on this page.', 'wp-sms')}
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>

      {previewData && (previewData.total || 0) > (previewData.per_page || 20) && (
        <div className="wsms-flex wsms-items-center wsms-justify-between wsms-text-[13px]">
          <span className="wsms-text-muted-foreground">
            {sprintf(__('Page %d', 'wp-sms'), previewPage)}
          </span>
          <div className="wsms-flex wsms-gap-2">
            <Button
              variant="outline"
              size="sm"
              disabled={!hasPrevPage || loading}
              onClick={() => onPageChange(previewPage - 1)}
            >
              {__('Previous', 'wp-sms')}
            </Button>
            <Button
              variant="outline"
              size="sm"
              disabled={!hasNextPage || loading}
              onClick={() => onPageChange(previewPage + 1)}
            >
              {__('Next', 'wp-sms')}
            </Button>
          </div>
        </div>
      )}

      <div className="wsms-rounded-lg wsms-border wsms-border-border wsms-bg-muted/30 wsms-p-4">
        <label className="wsms-flex wsms-items-start wsms-gap-3 wsms-cursor-pointer">
          <Checkbox
            checked={acknowledged}
            onCheckedChange={(v) => setAcknowledged(Boolean(v))}
            disabled={loading}
            aria-describedby="wpsms-migration-confirm-sub"
          />
          <span className="wsms-flex wsms-flex-col wsms-gap-1">
            <span className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">
              {__("I've reviewed the changes above and I'm ready to apply them.", 'wp-sms')}
            </span>
            <span
              id="wpsms-migration-confirm-sub"
              className="wsms-text-[12px] wsms-text-muted-foreground"
            >
              {__('A backup will be saved before any change is made.', 'wp-sms')}
            </span>
          </span>
        </label>
      </div>

      <div className="wsms-flex wsms-items-center wsms-justify-end wsms-gap-2 wsms-pt-2">
        <Button variant="outline" onClick={onBack} disabled={loading}>
          {__('Back', 'wp-sms')}
        </Button>
        <Button onClick={onApply} disabled={loading || !acknowledged}>
          {loading ? (
            <>
              <Loader2 className="wsms-h-4 wsms-w-4 wsms-me-1.5 wsms-animate-spin" aria-hidden="true" />
              {__('Starting...', 'wp-sms')}
            </>
          ) : (
            <>
              <Play className="wsms-h-4 wsms-w-4 wsms-me-1.5" aria-hidden="true" />
              {__('Apply changes', 'wp-sms')}
            </>
          )}
        </Button>
      </div>
    </div>
  )
}

