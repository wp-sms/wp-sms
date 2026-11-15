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
  'aria-label': ariaLabel,
  id,
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
        link.href = `${baseUrl}public/css/intlTelInput.min.css`
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
        /* Theme tokens: soft border, popover surface, muted dial code, light primary tint. */
        .wsms-wizard-phone-input .iti {
          --iti-border-color: hsl(var(--border));
          --iti-dropdown-bg: hsl(var(--popover));
          --iti-dialcode-color: hsl(var(--muted-foreground));
          --iti-arrow-color: hsl(var(--muted-foreground));
          --iti-hover-color: hsl(var(--accent));
          --iti-arrow-height: 3px;
          --iti-arrow-width: 8px;
          --iti-arrow-padding: 4px;
        }
        /* Closed field: no grey block behind the flag, only a soft hover tint. */
        .wsms-wizard-phone-input .iti .iti__selected-country-primary { padding: 0 6px 0 10px; border-radius: 5px 0 0 5px; }
        .wsms-wizard-phone-input .iti .iti__selected-country { border-radius: 5px 0 0 5px; }
        .wsms-wizard-phone-input .iti--allow-dropdown .iti__country-container:not(:has(+ input[disabled])) .iti__selected-country-primary:hover,
        .wsms-wizard-phone-input .iti--allow-dropdown .iti__country-container:not(:has(+ input[disabled])) .iti__selected-country:has(+ .iti__dropdown-content:hover) .iti__selected-country-primary { background-color: hsl(var(--accent) / 0.6); border-radius: 5px 0 0 5px; }
        .wsms-wizard-phone-input .iti--allow-dropdown .iti__country-container:not(:has(+ input[disabled])) .iti__selected-country-primary:hover .iti__arrow { opacity: 1; }
        .wsms-wizard-phone-input .iti__arrow { opacity: 0.7; transition: opacity 0.15s; }
        /* Open list: card with a soft border and shadow, like the other dashboard dropdowns. */
        .wsms-wizard-phone-input .iti__dropdown-content {
          border: 1px solid hsl(var(--border));
          border-radius: 4px;
          background-color: hsl(var(--popover));
          color: hsl(var(--popover-foreground));
          box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -2px rgba(0, 0, 0, 0.1);
          overflow: hidden;
          margin-top: 4px;
          font-size: 14px;
        }
        .wsms-wizard-phone-input .iti__search-input {
          display: block;
          width: calc(100% - 16px);
          height: 34px;
          margin: 8px 8px 8px;
          padding: 0 10px 0 32px;
          font-size: 14px;
          line-height: 34px;
          color: hsl(var(--foreground));
          background: transparent url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2394a3b8' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'/%3E%3Cpath d='m21 21-4.3-4.3'/%3E%3C/svg%3E") no-repeat 10px 50% / 14px 14px;
          border: 1px solid hsl(var(--border));
          border-radius: 6px;
          box-shadow: none;
          outline: none;
          box-sizing: border-box;
        }
        .wsms-wizard-phone-input .iti__search-input::placeholder { color: hsl(var(--muted-foreground)); }
        .wsms-wizard-phone-input .iti__search-input:focus,
        .wsms-wizard-phone-input .iti__search-input:focus-visible { outline: none; border: 1px solid hsl(var(--primary) / 0.6); box-shadow: 0 0 0 3px hsl(var(--primary) / 0.15); }
        .wsms-wizard-phone-input .iti__search-input + .iti__country-list { border-top: 1px solid hsl(var(--border)); }
        .wsms-wizard-phone-input .iti__dropdown-content > div, .wsms-wizard-phone-input .iti__dropdown-content > .iti__search-input-wrapper { border: 0; }
        .wsms-wizard-phone-input .iti__country-list {
          font-size: 14px;
          padding: 4px;
          max-height: 240px;
          overflow-y: auto;
          scrollbar-width: thin;
          scrollbar-color: hsl(var(--border)) transparent;
        }
        .wsms-wizard-phone-input .iti__country-list::-webkit-scrollbar { width: 6px; }
        .wsms-wizard-phone-input .iti__country-list::-webkit-scrollbar-thumb { background: hsl(var(--border)); border-radius: 3px; }
        .wsms-wizard-phone-input .iti__country {
          min-height: 40px;
          padding: 0 8px;
          gap: 8px;
          border-radius: 4px;
          cursor: pointer;
        }
        .wsms-wizard-phone-input .iti__country-list .iti__flag { margin: 0; flex-shrink: 0; }
        .wsms-wizard-phone-input .iti__country-name { margin: 0; flex: 1; min-width: 0; font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .wsms-wizard-phone-input .iti__dial-code { margin-left: auto; font-size: 13px; color: hsl(var(--muted-foreground)); font-variant-numeric: tabular-nums; }
        .wsms-wizard-phone-input .iti__country.iti__highlight { background-color: hsl(var(--accent)); }
        .wsms-wizard-phone-input .iti__divider { margin: 4px 0; border: 0; border-bottom: 1px solid hsl(var(--border)); opacity: 0.7; }
        /* A phone number is always left to right: the library flips the flag and padding under any
           [dir=rtl] ancestor, so put them back on the left inside our own dir=ltr wrapper. */
        .wsms-wizard-phone-input .iti { direction: ltr; }
        .wsms-wizard-phone-input .iti .iti__country-container { left: 0 !important; right: auto !important; }
        .wsms-wizard-phone-input .iti input.iti__tel-input { direction: ltr !important; text-align: left !important; padding-left: 56px !important; padding-right: 28px !important; }
        .wsms-wizard-phone-input .iti__selected-country { direction: ltr; }
        .wsms-wizard-phone-input .iti__arrow { margin: 0 0 0 6px !important; }
        .wsms-wizard-phone-input .iti .iti__country-container .iti__dropdown-content { left: 0 !important; right: auto !important; inset-inline-start: auto !important; inset-inline-end: auto !important; width: 320px; min-width: 260px; max-width: min(340px, calc(100vw - 48px)); direction: ltr; text-align: left; }
        .wsms-wizard-body.wsms-wizard-body--open-list { overflow: visible !important; position: relative; z-index: 5; }
        .wsms-wizard-phone-input.wsms-phone-dropup .iti .iti__country-container .iti__dropdown-content { top: auto !important; bottom: calc(100% + 4px) !important; }
        .wsms-wizard-phone-input .iti__search-input { direction: ltr; text-align: left; }
        .wsms-wizard-phone-input .iti__country-list, .wsms-wizard-phone-input .iti__country { direction: ltr; text-align: left; }
        [data-theme="dark"] .wsms-wizard-phone-input .iti__dropdown-content { box-shadow: 0 8px 24px -6px rgba(0, 0, 0, 0.6); }
        [data-theme="dark"] .wsms-wizard-phone-input input.iti__tel-input {
          color: hsl(var(--muted-foreground)) !important;
        }
      `


      // Load main script if not already loaded
      if (!window.intlTelInput) {
        await new Promise((resolve, reject) => {
          const script = document.createElement('script')
          script.src = `${baseUrl}public/js/intel/intlTelInput.min.js`
          script.onload = resolve
          script.onerror = reject
          document.head.appendChild(script)
        })
      }

      // Pre-load utils via fetch+Blob to avoid MIME type issues with import()
      const utilsUrl = `${baseUrl}public/js/intel/utils.js`
      if (!window.intlTelInput.utils) {
        const response = await fetch(utilsUrl)
        if (response.ok) {
          const text = await response.text()
          const blob = new Blob([text], { type: 'text/javascript' })
          const blobUrl = URL.createObjectURL(blob)
          try {
            await window.intlTelInput.loadUtils(blobUrl)
          } finally {
            URL.revokeObjectURL(blobUrl)
          }
        }
      }

      setIsLoaded(true)
    }

    loadScripts()
      .then(() => {
        if (inputRef.current && window.intlTelInput && !itiRef.current) {
          const options = {
            initialCountry: initialCountry,
            autoInsertDialCode: true,
            allowDropdown: true,
            useFullscreenPopup: false,
            nationalMode: false,
            autoPlaceholder: 'polite',
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

    // Inside the setup wizard the list sat in a scrolling box and its bottom rows were cut off by the
    // footer. While the list is open the box stops clipping, and the list opens upward when there is
    // more room above the field than below it.
    const handleDropdownOpen = () => {
      const wrapper = input.closest('.wsms-wizard-phone-input')
      const body = input.closest('.wsms-wizard-body')
      if (!wrapper || !body) return
      const dialog = input.closest('[role="dialog"]') || body
      const field = input.getBoundingClientRect()
      const bounds = dialog.getBoundingClientRect()
      const below = bounds.bottom - field.bottom
      const above = field.top - bounds.top
      body.classList.add('wsms-wizard-body--open-list')
      wrapper.classList.toggle('wsms-phone-dropup', below < 400 && above > below)
    }
    const handleDropdownClose = () => {
      input.closest('.wsms-wizard-body')?.classList.remove('wsms-wizard-body--open-list')
      input.closest('.wsms-wizard-phone-input')?.classList.remove('wsms-phone-dropup')
    }

    input.addEventListener('countrychange', handleCountryChange)
    input.addEventListener('input', handleInput)
    input.addEventListener('open:countrydropdown', handleDropdownOpen)
    input.addEventListener('close:countrydropdown', handleDropdownClose)

    return () => {
      input.removeEventListener('countrychange', handleCountryChange)
      input.removeEventListener('input', handleInput)
      input.removeEventListener('open:countrydropdown', handleDropdownOpen)
      input.removeEventListener('close:countrydropdown', handleDropdownClose)
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
    <div dir="ltr" className={cn('wsms-wizard-phone-input wsms-relative', className)}>
      <input
        ref={inputRef}
        type="tel"
        dir="ltr"
        autoComplete="off"
        disabled={disabled}
        aria-label={ariaLabel}
        id={id}
        className={cn(
          'wsms-flex wsms-h-9 wsms-w-full wsms-rounded-md wsms-border wsms-bg-card wsms-py-1 wsms-text-sm dark:wsms-text-muted-foreground wsms-ring-offset-background',
          'placeholder:wsms-text-muted-foreground',
          'focus-visible:wsms-outline-none focus-visible:wsms-ring-2 focus-visible:wsms-ring-offset-2',
          'disabled:wsms-cursor-not-allowed disabled:wsms-opacity-50',
          error
            ? 'wsms-border-red-500 focus-visible:wsms-ring-red-500/30'
            : 'wsms-border-input focus-visible:wsms-ring-ring',
          // A phone number is always left to right, also on right-to-left sites: the country
          // button sits on the left and the number reads from the left.
          'wsms-ps-14 wsms-text-left',
          error ? 'wsms-pe-7' : 'wsms-pe-3',
        )}
      />
      {error && (
        <div className="wsms-absolute wsms-end-2 wsms-top-1/2 wsms--translate-y-1/2 wsms-group">
          <AlertCircle className="wsms-h-3.5 wsms-w-3.5 wsms-text-red-500 wsms-cursor-help" strokeWidth={2} />
          <div className="wsms-absolute wsms-bottom-full wsms--end-2 wsms-mb-1.5 wsms-hidden group-hover:wsms-block wsms-z-50 wsms-pointer-events-none wsms-w-max">
            <div className="wsms-bg-slate-800 wsms-text-white wsms-text-[12px] wsms-px-2 wsms-py-1 wsms-rounded wsms-max-w-[280px] wsms-shadow-lg">
              {error}
            </div>
          </div>
        </div>
      )}
    </div>
  )
}
