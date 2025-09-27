import { useStore } from '@tanstack/react-form'

import { Checkbox } from '@/components/ui/checkbox'
import { useFieldContext } from '@/context/form-context'
import type { SchemaField } from '@/types/settings/group-schema'

import { FieldWrapper } from '../field-wrapper'

type CheckboxFieldProps = {
  schema: SchemaField
}

export const CheckboxField = ({ schema }: CheckboxFieldProps) => {
  const field = useFieldContext<boolean>()

  const errors = useStore(field.store, (state) => state.meta.errors)

  return (
    <FieldWrapper errors={errors} schema={schema}>
      <Checkbox
        id={schema.key}
        defaultChecked={Boolean(schema.default)}
        checked={Boolean(field.state.value)}
        onCheckedChange={(checked) => field.handleChange(checked === true)}
        onBlur={field.handleBlur}
        disabled={schema.readonly}
        aria-invalid={!!errors.length}
      />
    </FieldWrapper>
  )
}
