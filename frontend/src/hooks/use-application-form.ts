import { createFormHook, createFormHookContexts } from '@tanstack/react-form'

import { CheckboxField } from '@/components/form/fields/checkbox-field'
import { ColorField } from '@/components/form/fields/color-field'
import { Header, HtmlRenderer, Notice } from '@/components/form/fields/display-fields'
import { ImageField } from '@/components/form/fields/image-field'
import { MultiselectField } from '@/components/form/fields/multiselect-field'
import { NumberField } from '@/components/form/fields/number-field'
import { RepeaterField } from '@/components/form/fields/repeater-field'
import { SelectField } from '@/components/form/fields/select-field'
import { TelField } from '@/components/form/fields/tel-field'
import { TextField } from '@/components/form/fields/text-field'
import { TextareaField } from '@/components/form/fields/textarea-field'

export type FormField = {
  key: string
  hidden?: boolean
  showIf?: Record<string, unknown> | null
  hideIf?: Record<string, unknown> | null
  sub_fields?: FormField[]
}

export type FormSection = {
  id?: string
  title: string
  subtitle?: string
  fields?: FormField[]
}

export type FormSchema = {
  label: string
  icon?: string
  sections: FormSection[]
}

export type UseSchemaFormOptions = {
  defaultValues: Record<string, unknown>
  formSchema?: FormSchema | null
  onSubmit: (values: Record<string, unknown>) => Promise<void>
}

const { fieldContext, formContext } = createFormHookContexts()

const { useAppForm } = createFormHook({
  fieldComponents: {
    CheckboxField,
    ColorField,
    ImageField,
    MultiselectField,
    NumberField,
    RepeaterField,
    SelectField,
    TelField,
    TextField,
    TextareaField,
    HtmlRenderer,
    Header,
    Notice,
  },
  formComponents: {},
  fieldContext,
  formContext,
})

export type AppFormType = ReturnType<typeof useAppForm>

export const useApplicationForm = ({ defaultValues, formSchema, onSubmit }: UseSchemaFormOptions) => {
  const form = useAppForm({
    defaultValues,
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
    schema?: FormSchema | null
  ) => {
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
