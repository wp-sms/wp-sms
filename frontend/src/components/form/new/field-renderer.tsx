import { cva } from 'class-variance-authority'
import parsePhoneNumber from 'libphonenumber-js'
import { AlertCircle, Check, CloudUpload, Grip, Plus, Settings, Trash2Icon } from 'lucide-react'
import React, { useCallback, useEffect, useMemo, useState } from 'react'

import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Command, CommandEmpty, CommandInput, CommandItem, CommandList } from '@/components/ui/command'
import { ConfirmAction } from '@/components/ui/confirm-action'
import { Input } from '@/components/ui/input'
import { MultiSelect } from '@/components/ui/multiselect'
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import { Textarea } from '@/components/ui/textarea'
import { useWordPressMediaUploader } from '@/hooks/use-wordpress-media-uploader'
import { WordPressDataService } from '@/lib/data-service'
import { toOptions } from '@/lib/to-options'
import { cn } from '@/lib/utils'
import type { SchemaField } from '@/types/settings/group-schema'

import { FieldWrapper } from './field-wrapper'

// Specific types for field rendering
type FieldValue = string | number | boolean | string[] | Record<string, unknown>[] | null | undefined

// Country type for phone field
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

// Simplified FieldApi type for our use case
export type SimpleFieldApi = {
  name: string
  state: {
    value: FieldValue
    meta: {
      errors: string[]
    }
  }
  handleBlur: () => void
  handleChange: (value: FieldValue) => void
}

type FieldRendererProps = {
  field: SchemaField
  fieldApi: SimpleFieldApi
  onOpenSubFields?: (field: SchemaField) => void
  onFieldValueChange?: (name: string, value: FieldValue) => void
  formValues?: Record<string, unknown>
  defaultValues?: Record<string, unknown>
  groupName?: SettingGroupName
}

type FieldHelperFunctions = {
  getFieldOptions: (field: SchemaField) => Record<string, string>
  getFieldPlaceholder: (field: SchemaField) => string
  getFieldStep: (field: SchemaField) => number | null
  getFieldRows: (field: SchemaField) => number | null
  getFieldSubFields: (field: SchemaField) => SchemaField[]
}

type RepeaterItem = Record<string, unknown> & { id?: string }

// Helper functions to safely access field properties
const fieldHelpers: FieldHelperFunctions = {
  getFieldOptions: (field: SchemaField): Record<string, string> => {
    if (Array.isArray(field.options)) {
      return {}
    }

    // Convert FieldOption to Record<string, string>
    const options: Record<string, string> = {}
    Object.entries(field.options).forEach(([key, value]) => {
      options[key] = typeof value === 'string' ? value : key
    })

    return options
  },

  getFieldPlaceholder: (field: SchemaField): string => {
    return field.placeholder || ''
  },

  getFieldStep: (field: SchemaField): number | null => {
    return field.step
  },

  getFieldRows: (field: SchemaField): number | null => {
    return field.rows
  },

  getFieldSubFields: (field: SchemaField): SchemaField[] => {
    return field.sub_fields || []
  },
}

const TextField = ({
  field,
  fieldApi,
  fieldValue,
  fieldState,
}: {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  fieldState: { errors: string[] }
}) => {
  return (
    <Input
      id={field.key}
      name={fieldApi.name}
      type="text"
      placeholder={field.placeholder}
      value={String(fieldValue || '')}
      onBlur={fieldApi.handleBlur}
      onChange={(e) => fieldApi.handleChange(e.target.value)}
      disabled={field.readonly}
      aria-invalid={!!fieldState.errors.length}
    />
  )
}

const TextareaField = ({
  field,
  fieldApi,
  fieldValue,
  fieldState,
}: {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  fieldState: { errors: string[] }
}) => {
  return (
    <Textarea
      id={field.key}
      name={fieldApi.name}
      placeholder={field.placeholder}
      value={String(fieldValue || '')}
      onBlur={fieldApi.handleBlur}
      onChange={(e) => fieldApi.handleChange(e.target.value)}
      disabled={field.readonly}
      rows={field.rows || 3}
      aria-invalid={!!fieldState.errors.length}
    />
  )
}

const NumberField = ({
  field,
  fieldApi,
  fieldValue,
  fieldState,
}: {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  fieldState: { errors: string[] }
}) => {
  return (
    <Input
      id={field.key}
      name={fieldApi.name}
      type="number"
      min={field.min || undefined}
      max={field.max || undefined}
      step={fieldHelpers.getFieldStep(field) || undefined}
      value={String(fieldValue || '')}
      onBlur={fieldApi.handleBlur}
      onChange={(e) => fieldApi.handleChange(parseFloat(e.target.value) || 0)}
      disabled={field.readonly}
      aria-invalid={!!fieldState.errors.length}
    />
  )
}

