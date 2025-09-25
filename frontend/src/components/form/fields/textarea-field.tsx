import { Textarea } from '@/components/ui/textarea'
import type { FieldValue, SchemaField } from '@/types/settings/group-schema'

import type { SimpleFieldApi } from '../field-renderer'

type TextareaFieldProps = {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  fieldState: { errors: string[] }
}

export const TextareaField = ({ field, fieldApi, fieldValue, fieldState }: TextareaFieldProps) => {
  return (
    <Textarea
      id={field.key}
      name={fieldApi.name}
      placeholder={field.placeholder}
      defaultValue={String(field.default || '')}
      value={String(fieldValue || '')}
      onBlur={fieldApi.handleBlur}
      onChange={(e) => fieldApi.handleChange(e.target.value)}
      disabled={field.readonly}
      rows={field.rows || 3}
      aria-invalid={!!fieldState.errors.length}
    />
  )
}
