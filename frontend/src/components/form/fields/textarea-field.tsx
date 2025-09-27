import { useStore } from '@tanstack/react-form'

import { Textarea } from '@/components/ui/textarea'
import { useFieldContext } from '@/context/form-context'
import type { SchemaField } from '@/types/settings/group-schema'

import { FieldWrapper } from '../field-wrapper'

type TextareaFieldProps = {
  schema: SchemaField
}

export const TextareaField = ({ schema }: TextareaFieldProps) => {
  const field = useFieldContext<string>()

  const errors = useStore(field.store, (state) => state.meta.errors)

  return (
    <FieldWrapper schema={schema} errors={errors}>
      <Textarea
        id={schema.key}
        placeholder={schema.placeholder}
        defaultValue={String(schema.default || '')}
        value={String(field.state.value || '')}
        onBlur={field.handleBlur}
        onChange={(e) => field.handleChange(e.target.value)}
        disabled={schema.readonly}
        rows={schema.rows || 3}
        aria-invalid={!!errors.length}
      />
    </FieldWrapper>
  )
}