const SelectField = ({
  field,
  fieldApi,
  fieldValue,
  fieldState,
}: {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  fieldState: { errors: string[] }
}) => {
  return (
    <Select
      value={String(fieldValue || '')}
      onValueChange={(value) => fieldApi.handleChange(value)}
      disabled={field.readonly}
    >
      <SelectTrigger
        aria-invalid={!!fieldState.errors.length}
        aria-disabled={field.readonly}
        aria-readonly={field.readonly}
        className="w-full"
        id={field.key}
      >
        <SelectValue className="w-full" placeholder={field.placeholder} />
      </SelectTrigger>
      <SelectContent>
        {toOptions(field.options)?.map((item, index) => {
          if (item?.children) {
            return (
              <SelectGroup key={`select-group-${item.value}-${index}`}>
                <SelectLabel>{item.label}</SelectLabel>

                {item.children?.map((child, j) => {
                  return (
                    <SelectItem key={`group-select-item-${child.value}${j}`} value={String(child.value)}>
                      {child.label}
                    </SelectItem>
                  )
                })}
              </SelectGroup>
            )
          }

          return (
            <SelectItem key={`select-item-${item.value}-${index}`} value={String(item.value)}>
              {item.label}
            </SelectItem>
          )
        })}
      </SelectContent>
    </Select>
  )
}

const MultiselectField = ({
  field,
  fieldApi,
  fieldValue,
  fieldState,
}: {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  fieldState: { errors: string[] }
}) => {
  const selectedValues =
    Array.isArray(fieldValue) && fieldValue.every((item) => typeof item === 'string') ? (fieldValue as string[]) : []

  return (
    <MultiSelect
      aria-invalid={!!fieldState.errors.length}
      value={selectedValues ?? []}
      options={toOptions(field.options) ?? []}
      onValueChange={fieldApi.handleChange}
      onBlur={fieldApi.handleBlur}
    />
  )
}

const CheckboxField = ({
  field,
  fieldApi,
  fieldValue,
  fieldState,
}: {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  fieldState: { errors: string[] }
}) => {
  return (
    <Checkbox
      id={field.key}
      name={fieldApi.name}
      checked={Boolean(fieldValue)}
      onCheckedChange={(checked) => fieldApi.handleChange(checked)}
      disabled={field.readonly}
      aria-invalid={!!fieldState.errors.length}
    />
  )
}

const HtmlRenderer = ({ field }: { field: SchemaField }) => {
  return field.options ? (
    <div className={'font-light text-sm'} dangerouslySetInnerHTML={{ __html: field.options }} />
  ) : null
}

const Header = ({ field }: { field: SchemaField }) => {
  return (
    <div>
      <Separator className="my-2" />
      <div className={'font-extrabold text-sm'}>{field.groupLabel}</div>
    </div>
  )
}

const Color = ({
  field,
  fieldApi,
  fieldValue,
  fieldState,
}: {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  fieldState: { errors: string[] }
}) => {
  return (
    <div className="flex items-center gap-2">
      <Input
        type="color"
        id={field.key}
        name={fieldApi.name}
        value={String(fieldValue || '')}
        onBlur={fieldApi.handleBlur}
        onChange={(e) => fieldApi.handleChange(e.target.value)}
        disabled={field.readonly}
        aria-invalid={!!fieldState.errors.length}
        className="w-12 h-10 p-1 border rounded cursor-pointer"
      />
      <Input
        type="text"
        placeholder={field.placeholder}
        value={String(fieldValue || '')}
        onBlur={fieldApi.handleBlur}
        onChange={(e) => fieldApi.handleChange(e.target.value)}
        disabled={field.readonly}
        aria-invalid={!!fieldState.errors.length}
        className="flex-1"
      />
    </div>
  )
}

const Notice = ({ field }: { field: SchemaField }) => {
  return (
    <Alert variant="default">
      <AlertCircle />
      <AlertTitle>{field.label}</AlertTitle>
      <AlertDescription>{field.description}</AlertDescription>
    </Alert>
  )
}

const layoutVariants = cva('', {
  variants: {
    layout: {
      '1-column': 'grid grid-cols-1 gap-4',
      '2-column': 'grid grid-cols-2 gap-4',
      '3-column': 'grid grid-cols-3 gap-4',
      '4-column': 'grid grid-cols-4 gap-4',
      '5-column': 'grid grid-cols-5 gap-4',
      '6-column': 'grid grid-cols-6 gap-4',
      '7-column': 'grid grid-cols-7 gap-4',
      '8-column': 'grid grid-cols-8 gap-4',
      '9-column': 'grid grid-cols-9 gap-4',
      '10-column': 'grid grid-cols-10 gap-4',
      '11-column': 'grid grid-cols-11 gap-4',
      '12-column': 'grid grid-cols-12 gap-4',
    },
  },
  defaultVariants: {
    layout: '2-column',
  },
})

