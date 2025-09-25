import parsePhoneNumber from 'libphonenumber-js'
import { Check } from 'lucide-react'
import { useCallback, useEffect, useState } from 'react'

import { Button } from '@/components/ui/button'
import { Command, CommandEmpty, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { WordPressDataService } from '@/lib/data-service'
import { cn } from '@/lib/utils'
import type { FieldValue } from '@/types/settings/group-schema'

import type { SimpleFieldApi } from '../field-renderer'

type Country = {
  id: number
  name: string
  nativeName: string
  code: string
  dialCode: string
  allDialCodes: string[]
  emoji: string
  unicode: string
  flag: string
}

type TelFieldProps = {
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  fieldState: { errors: string[] }
}

export const TelField = ({ fieldApi, fieldValue, fieldState }: TelFieldProps) => {
  const [jsonData, setJsonData] = useState<Country[]>([])
  const [open, setOpen] = useState(false)
  const [selectedCountryCode, setSelectedCountryCode] = useState('+1')
  const [phoneNumber, setPhoneNumber] = useState('')
  const [initialized, setInitialized] = useState(false)

  const dataService = WordPressDataService.getInstance()

  useEffect(() => {
    const loadData = async () => {
      try {
        const response = await fetch(`${dataService.getBuildUrl()}countries.json`)
        const importedData = (await response.json()) as Country[]
        setJsonData(importedData)
      } catch {
        // Handle error silently
      }
    }

    loadData()
  }, [dataService])

  useEffect(() => {
    if (fieldValue && typeof fieldValue === 'string' && !initialized && jsonData.length > 0) {
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
  }, [fieldValue, initialized, jsonData])

  const selectedCountry =
    jsonData?.find((item) => item.dialCode === selectedCountryCode) ||
    jsonData?.find((item) => item.code === 'US') ||
    jsonData?.[0]

  const handleCountryChange = useCallback(
    (dialCode: string) => {
      setSelectedCountryCode(dialCode)
      const newValue = `${dialCode}${phoneNumber}`
      fieldApi.handleChange(newValue)
    },
    [phoneNumber, fieldApi]
  )

  const handlePhoneNumberChange = useCallback(
    (number: string) => {
      setPhoneNumber(number)
      const newValue = `${selectedCountryCode}${number}`
      fieldApi.handleChange(newValue)
    },
    [selectedCountryCode, fieldApi]
  )

  return (
    <div
      className={cn(
        'flex border rounded-lg focus-within:ring-[3px] transition-all duration-300',
        fieldState?.errors.length
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
            >
              {`${selectedCountry?.emoji || '🇺🇸'} ${selectedCountryCode}`}
            </Button>
          </PopoverTrigger>

          <PopoverContent className="p-0">
            <Command value={`${selectedCountryCode}__${selectedCountry?.code}__${selectedCountry?.name}`}>
              <CommandInput placeholder="Search country..." />
              <CommandList>
                <CommandEmpty>No country found.</CommandEmpty>

                {jsonData?.map((item) => {
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
        value={phoneNumber}
        placeholder="Enter phone number"
        className={cn(
          'border-none focus:border-0 focus-visible:border-0 focus-within:!border-0 focus-visible:ring-0 focus-within:ring-0',
          fieldState?.errors.length && 'text-destructive'
        )}
        aria-invalid={!!fieldState?.errors.length}
        onChange={(e) => {
          const inputValue = e.target.value.replace(/\D/g, '')
          handlePhoneNumberChange(inputValue)
        }}
      />
    </div>
  )
}
