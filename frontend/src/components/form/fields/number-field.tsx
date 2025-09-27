import { useStore } from '@tanstack/react-form'

import { Input } from '@/components/ui/input'
import { useFieldContext } from '@/context/form-context'
import type { SchemaField } from '@/types/settings/group-schema'

import { FieldWrapper } from '../field-wrapper'

type NumberFieldProps = {
  schema: SchemaField
}

export const NumberField = ({ schema }: NumberFieldProps) => {
  const field = useFieldContext<number>()

  const errors = useStore(field.store, (state) => state.meta.errors)

  return (
    <FieldWrapper schema={schema} errors={errors}>
      <Input
        id={schema.key}
        type="number"
        min={schema.min || undefined}
        max={schema.max || undefined}
        step={schema.step || undefined}
        defaultValue={String(schema.default || '')}
        value={String(field.state.value || '')}
        onBlur={field.handleBlur}
        onChange={(e) => field.handleChange(parseFloat(e.target.value) || 0)}
        disabled={schema.readonly}
        aria-invalid={!!errors.length}
      />
    </FieldWrapper>
  )
}
