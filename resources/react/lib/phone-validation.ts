import { __ } from '@wordpress/i18n'
import parsePhoneNumber from 'libphonenumber-js'

export type PhoneValidationResult = {
  isValid: boolean
  error?: string
  formattedNumber?: string
  countryCode?: string
  nationalNumber?: string
}

export const validatePhoneNumber = (phoneNumber: string, countryCode?: string): PhoneValidationResult => {
  if (!phoneNumber || phoneNumber.trim() === '') {
    return {
      isValid: false,
      error: __('Phone number is required', 'wp-sms'),
    }
  }

  const cleanValue = phoneNumber.replace(/\s+/g, '')

  if (cleanValue.length < 7) {
    return {
      isValid: false,
      error: __('Phone number is too short', 'wp-sms'),
    }
  }

  try {
    const parsedNumber = parsePhoneNumber(cleanValue, countryCode as any)

    if (!parsedNumber) {
      return {
        isValid: false,
        error: __('Please enter a valid phone number', 'wp-sms'),
      }
    }

    if (!parsedNumber.isValid()) {
      return {
        isValid: false,
        error: __('Please enter a valid phone number for the selected country', 'wp-sms'),
      }
    }

    if (parsedNumber.nationalNumber && parsedNumber.nationalNumber.length < 6) {
      return {
        isValid: false,
        error: __('Phone number is too short for this country', 'wp-sms'),
      }
    }

    return {
      isValid: true,
      formattedNumber: parsedNumber.formatInternational(),
      countryCode: parsedNumber.countryCallingCode,
      nationalNumber: parsedNumber.nationalNumber,
    }
  } catch {
    return {
      isValid: false,
      error: __('Please enter a valid phone number', 'wp-sms'),
    }
  }
}

export const formatPhoneNumber = (phoneNumber: string, countryCode?: string): string => {
  try {
    const parsedNumber = parsePhoneNumber(phoneNumber, countryCode as any)
    return parsedNumber?.formatInternational() || phoneNumber
  } catch {
    return phoneNumber
  }
}

export const extractCountryCode = (phoneNumber: string): string | null => {
  try {
    const parsedNumber = parsePhoneNumber(phoneNumber)
    return parsedNumber?.countryCallingCode || null
  } catch {
    return null
  }
}
