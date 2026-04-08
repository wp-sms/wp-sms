import React, { useMemo } from 'react'
import { Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { getWpSettings, __, sprintf } from '@/lib/utils'

/**
 * Confirm-country step. Shown only when the scan returned `missing_country_code`.
 * `mode === 'international_input'` swaps the body copy to clarify that we still
 * need a CC for legacy local-format data even though international input is on.
 */
export default function CountryStep({
  headlineRef,
  mode = 'default',
  value,
  onChange,
  loading,
  onContinue,
  onBack,
}) {
  const { countriesByDialCode = {} } = getWpSettings()

  const exampleLine = useMemo(() => {
    if (!value) {
      return __('Numbers like 0912 345 6789 will become +98 912 345 6789')
    }
    const cc = String(value).replace(/^\+?/, '+')
    return sprintf(__('Numbers like 0912 345 6789 will become %s 912 345 6789'), cc)
  }, [value])

  const bodyCopy =
    mode === 'international_input'
      ? __(
          "We need a country code just for this update — to convert your existing local-format numbers. Your International Number Input setting won't change."
        )
      : __(
          "We'll use this country code to convert any number that doesn't already start with a +. You can change your plugin default later from Settings → General."
        )

  return (
    <div className="wsms-space-y-5">
      <div>
        <h2
          ref={headlineRef}
          tabIndex={-1}
          aria-live="polite"
          className="wsms-text-[18px] wsms-font-semibold wsms-text-foreground wsms-mb-2 wsms-outline-none"
        >
          {__('Confirm your default country')}
        </h2>
        <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-leading-relaxed">
          {bodyCopy}
        </p>
      </div>

      <div className="wsms-max-w-md">
        <label
          htmlFor="wpsms-migration-country"
          className="wsms-block wsms-text-[13px] wsms-font-medium wsms-text-foreground wsms-mb-1.5"
        >
          {__('Default country code')}
        </label>
        <select
          id="wpsms-migration-country"
          className="wsms-w-full wsms-border wsms-border-input wsms-rounded-md wsms-px-3 wsms-py-2 wsms-text-[13px] wsms-bg-background"
          value={value || ''}
          onChange={(e) => onChange(e.target.value)}
          disabled={loading}
        >
          <option value="">{__('Select country code...')}</option>
          {Object.entries(countriesByDialCode).map(([dialCode, label]) => (
            <option key={dialCode} value={dialCode}>
              {label}
            </option>
          ))}
        </select>
        <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mt-2">
          <bdi>{exampleLine}</bdi>
        </p>
      </div>

      <div className="wsms-flex wsms-items-center wsms-justify-end wsms-gap-2 wsms-pt-2">
        <Button variant="outline" onClick={onBack} disabled={loading}>
          {__('Back')}
        </Button>
        <Button onClick={onContinue} disabled={loading || !value}>
          {loading ? (
            <>
              <Loader2 className="wsms-h-4 wsms-w-4 wsms-me-1.5 wsms-animate-spin" aria-hidden="true" />
              {__('Loading...')}
            </>
          ) : (
            __('Continue')
          )}
        </Button>
      </div>
    </div>
  )
}