const RepeaterField = ({
  field,
  fieldApi,
  fieldValue,
  // _onFieldValueChange - unused in this implementation
  formValues,
  defaultValues,
  groupName,
}: {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  _onFieldValueChange?: (name: string, value: FieldValue) => void
  formValues?: Record<string, unknown>
  defaultValues?: Record<string, unknown>
  groupName?: SettingGroupName
}) => {
  const fieldsArray = useMemo(() => {
    if (Array.isArray(fieldValue)) {
      // Check if it's an array of objects (RepeaterItem[])
      if (fieldValue.every((item) => typeof item === 'object' && item !== null)) {
        return fieldValue as RepeaterItem[]
      }
    }
    return []
  }, [fieldValue])

  const layout = '2-column' // Default layout since field.layout doesn't exist in SchemaField type

  const handleAddItem = useCallback(() => {
    const firstItem = fieldsArray?.[0]
    const newFieldData = firstItem ? Object.fromEntries(Object.entries(firstItem).map(([key]) => [key, null])) : {}
    const newItem: RepeaterItem = { ...newFieldData, id: `item-${Date.now()}` }
    const newArray = [...fieldsArray, newItem]
    fieldApi.handleChange(newArray as FieldValue)
  }, [fieldsArray, fieldApi])

  const handleRemoveItem = useCallback(
    (idx: number) => {
      const newArray = fieldsArray.filter((_, index) => index !== idx)
      fieldApi.handleChange(newArray as FieldValue)
    },
    [fieldsArray, fieldApi]
  )

  const handleItemFieldChange = useCallback(
    (itemIndex: number, fieldKey: string, value: FieldValue) => {
      const newArray = [...fieldsArray]
      const currentItem = newArray[itemIndex]
      if (currentItem) {
        newArray[itemIndex] = { ...currentItem, [fieldKey]: value }
        fieldApi.handleChange(newArray as FieldValue)
      }
    },
    [fieldsArray, fieldApi]
  )

  return (
    <div className="flex flex-col gap-y-4">
      {fieldsArray?.map((item, idx) => {
        return (
          <div key={item?.id || `item-${idx}`} className="flex flex-col gap-y-6 border border-border rounded-lg p-4">
            <div className="flex justify-between items-center">
              <div className="flex items-center gap-x-2">
                <Grip size={20} className="text-foreground" />
                <p className="text-base font-medium text-foreground">{`Item ${idx + 1}`}</p>
              </div>

              <div className="flex items-center gap-x-2">
                <ConfirmAction onConfirm={() => handleRemoveItem(idx)}>
                  <Button variant="ghost" size="icon">
                    <Trash2Icon className="w-4 h-4" />
                  </Button>
                </ConfirmAction>
              </div>
            </div>

            {field.fieldGroups?.map((group) => {
              return (
                <section key={`${field.key}-${item?.id || `item-${idx}`}`} className={layoutVariants({ layout })}>
                  {group?.fields?.map((subField) => {
                    const shouldShow = Object.entries(subField?.showIf ?? {}).every(([key, expectedValue]) => {
                      return formValues?.[key] === expectedValue
                    })

                    const shouldHide = Object.entries(subField?.hideIf ?? {}).some(([key, expectedValue]) => {
                      return formValues?.[key] === expectedValue
                    })

                    if (!shouldShow || shouldHide || Boolean(subField?.hidden)) {
                      return null
                    }

                    const subFieldApi: SimpleFieldApi = {
                      name: `${field.key}.${idx}.${subField.key}`,
                      state: {
                        value:
                          item && typeof item === 'object' && subField.key in item
                            ? (item[subField.key] as FieldValue)
                            : undefined,
                        meta: { errors: [] },
                      },
                      handleBlur: () => {},
                      handleChange: (value) => handleItemFieldChange(idx, subField.key, value),
                    }

                    return (
                      <FieldRenderer
                        key={`group-${group?.key}-field-${subField?.key}`}
                        field={{ ...subField, key: `${field.key}.${idx}.${subField?.key}` }}
                        fieldApi={subFieldApi}
                        defaultValues={defaultValues}
                        groupName={groupName}
                      />
                    )
                  })}
                </section>
              )
            })}
          </div>
        )
      })}

      <Button
        onClick={handleAddItem}
        type="button"
        variant="outline"
        size="sm"
        className="flex items-center justify-center gap-x-1 w-full"
      >
        <Plus size={18} />
        <span>{`Add ${field.fieldGroups?.[0]?.label || 'Item'}`}</span>
      </Button>
    </div>
  )
}

