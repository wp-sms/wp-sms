import { Input } from '@/components/ui/input'
import type { FieldValue, SchemaField } from '@/types/settings/group-schema'

import type { SimpleFieldApi } from '../field-renderer'

type ColorFieldProps = {
  field: SchemaField
  fieldApi: SimpleFieldApi
  fieldValue: FieldValue
  fieldState: { errors: string[] }
}

export const ColorField = ({ field, fieldApi, fieldValue, fieldState }: ColorFieldProps) => {
  return (
    <div className="flex items-center gap-2">
      <Input
        type="color"
        id={field.key}
        name={fieldApi.name}
        defaultValue={String(field.default || '')}
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
        defaultValue={String(field.default || '')}
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
