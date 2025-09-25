import { Checkbox } from '@/components/ui/checkbox'
import type { FieldValue, SchemaField } from '@/types/settings/group-schema'

import type { SimpleFieldApi } from '../field-renderer'

type CheckboxFieldProps = {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  fieldState: { errors: string[] }
}

export const CheckboxField = ({ field, fieldApi, fieldValue, fieldState }: CheckboxFieldProps) => {
  return (
    <Checkbox
      id={field.key}
      name={fieldApi.name}
      defaultChecked={Boolean(field.default)}
      checked={Boolean(fieldValue)}
      onCheckedChange={(checked) => fieldApi.handleChange(checked)}
      disabled={field.readonly}
      aria-invalid={!!fieldState.errors.length}
    />
  )
}
