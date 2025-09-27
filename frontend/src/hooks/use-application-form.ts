import type { GroupSchema, SchemaField } from '@/types/settings/group-schema'

import { useAppForm } from './use-form'

export type UseSchemaFormOptions = {
  defaultValues: Record<string, unknown>
  formSchema?: GroupSchema | null
  onSubmit: (values: Record<string, unknown>) => Promise<void>
}

export type AppFormType = ReturnType<typeof useAppForm>

export const useApplicationForm = ({ defaultValues, formSchema, onSubmit }: UseSchemaFormOptions) => {
  const form = useAppForm({
    defaultValues,
    validators: {},
    onSubmit: async () => {
      const dirtyValues = getDirtyFormValues(form, formSchema)

      if (Object.keys(dirtyValues).length === 0) {
        return
      }

      await onSubmit(dirtyValues)
    },
  })

  const getDirtyFormValues = (
    form: Pick<AppFormType, 'getFieldMeta' | 'getFieldValue'>,
    schema?: GroupSchema | null
  ) => {
    if (!schema?.sections) {
      return {}
    }

    const collectAllFieldKeys = (fields: SchemaField[] = []): string[] => {
      return fields.flatMap((field) => [field.key, ...collectAllFieldKeys(field.sub_fields || [])])
    }

    const allFieldKeys = schema.sections.flatMap((section) => collectAllFieldKeys(section.fields || []))

    const dirtyFieldNames = allFieldKeys.filter((key) => Boolean(form.getFieldMeta?.(key)?.isDirty))

    return dirtyFieldNames.reduce<Record<string, unknown>>((acc, key) => {
      acc[key] = form.getFieldValue(key)
      return acc
    }, {})
  }

  const shouldShowField = (field: SchemaField) => {
    const shouldShow = Object.entries(field.showIf ?? {}).every(([key, expectedValue]) => {
      return form.getFieldValue(key) === expectedValue
    })

    const shouldHide = Object.entries(field.hideIf ?? {}).some(([key, expectedValue]) => {
      return form.getFieldValue(key) === expectedValue
    })

    return shouldShow && !shouldHide && !field.hidden
  }

  const getSubFields = (field: SchemaField) => {
    return field.sub_fields || []
  }

  return {
    form,
    shouldShowField,
    getSubFields,
  }
}
