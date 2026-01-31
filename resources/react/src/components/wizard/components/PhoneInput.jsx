import React, { useRef, useEffect, useState, useCallback, useMemo } from 'react'
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
      if (!document.querySelector('style[data-iti-custom]')) {
        const style = document.createElement('style')
        style.setAttribute('data-iti-custom', 'true')
        style.textContent = `
          .iti { width: 100%; }
          .iti__country-list { font-size: 12px; }
          .iti__country-name, .iti__dial-code { font-size: 12px; }
          .iti__search-input { font-size: 12px; padding: 6px 8px; }
        `
        document.head.appendChild(style)
      }


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
            utilsScript: utilsUrl,
            customPlaceholder: (selectedCountryPlaceholder, selectedCountryData) => {
              return `+${selectedCountryData.dialCode} 555 123 4567`
            },
          }

          // Add country restrictions if provided
          if (onlyCountries.length > 0) {
            options.onlyCountries = onlyCountries.map(c => c.toLowerCase())
          }
          if (preferredCountries.length > 0) {
            options.preferredCountries = preferredCountries.map(c => c.toLowerCase())
          }

          const iti = window.intlTelInput(inputRef.current, options)
          itiRef.current = iti

          // Set initial value if provided
          if (value) {
            iti.setNumber(value)
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

  return (
    <div className={cn('wsms-relative', className)}>
      <input
        ref={inputRef}
        type="tel"
        dir="ltr"
        autoComplete="off"
        disabled={disabled}
        className={cn(
          'wsms-flex wsms-h-9 wsms-w-full wsms-rounded-md wsms-border wsms-border-input wsms-bg-background wsms-px-3 wsms-py-1 wsms-text-sm wsms-ring-offset-background',
          'placeholder:wsms-text-muted-foreground',
          'focus-visible:wsms-outline-none focus-visible:wsms-ring-2 focus-visible:wsms-ring-ring focus-visible:wsms-ring-offset-2',
          'disabled:wsms-cursor-not-allowed disabled:wsms-opacity-50',
          error && 'wsms-border-destructive',
          // Extra padding for the country dropdown
          'wsms-pl-[90px]'
        )}
        style={{ paddingLeft: '90px', height: '36px' }}
      />
      {error && (
        <p className="wsms-text-[12px] wsms-text-destructive wsms-mt-1">{error}</p>
      )}
    </div>
  )
}
