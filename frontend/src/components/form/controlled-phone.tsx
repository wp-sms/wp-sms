import { Controller, useFormContext } from 'react-hook-form'
import { useEffect, useState } from 'react'
import { Input } from '@/components/ui/input'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import { Button } from '@/components/ui/button'
import { Command, CommandEmpty, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import type { ControlledFieldProps } from './field-wrapper'

export type Country = {
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

export type ControlledPhoneProps = React.ComponentProps<'input'> & ControlledFieldProps
import parsePhoneNumber from 'libphonenumber-js'
import { FieldWrapper } from './field-wrapper'
import { WordPressDataService } from '@/lib/data-service'

export const ControlledPhone: React.FC<ControlledPhoneProps> = ({
  name,
  label,
  description,
  tooltip,
  tag,
  isLocked,
  isLoading,
}) => {
  const { control } = useFormContext()
  const [jsonData, setJsonData] = useState<Country[]>([])
  const [open, setOpen] = useState(false)

  const dataService = WordPressDataService.getInstance()

  useEffect(() => {
    const loadData = async () => {
      try {
        const response = await fetch(`${dataService.getBuildUrl()}countries.json`)
        const importedData = (await response.json()) as Country[]

        setJsonData(importedData)
      } catch (error) {}
    }

    loadData()
  }, [])

  return (
    <Controller
      name={name ?? ''}
      control={control}
      defaultValue={'+1'}
      render={({ field, fieldState }) => {
        const phoneUtil = parsePhoneNumber(`+${field?.value}`)

        let selectedCountry = jsonData?.find((item) =>
          phoneUtil?.countryCallingCode ? item.dialCode === `+${phoneUtil?.countryCallingCode}` : item.code === 'US'
        )

        return (
          <FieldWrapper
            label={label}
            description={description}
            isLoading={isLoading}
            error={fieldState?.error?.message}
            isLocked={isLocked}
            tag={tag}
            tooltip={tooltip}
          >
            <div className="flex border border-border rounded-lg focus-within:border-ring focus-within:ring-ring/50 focus-within:ring-[3px] transition-all duration-300">
              <div>
                <Popover open={open} onOpenChange={setOpen}>
                  <PopoverTrigger asChild>
                    <Button
                      variant="ghost"
                      role="combobox"
                      aria-expanded={open}
                      className="w-20 justify-between rounded-r-none bg-accent"
                    >
                      {`${selectedCountry?.emoji} ${selectedCountry?.dialCode}`}
                    </Button>
                  </PopoverTrigger>

                  <PopoverContent className="w-auto p-0">
                    <Command>
                      <CommandInput placeholder="Search country..." />
                      <CommandList>
                        <CommandEmpty>No country found.</CommandEmpty>

                        {jsonData?.map((item) => (
                          <CommandItem
                            key={`command-item-${item.id}`}
                            value={`${item.dialCode}__${item.code}__${item.name}`}
                            className="flex justify-between gap-1"
                            onSelect={(value) => {
                              const [dialCode, code] = value?.split('__')

                              const finalValue = `${dialCode.replace('+', '')}${phoneUtil?.nationalNumber}`

                              selectedCountry = jsonData?.find(
                                (item) => item?.code === code && item.dialCode === dialCode
                              )

                              field?.onChange(finalValue)

                              setOpen(false)
                            }}
                          >
                            <div className="flex items-center gap-x-1">
                              <div>{item?.emoji}</div>
                              <span className="line-clamp-1">{item?.name}</span>
                            </div>

                            <span className="text-muted-foreground">{item?.dialCode}</span>
                          </CommandItem>
                        ))}
                      </CommandList>
                    </Command>
                  </PopoverContent>
                </Popover>
              </div>

              <Input
                value={field?.value?.replace(phoneUtil?.countryCallingCode, '')}
                className="border-none focus:border-0 focus-visible:border-0 focus-within:!border-0 focus-visible:ring-0 focus-within:ring-0"
                onChange={(e) => {
                  field.onChange(`${phoneUtil?.countryCallingCode}${e.target.value}`)
                }}
              />
            </div>
          </FieldWrapper>
        )
      }}
    />
  )
}
