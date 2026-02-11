import React, { useRef, useEffect, useState, useCallback, useMemo } from 'react'
import { AlertCircle } from 'lucide-react'
import { cn, getWpSettings } from '@/lib/utils'

/**
 * Phone input component with international telephone input support
 * Wraps the intlTelInput library for React usage
 */
export default function PhoneInput({
  value = '',
  onChange,
  onValidChange,
  className,
  placeholder,
  disabled = false,
  initialCountry = 'us',
  onlyCountries = [],
  preferredCountries = [],
}) {
  const inputRef = useRef(null)
  const itiRef = useRef(null)
  const [isLoaded, setIsLoaded] = useState(false)
  const [isValid, setIsValid] = useState(false)
  const [error, setError] = useState('')

  // Memoize country arrays to prevent infinite re-renders
  const onlyCountriesKey = useMemo(() => JSON.stringify(onlyCountries), [onlyCountries])
  const preferredCountriesKey = useMemo(() => JSON.stringify(preferredCountries), [preferredCountries])

  // Load intlTelInput scripts dynamically
  useEffect(() => {
    const loadScripts = async () => {
      const { pluginUrl } = getWpSettings()
      const baseUrl = pluginUrl || '/wp-content/plugins/wp-sms/'

      // Load CSS if not already loaded
      if (!document.querySelector('link[href*="intlTelInput"]')) {
        const link = document.createElement('link')
        link.rel = 'stylesheet'
        link.href = `${baseUrl}assets/css/intlTelInput.min.css`
        document.head.appendChild(link)
      }

      // Add custom styles for intlTelInput
      // Keep this style block idempotent. In the React SPA, the element may already exist from a
      // previous navigation, and we don't want stale CSS to linger across updates.
      const existingStyle = document.querySelector('style[data-iti-custom]')
      const style = existingStyle || document.createElement('style')
      if (!existingStyle) {
        style.setAttribute('data-iti-custom', 'true')
        document.head.appendChild(style)
      }
      style.textContent = `
        .iti { width: 100%; }
        .iti__country-list { font-size: 12px; }
        .iti__country-name, .iti__dial-code { font-size: 12px; }
        .iti__search-input { font-size: 12px; padding: 6px 8px; }
      `


      // Load main script if not already loaded
      if (!window.intlTelInput) {
        await new Promise((resolve, reject) => {
          const script = document.createElement('script')
          script.src = `${baseUrl}assets/js/intel/intlTelInput.min.js`
          script.onload = resolve
          script.onerror = reject
          document.head.appendChild(script)
        })
      }

      // Load utils script
      const utilsUrl = `${baseUrl}assets/js/intel/utils.js`

      setIsLoaded(true)
      return utilsUrl
    }

    loadScripts()
      .then((utilsUrl) => {
        if (inputRef.current && window.intlTelInput && !itiRef.current) {
          const options = {
            initialCountry: initialCountry,
            autoInsertDialCode: true,
            allowDropdown: true,
            strictMode: true,
            useFullscreenPopup: false,
            nationalMode: false,
            autoPlaceholder: 'polite',
            loadUtilsOnInit: utilsUrl,
            customPlaceholder: (selectedCountryPlaceholder, selectedCountryData) => {
              return `+${selectedCountryData.dialCode} 555 123 4567`
            },
          }

          // Add country restrictions if provided
          if (onlyCountries.length > 0) {
            options.onlyCountries = onlyCountries.map(c => c.toLowerCase())
          }
          if (preferredCountries.length > 0) {
            // v24+ uses countryOrder instead of preferredCountries
            options.countryOrder = preferredCountries.map(c => c.toLowerCase())
          }

          const iti = window.intlTelInput(inputRef.current, options)
          itiRef.current = iti

          // Set initial value if provided and validate after utils loads
          if (value) {
            iti.setNumber(value)
            // Validate after utils.js is loaded to ensure proper validation
            if (iti.promise) {
              iti.promise.then(() => {
                const valid = iti.isValidNumber()
                setIsValid(valid)
                if (valid) {
                  const fullNumber = iti.getNumber().replace(/[-\s]/g, '')
                  const countryData = iti.getSelectedCountryData()
                  const countryCode = `+${countryData.dialCode}`
                  onChange?.(fullNumber, countryCode)
                  onValidChange?.(true)
                }
              })
            }
          }

          // Update placeholder
          const dialCode = iti.getSelectedCountryData().dialCode || '1'
          inputRef.current.placeholder = placeholder || `+${dialCode} 555 123 4567`
        }
      })
      .catch((err) => {
        console.error('Failed to load intlTelInput:', err)
        setError('Failed to load phone input')
      })

    return () => {
      if (itiRef.current) {
        itiRef.current.destroy()
        itiRef.current = null
      }
    }
  }, [initialCountry, placeholder, onlyCountriesKey, preferredCountriesKey])

  // Handle country change
  const handleCountryChange = useCallback(() => {
    if (!itiRef.current || !inputRef.current) return

    const selectedCountryData = itiRef.current.getSelectedCountryData()
    const dialCode = selectedCountryData.dialCode || '1'

    // Update placeholder
    inputRef.current.placeholder = placeholder || `+${dialCode} 555 123 4567`

    // If empty, set to just the dial code
    if (!inputRef.current.value.trim()) {
      inputRef.current.value = `+${dialCode}`
    }

    validateAndNotify()
  }, [placeholder])

  // Validate and notify parent
  const validateAndNotify = useCallback(() => {
    if (!itiRef.current || !inputRef.current) return

    const phoneValue = inputRef.current.value.trim()
    const isEmpty = phoneValue === '' || phoneValue === `+${itiRef.current.getSelectedCountryData().dialCode}`

    if (isEmpty) {
      setIsValid(false)
      setError('')
      onValidChange?.(false)
      onChange?.('', '')
      return
    }

    const valid = itiRef.current.isValidNumber()
    setIsValid(valid)

    if (valid) {
      setError('')
      const fullNumber = itiRef.current.getNumber().replace(/[-\s]/g, '')
      const countryData = itiRef.current.getSelectedCountryData()
      const countryCode = `+${countryData.dialCode}`
      onChange?.(fullNumber, countryCode)
      onValidChange?.(true)
    } else {
      setError('Please enter a valid phone number')
      onValidChange?.(false)
    }
  }, [onChange, onValidChange])

  // Handle input change
  const handleInput = useCallback(() => {
    validateAndNotify()
  }, [validateAndNotify])

  // Set up event listeners
  useEffect(() => {
    const input = inputRef.current
    if (!input || !isLoaded) return

    input.addEventListener('countrychange', handleCountryChange)
    input.addEventListener('input', handleInput)

    return () => {
      input.removeEventListener('countrychange', handleCountryChange)
      input.removeEventListener('input', handleInput)
    }
  }, [isLoaded, handleCountryChange, handleInput])

  // Sync external value prop with internal input (e.g., when parent clears the value)
  useEffect(() => {
    if (!itiRef.current || !inputRef.current || !isLoaded) return

    const currentInputValue = inputRef.current.value.trim()
    const dialCode = itiRef.current.getSelectedCountryData().dialCode || '1'
    const dialCodePrefix = `+${dialCode}`

    // If external value is empty, clear the input to just the dial code
    if (value === '' && currentInputValue !== '' && currentInputValue !== dialCodePrefix) {
      // Directly set input value instead of using setNumber() to avoid library issues
      inputRef.current.value = dialCodePrefix
      setIsValid(false)
      setError('')
    }
  }, [value, isLoaded])

  return (
    <div className={cn('wsms-relative', className)}>
      <input
        ref={inputRef}
        type="tel"
        dir="ltr"
        autoComplete="off"
        disabled={disabled}
        className={cn(
          'wsms-flex wsms-h-9 wsms-w-full wsms-rounded-md wsms-border wsms-bg-background wsms-py-1 wsms-text-sm wsms-ring-offset-background',
          'placeholder:wsms-text-muted-foreground',
          'focus-visible:wsms-outline-none focus-visible:wsms-ring-2 focus-visible:wsms-ring-offset-2',
          'disabled:wsms-cursor-not-allowed disabled:wsms-opacity-50',
          error
            ? 'wsms-border-red-500 focus-visible:wsms-ring-red-500/30'
            : 'wsms-border-input focus-visible:wsms-ring-ring',
          // Ensure enough room for the country button on the "start" side:
          // LTR: left, RTL: right.
          'wsms-ps-[90px] rtl:wsms-ps-3',
          error ? 'wsms-pe-7 rtl:wsms-pe-3 rtl:wsms-ps-7' : 'wsms-pe-3 rtl:wsms-pe-[90px]',
          // Keep phone number direction LTR for readability, but align text to the right in RTL
          // so the value sits next to the flag dropdown.
          'rtl:wsms-text-right',
        )}
      />
      {error && (
        <div className="wsms-absolute wsms-end-2 wsms-top-1/2 wsms--translate-y-1/2 wsms-group">
          <AlertCircle className="wsms-h-3.5 wsms-w-3.5 wsms-text-red-500 wsms-cursor-help" strokeWidth={2} />
          <div className="wsms-absolute wsms-bottom-full wsms--end-2 wsms-mb-1.5 wsms-hidden group-hover:wsms-block wsms-z-50 wsms-pointer-events-none wsms-w-max">
            <div className="wsms-bg-slate-800 wsms-text-white wsms-text-[11px] wsms-px-2 wsms-py-1 wsms-rounded wsms-max-w-[280px] wsms-shadow-lg">
              {error}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
