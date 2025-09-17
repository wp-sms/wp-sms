import type { SchemaField } from '@/types/settings/group-schema'

import { FieldRenderer, type FieldValue, type SimpleFieldApi } from './field-renderer'

// Minimal shape we need from TanStack's FieldApi
type ExternalFieldApiLike = {
  name: string
  state: {
    value: FieldValue
    meta: {
      errors?: string[]
    }
  }
  handleBlur: () => void
  handleChange: (value: unknown) => void
}

type FormFieldProps = {
  field: SchemaField
  fieldApi: ExternalFieldApiLike
  isSubField?: boolean
  onOpenSubFields?: (field: SchemaField) => void
}

export const FormField = ({ field, fieldApi, isSubField = false, onOpenSubFields }: FormFieldProps) => {
  // Convert TanStack Form FieldApi to our SimpleFieldApi
  const simpleFieldApi: SimpleFieldApi = {
    name: fieldApi.name,
    state: {
      value: fieldApi.state.value,
      meta: {
        errors: fieldApi.state.meta.errors || [],
      },
    },
    handleBlur: fieldApi.handleBlur,
    // Normalize signature to match SimpleFieldApi
    handleChange: (value) => fieldApi.handleChange(value),
  }

  return (
    <FieldRenderer field={field} fieldApi={simpleFieldApi} isSubField={isSubField} onOpenSubFields={onOpenSubFields} />
  )
}

export type { FormFieldProps }
