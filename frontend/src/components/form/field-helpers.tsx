import type { SchemaField } from '@/types/settings/group-schema'

type FieldHelperFunctions = {
  getFieldOptions: (field: SchemaField) => Record<string, string>
  getFieldPlaceholder: (field: SchemaField) => string
  getFieldStep: (field: SchemaField) => number | null
  getFieldRows: (field: SchemaField) => number | null
  getFieldSubFields: (field: SchemaField) => SchemaField[]
}

export const fieldHelpers: FieldHelperFunctions = {
  getFieldOptions: (field: SchemaField): Record<string, string> => {
    if (Array.isArray(field.options)) {
      return {}
    }

    // Convert FieldOption to Record<string, string>
    const options: Record<string, string> = {}
    Object.entries(field.options).forEach(([key, value]) => {
      options[key] = typeof value === 'string' ? value : key
    })

    return options
  },

  getFieldPlaceholder: (field: SchemaField): string => {
    return field.placeholder || ''
  },

  getFieldStep: (field: SchemaField): number | null => {
    return field.step
  },

  getFieldRows: (field: SchemaField): number | null => {
    return field.rows
  },

  getFieldSubFields: (field: SchemaField): SchemaField[] => {
    return field.sub_fields || []
  },
}
