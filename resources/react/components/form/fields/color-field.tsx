import { useStore } from '@tanstack/react-form'

import { Input } from '@/components/ui/input'
import { useFieldContext } from '@/context/form-context'
import type { SchemaField } from '@/types/settings/group-schema'

import { FieldWrapper } from '../field-wrapper'

type ColorFieldProps = {
  schema: SchemaField
}

export const ColorField = ({ schema }: ColorFieldProps) => {
  const field = useFieldContext<string>()

  const errors = useStore(field.store, (state) => state.meta.errors)

  return (
    <FieldWrapper errors={errors} schema={schema}>
      <div className="flex items-center gap-2">
        <Input
          type="color"
          id={schema.key}
          defaultValue={String(schema.default || '#000')}
          value={String(field.state.value)}
          onBlur={field.handleBlur}
          onChange={(e) => field.handleChange(e.target.value)}
          disabled={schema.readonly}
          aria-invalid={!!errors.length}
          className="w-12 h-10 p-1 border rounded cursor-pointer"
        />
        <Input
          type="text"
          placeholder={schema.placeholder}
          defaultValue={String(schema.default || '#000')}
          value={String(field.state.value)}
          onBlur={field.handleBlur}
          onChange={(e) => field.handleChange(e.target.value)}
          disabled={schema.readonly}
          aria-invalid={!!errors.length}
          className="flex-1"
        />
      </div>
    </FieldWrapper>
  )
}
