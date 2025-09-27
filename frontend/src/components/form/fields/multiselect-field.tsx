import { useStore } from '@tanstack/react-form'

import { MultiSelect } from '@/components/ui/multiselect'
import { useFieldContext } from '@/context/form-context'
import { toOptions } from '@/lib/to-options'
import type { SchemaField } from '@/types/settings/group-schema'

type MultiselectFieldProps = {
  schema: SchemaField
}

export const MultiselectField = ({ schema }: MultiselectFieldProps) => {
  const field = useFieldContext<string[]>()

  const errors = useStore(field.store, (state) => state.meta.errors)

  const fieldValue = field.state.value

  const selectedValues =
    Array.isArray(fieldValue) && fieldValue.every((item) => typeof item === 'string') ? (fieldValue as string[]) : []

  return (
    <MultiSelect
      aria-invalid={!!errors.length}
      value={selectedValues ?? []}
      options={toOptions(schema.options) ?? []}
      onValueChange={field.handleChange}
      onBlur={field.handleBlur}
    />
  )
}
