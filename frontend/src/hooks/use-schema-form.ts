import { useForm } from '@tanstack/react-form'

type FormField = {
  key: string
  hidden?: boolean
  showIf?: Record<string, unknown> | null
  hideIf?: Record<string, unknown> | null
  sub_fields?: FormField[]
}

type FormSection = {
  id?: string
  title: string
  subtitle?: string
  fields?: FormField[]
}

type FormSchema = {
  label: string
  icon?: string
  sections: FormSection[]
}

type UseSchemaFormOptions = {
  defaultValues: Record<string, unknown>
  schema?: FormSchema | null
  onSubmit: (values: Record<string, unknown>) => Promise<void>
}

export const useSchemaForm = ({ defaultValues, schema, onSubmit }: UseSchemaFormOptions) => {
  const form = useForm({
    defaultValues,
    onSubmit: async () => {
      const dirtyValues = getDirtyFormValues(form, schema)

      if (Object.keys(dirtyValues).length === 0) {
        return
      }

      await onSubmit(dirtyValues)
    },
  })

  const getDirtyFormValues = (form: any, schema?: FormSchema | null) => {
    if (!schema?.sections) {
      return {}
    }

    const collectAllFieldKeys = (fields: FormField[] = []): string[] => {
      return fields.flatMap((field) => [field.key, ...collectAllFieldKeys(field.sub_fields || [])])
    }

    const allFieldKeys = schema.sections.flatMap((section) => collectAllFieldKeys(section.fields || []))

    const dirtyFieldNames = allFieldKeys.filter((key) => Boolean(form.getFieldMeta?.(key)?.isDirty))

    return dirtyFieldNames.reduce<Record<string, unknown>>((acc, key) => {
      acc[key] = form.getFieldValue(key)
      return acc
    }, {})
  }

  const shouldShowField = (field: FormField) => {
    const shouldShow = Object.entries(field.showIf ?? {}).every(([key, expectedValue]) => {
      return form.getFieldValue(key) === expectedValue
    })

    const shouldHide = Object.entries(field.hideIf ?? {}).some(([key, expectedValue]) => {
      return form.getFieldValue(key) === expectedValue
    })

    return shouldShow && !shouldHide && !field.hidden
  }

  const getSubFields = (field: FormField) => {
    return field.sub_fields || []
  }

  return {
    form,
    shouldShowField,
    getSubFields,
  }
}

export type { FormField, FormSchema, FormSection }
