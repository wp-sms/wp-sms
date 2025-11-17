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
      error: 'Phone number is required',
    }
  }

  const cleanValue = phoneNumber.replace(/\s+/g, '')

  if (cleanValue.length < 7) {
    return {
      isValid: false,
      error: 'Phone number is too short',
    }
  }

  try {
    const parsedNumber = parsePhoneNumber(cleanValue, countryCode as any)

    if (!parsedNumber) {
      return {
        isValid: false,
        error: 'Please enter a valid phone number',
      }
    }

    if (!parsedNumber.isValid()) {
      return {
        isValid: false,
        error: 'Please enter a valid phone number for the selected country',
      }
    }

    if (parsedNumber.nationalNumber && parsedNumber.nationalNumber.length < 6) {
      return {
        isValid: false,
        error: 'Phone number is too short for this country',
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
      error: 'Please enter a valid phone number',
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
