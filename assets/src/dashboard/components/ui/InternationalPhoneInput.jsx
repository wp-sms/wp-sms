import React from 'react'
import { Input } from '@/components/ui/input'
import { useSetting } from '@/context/SettingsContext'
import PhoneInput from '@/components/wizard/components/PhoneInput'
import { cn } from '@/lib/utils'

/**
 * InternationalPhoneInput - A wrapper component that conditionally shows
 * international phone input based on the 'International Phone Input' setting.
 *
 * When international mode is enabled, shows a country flag selector.
 * When disabled, shows a regular text input.
 *
 * Respects 'Limit Countries' and 'Preferred Countries' settings.
 */
export function InternationalPhoneInput({
  value = '',
  onChange,
  className,
  placeholder,
  disabled = false,
  id,
  ...props
}) {
  // Read settings from context
  const [internationalMobile] = useSetting('international_mobile', '')
  const [onlyCountries] = useSetting('international_mobile_only_countries', [])
  const [preferredCountries] = useSetting('international_mobile_preferred_countries', [])

  const isInternationalEnabled = internationalMobile === '1'

  // Handler for PhoneInput (receives full number and country code)
  const handlePhoneChange = (fullNumber, countryCode) => {
    // Pass the full number to the parent
    onChange?.(fullNumber)
  }

  // Handler for regular input
  const handleInputChange = (e) => {
    onChange?.(e.target.value)
  }

  if (isInternationalEnabled) {
    return (
      <PhoneInput
        value={value}
        onChange={handlePhoneChange}
        className={className}
        placeholder={placeholder}
        disabled={disabled}
        onlyCountries={onlyCountries}
        preferredCountries={preferredCountries}
        {...props}
      />
    )
  }

  // Regular input when international mode is disabled
  return (
    <Input
      id={id}
      type="tel"
      value={value}
      onChange={handleInputChange}
      placeholder={placeholder || '+1 555 123 4567'}
      disabled={disabled}
      className={cn('wsms-font-mono', className)}
      {...props}
    />
  )
}

export default InternationalPhoneInput
