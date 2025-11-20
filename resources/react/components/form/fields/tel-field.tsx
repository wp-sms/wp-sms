import { useStore } from '@tanstack/react-form'
import { __ } from '@wordpress/i18n'
import parsePhoneNumber from 'libphonenumber-js'
import { Check } from 'lucide-react'
import { useCallback, useEffect, useMemo, useState } from 'react'

import { Button } from '@/components/ui/button'
import { Command, CommandEmpty, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { useFieldContext } from '@/context/form-context'
import { cn } from '@/lib/utils'
import { useCountries } from '@/services/use-countries'
import type { SchemaField } from '@/types/settings/group-schema'

import { FieldWrapper } from '../field-wrapper'

type TelFieldProps = {
  schema: SchemaField
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  form: any
}

/**
 * Check if a phone number string has a country prefix (starts with +)
 */
const hasCountryPrefix = (value: string): boolean => {
  return value.trim().startsWith('+')
}

export const TelField = ({ schema, form }: TelFieldProps) => {
  const field = useFieldContext<string>()
  const { countries } = useCountries()

  const errors = useStore(field.store, (state) => state.meta.errors)

  // Get the international_mobile setting value from the form
  const isInternationalInputEnabled = useStore(form.baseStore, (state) => {
    const values = state.values as Record<string, unknown>
    return Boolean(values.international_mobile)
  })

  const [open, setOpen] = useState(false)
  const [selectedCountryCode, setSelectedCountryCode] = useState('+1')
  const [phoneNumber, setPhoneNumber] = useState('')
  const [initialized, setInitialized] = useState(false)

  // Determine if we should show the country picker
  // Show it only if: international input is enabled AND number has country prefix
  const shouldShowCountryPicker = useMemo(() => {
    const fieldValue = field.state.value
    if (!isInternationalInputEnabled) {
      return false
    }
    // If international input is enabled, only show picker if number has country prefix
    if (fieldValue && typeof fieldValue === 'string') {
      return hasCountryPrefix(fieldValue)
    }
    // For new/empty values with international input enabled, show the picker
    return true
  }, [isInternationalInputEnabled, field.state.value])

  useEffect(() => {
    const fieldValue = field.state.value

    // Only parse and split the number if we're showing the country picker
    if (shouldShowCountryPicker && fieldValue && typeof fieldValue === 'string' && !initialized && countries.length > 0) {
      try {
        const phoneUtil = parsePhoneNumber(fieldValue)
        if (phoneUtil?.countryCallingCode && phoneUtil?.nationalNumber) {
          setSelectedCountryCode(`+${phoneUtil.countryCallingCode}`)
          setPhoneNumber(phoneUtil.nationalNumber)
          setInitialized(true)
        }
      } catch {
        // Handle error silently
      }
    }
  }, [field.state.value, initialized, countries, shouldShowCountryPicker])

  const selectedCountry =
    countries?.find((item) => item.dialCode === selectedCountryCode) ||
    countries?.find((item) => item.code === 'US') ||
    countries?.[0]

  const handleCountryChange = useCallback(
    (dialCode: string) => {
      setSelectedCountryCode(dialCode)
      const newValue = `${dialCode}${phoneNumber}`
      field.handleChange(newValue)
    },
    [phoneNumber, field]
  )

  const handlePhoneNumberChange = useCallback(
    (number: string) => {
      setPhoneNumber(number)
      const newValue = `${selectedCountryCode}${number}`
      field.handleChange(newValue)
    },
    [selectedCountryCode, field]
  )

  // If not showing country picker, render a simple text input
  // The number is displayed and migrated as-is without any modification
  if (!shouldShowCountryPicker) {
    return (
      <FieldWrapper errors={errors} schema={schema}>
        <Input
          id={schema.key}
          value={field.state.value || ''}
          placeholder={__('Enter phone number', 'wp-sms')}
          className={cn(
            'border rounded-lg focus-within:ring-[3px] transition-all duration-300',
            errors.length
              ? 'border-destructive focus-within:border-destructive focus-within:ring-destructive/50 text-destructive'
              : 'border-border focus-within:border-ring focus-within:ring-ring/50'
          )}
          onBlur={field.handleBlur}
          disabled={schema.readonly}
          aria-invalid={!!errors.length}
          onChange={(e) => {
            // Store the value as-is without any modification
            field.handleChange(e.target.value)
          }}
        />
      </FieldWrapper>
    )
  }

  // Show the full country picker with flag
  return (
    <FieldWrapper errors={errors} schema={schema}>
      <div
        className={cn(
          'flex border rounded-lg focus-within:ring-[3px] transition-all duration-300',
          errors.length
            ? 'border-destructive focus-within:border-destructive focus-within:ring-destructive/50'
            : 'border-border focus-within:border-ring focus-within:ring-ring/50'
        )}
      >
        <div>
          <Popover open={open} onOpenChange={setOpen}>
            <PopoverTrigger asChild>
              <Button
                variant="ghost"
                role="combobox"
                aria-expanded={open}
                className="w-20 justify-between rounded-r-none bg-accent"
                disabled={schema.readonly}
              >
                {`${selectedCountry?.emoji || '🇺🇸'} ${selectedCountryCode}`}
              </Button>
            </PopoverTrigger>

            <PopoverContent className="p-0">
              <Command value={`${selectedCountryCode}__${selectedCountry?.code}__${selectedCountry?.name}`}>
                <CommandInput placeholder={__('Search country...', 'wp-sms')} />
                <CommandList>
                  <CommandEmpty>{__('No country found.', 'wp-sms')}</CommandEmpty>

                  {countries?.map((item) => {
                    const isSelected = selectedCountryCode === item.dialCode

                    return (
                      <CommandItem
                        key={`command-item-${item.id}`}
                        value={`${item.dialCode}__${item.code}__${item.name}`}
                        className="flex justify-between gap-1"
                        onSelect={(value) => {
                          const [dialCode, code] = value?.split('__') || []

                          if (dialCode && code) {
                            handleCountryChange(dialCode)
                          }

                          setOpen(false)
                        }}
                      >
                        <div className="flex items-center gap-x-2">
                          <Check className={cn('h-4 w-4', isSelected ? 'opacity-100' : 'opacity-0')} />
                          <div className="flex items-center gap-x-1">
                            <div>{item?.emoji}</div>
                            <span className="line-clamp-1">{item?.name}</span>
                          </div>
                        </div>

                        <span className="text-muted-foreground">{item?.dialCode}</span>
                      </CommandItem>
                    )
                  })}
                </CommandList>
              </Command>
            </PopoverContent>
          </Popover>
        </div>

        <Input
          id={schema.key}
          value={phoneNumber}
          placeholder={__('Enter phone number', 'wp-sms')}
          className={cn(
            'border-none focus:border-0 focus-visible:border-0 focus-within:border-0 focus-visible:ring-0 focus-within:ring-0',
            errors.length && 'text-destructive'
          )}
          onBlur={field.handleBlur}
          disabled={schema.readonly}
          aria-invalid={!!errors.length}
          onChange={(e) => {
            const inputValue = e.target.value.replace(/\D/g, '')
            handlePhoneNumberChange(inputValue)
          }}
        />
      </div>
    </FieldWrapper>
  )
}
