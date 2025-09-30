import { useStore } from '@tanstack/react-form'

import { Combobox } from '@/components/ui/combobox'
import { useFieldContext } from '@/context/form-context'
import { toOptions } from '@/lib/to-options'
import type { SchemaField } from '@/types/settings/group-schema'

import { FieldWrapper } from '../field-wrapper'

type SelectFieldProps = {
  schema: SchemaField
}

export const SelectField = ({ schema }: SelectFieldProps) => {
  const field = useFieldContext<string>()

  const errors = useStore(field.store, (state) => state.meta.errors)

  return (
    <FieldWrapper schema={schema} errors={errors}>
      <Combobox
        value={field.state.value}
        onValueChange={(value) => field.handleChange(value)}
        options={toOptions(schema.options)}
        disabled={schema.readonly}
        placeholder={schema.placeholder}
      />
    </FieldWrapper>
  )
}
