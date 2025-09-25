import { MultiSelect } from '@/components/ui/multiselect'
import { toOptions } from '@/lib/to-options'
import type { FieldValue, SchemaField } from '@/types/settings/group-schema'

import type { SimpleFieldApi } from '../field-renderer'

type MultiselectFieldProps = {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  fieldState: { errors: string[] }
}

export const MultiselectField = ({ field, fieldApi, fieldValue, fieldState }: MultiselectFieldProps) => {
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