const TelField = ({
  // _field - unused in this implementation
  fieldApi,
  fieldValue,
  fieldState,
}: {
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  fieldState: { errors: string[] }
}) => {
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
      } catch {}
    }

    loadData()
  }, [dataService])

  // Initialize from field value ONCE when component first loads
  useEffect(() => {
    if (fieldValue && typeof fieldValue === 'string' && !initialized && jsonData.length > 0) {
      try {
        const phoneUtil = parsePhoneNumber(fieldValue)
        if (phoneUtil?.countryCallingCode && phoneUtil?.nationalNumber) {
          setSelectedCountryCode(`+${phoneUtil.countryCallingCode}`)
          setPhoneNumber(phoneUtil.nationalNumber)
          setInitialized(true)
        }
      } catch {}
    }
  }, [fieldValue, initialized, jsonData])

  const selectedCountry =
    jsonData?.find((item) => item.dialCode === selectedCountryCode) ||
    jsonData?.find((item) => item.code === 'US') ||
    jsonData?.[0]

  const handleCountryChange = (dialCode: string) => {
    setSelectedCountryCode(dialCode)
    const newValue = `${dialCode}${phoneNumber}`
    fieldApi.handleChange(newValue)
  }

  const handlePhoneNumberChange = (number: string) => {
    setPhoneNumber(number)
    const newValue = `${selectedCountryCode}${number}`
    fieldApi.handleChange(newValue)
  }

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
          const inputValue = e.target.value.replace(/\D/g, '') // Remove non-digits
          handlePhoneNumberChange(inputValue)
        }}
      />
    </div>
  )
}

const ImageField = ({
  // _field - unused in this implementation
  fieldApi,
  fieldValue,
}: {
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
}) => {
  const { openMediaUploader } = useWordPressMediaUploader()

  return (
    <Button variant="outline" type="button" onClick={() => openMediaUploader(fieldApi.handleChange)}>
      <CloudUpload />
      {fieldValue ? 'Change Image' : 'Select Image'}
    </Button>
  )
}

// Main field renderer component
export const FieldRenderer = ({
  field,
  fieldApi,
  onOpenSubFields,
  onFieldValueChange,
  formValues,
  defaultValues,
  groupName,
}: FieldRendererProps) => {
  const fieldValue = fieldApi.state.value
  const fieldState = fieldApi.state.meta

  const renderFieldContent = (): React.ReactNode => {
    switch (field.type) {
      case 'text':
        return <TextField field={field} fieldApi={fieldApi} fieldValue={fieldValue} fieldState={fieldState} />

      case 'textarea':
        return <TextareaField field={field} fieldApi={fieldApi} fieldValue={fieldValue} fieldState={fieldState} />

      case 'number':
        return <NumberField field={field} fieldApi={fieldApi} fieldValue={fieldValue} fieldState={fieldState} />

      case 'select':
      case 'advancedselect':
      case 'countryselect':
        return <SelectField field={field} fieldApi={fieldApi} fieldValue={fieldValue} fieldState={fieldState} />

      case 'multiselect':
        return <MultiselectField field={field} fieldApi={fieldApi} fieldValue={fieldValue} fieldState={fieldState} />

      case 'checkbox':
        return <CheckboxField field={field} fieldApi={fieldApi} fieldValue={fieldValue} fieldState={fieldState} />

      case 'html':
        return <HtmlRenderer field={field} />

      case 'header':
        return <Header field={field} />

      case 'color':
        return <Color field={field} fieldApi={fieldApi} fieldValue={fieldValue} fieldState={fieldState} />

      case 'notice':
        return <Notice field={field} />

      case 'repeater':
        return (
          <RepeaterField
            field={field}
            fieldApi={fieldApi}
            fieldValue={fieldValue}
            _onFieldValueChange={onFieldValueChange}
            defaultValues={defaultValues}
            formValues={formValues}
            groupName={groupName}
          />
        )

      case 'tel':
        return <TelField fieldApi={fieldApi} fieldValue={fieldValue} fieldState={fieldState} />

      case 'image':
        return <ImageField fieldApi={fieldApi} fieldValue={fieldValue} />

      default:
        return <div>Unsupported field type: {field.type}</div>
    }
  }

  const subFields = fieldHelpers.getFieldSubFields(field)
  const hasSubFields = subFields.length > 0

  return (
    <div className="flex items-center gap-2">
      <div className="flex-1">
        {field.type === 'notice' ? (
          renderFieldContent()
        ) : (
          <FieldWrapper field={field} fieldState={fieldState}>
            {renderFieldContent()}
          </FieldWrapper>
        )}
      </div>
      {hasSubFields && onOpenSubFields && (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => onOpenSubFields(field)}
          className="h-8 w-8 p-0 text-muted-foreground hover:text-foreground"
        >
          <Settings className="h-4 w-4" />
        </Button>
      )}
    </div>
  )
}

export { fieldHelpers }
export type { FieldHelperFunctions, FieldRendererProps, FieldValue, RepeaterItem }
