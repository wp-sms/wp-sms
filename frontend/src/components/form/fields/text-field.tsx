import { Input } from '@/components/ui/input'
import type { FieldValue, SchemaField } from '@/types/settings/group-schema'

import type { SimpleFieldApi } from '../field-renderer'

type TextFieldProps = {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  fieldState: { errors: string[] }
}

export const TextField = ({ field, fieldApi, fieldValue, fieldState }: TextFieldProps) => {
  return (
    <Input
      id={field.key}
      name={fieldApi.name}
      type="text"
      placeholder={field.placeholder}
      defaultValue={String(field.default || '')}
      value={String(fieldValue || '')}
      onBlur={fieldApi.handleBlur}
      onChange={(e) => fieldApi.handleChange(e.target.value)}
      disabled={field.readonly}
      aria-invalid={!!fieldState.errors.length}
    />
  )
}
