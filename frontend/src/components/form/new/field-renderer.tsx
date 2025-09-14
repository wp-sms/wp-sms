import { AlertCircle, Settings } from 'lucide-react'

import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Input } from '@/components/ui/input'
import { MultiSelect } from '@/components/ui/multiselect'
import {
  Select,
  SelectContent,
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { toOptions } from '@/lib/to-options'
import type { SchemaField } from '@/types/settings/group-schema'

import { FieldWrapper } from './field-wrapper'

// Specific types for field rendering
type FieldValue = string | number | boolean | string[] | null | undefined

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
  isSubField?: boolean
  onOpenSubFields?: (field: SchemaField) => void
}

type FieldHelperFunctions = {
  getFieldOptions: (field: SchemaField) => Record<string, string>
  getFieldPlaceholder: (field: SchemaField) => string
  getFieldStep: (field: SchemaField) => number | null
  getFieldRows: (field: SchemaField) => number | null
  getFieldSubFields: (field: SchemaField) => SchemaField[]
}

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

// Error display component
const FieldError = ({ errors }: { errors: string[] }) => {
  if (errors.length === 0) return null

  return (
    <Alert variant="destructive">
      <AlertCircle className="h-4 w-4" />
      <AlertDescription>{errors.join(', ')}</AlertDescription>
    </Alert>
  )
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
      >
        <SelectValue id={field.key} className="w-full" placeholder={field.placeholder} />
      </SelectTrigger>
      <SelectContent>
        {toOptions(field.options)?.map((item) => {
          if (item?.children) {
            return (
              <SelectGroup key={`select-group-${item.value}`}>
                <SelectLabel>{item.label}</SelectLabel>

                {item.children?.map((child) => {
                  return (
                    <SelectItem key={`group-select-item-${child.value}`} value={String(child.value)}>
                      {child.label}
                    </SelectItem>
                  )
                })}
              </SelectGroup>
            )
          }

          return (
            <SelectItem key={`select-item-${item.value}`} value={item?.value}>
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
  const selectedValues = Array.isArray(fieldValue) ? fieldValue : []

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
}: {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
}) => {
  return (
    <Checkbox
      id={field.key}
      name={fieldApi.name}
      checked={Boolean(fieldValue)}
      onCheckedChange={(checked) => fieldApi.handleChange(checked)}
      disabled={field.readonly}
    />
  )
}

// Main field renderer component
export const FieldRenderer = ({ field, fieldApi, isSubField = false, onOpenSubFields }: FieldRendererProps) => {
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
    }
  }

  const subFields = fieldHelpers.getFieldSubFields(field)
  const hasSubFields = subFields.length > 0

  return (
    <div className="flex items-center gap-2">
      <div className="flex-1">
        <FieldWrapper field={field} fieldState={fieldState}>
          {renderFieldContent()}
        </FieldWrapper>
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
export type { FieldHelperFunctions, FieldRendererProps, FieldValue }
