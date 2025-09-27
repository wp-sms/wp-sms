import { useStore } from '@tanstack/react-form'

import { Input } from '@/components/ui/input'
import { useFieldContext } from '@/context/form-context'
import type { SchemaField } from '@/types/settings/group-schema'

import { FieldWrapper } from '../field-wrapper'

type TextFieldProps = {
  schema: SchemaField
}

export const TextField = ({ schema }: TextFieldProps) => {
  const field = useFieldContext<string>()

  const errors = useStore(field.store, (state) => state.meta.errors)

  return (
    <FieldWrapper schema={schema} errors={errors}>
      <Input
        id={schema.key}
        type="text"
        placeholder={schema.placeholder}
        defaultValue={String(schema.default || '')}
        value={String(field.state.value || '')}
        onBlur={field.handleBlur}
        onChange={(e) => field.handleChange(e.target.value)}
        disabled={schema.readonly}
        aria-invalid={!!errors.length}
      />
    </FieldWrapper>
  )
}
