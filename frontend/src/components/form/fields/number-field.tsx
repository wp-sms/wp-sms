import { Input } from '@/components/ui/input'
import type { FieldValue, SchemaField } from '@/types/settings/group-schema'

import type { SimpleFieldApi } from '../field-renderer'

type NumberFieldProps = {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  fieldState: { errors: string[] }
}

export const NumberField = ({ field, fieldApi, fieldValue, fieldState }: NumberFieldProps) => {
  return (
    <Input
      id={field.key}
      name={fieldApi.name}
      type="number"
      min={field.min || undefined}
      max={field.max || undefined}
      step={field.step || undefined}
      defaultValue={String(field.default || '')}
      value={String(fieldValue || '')}
      onBlur={fieldApi.handleBlur}
      onChange={(e) => fieldApi.handleChange(parseFloat(e.target.value) || 0)}
      disabled={field.readonly}
      aria-invalid={!!fieldState.errors.length}
    />
  )
}
