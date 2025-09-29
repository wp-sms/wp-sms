import { useStore } from '@tanstack/react-form'
import { Settings } from 'lucide-react'
import { useCallback, useEffect, useRef } from 'react'

import type { AppFormType } from '@/hooks/use-application-form'
import { withForm } from '@/hooks/use-form'
import type { SchemaField } from '@/types/settings/group-schema'

import { Button } from '../ui/button'

interface FieldRendererProps {
  schema: SchemaField
  onOpenSubFields?: (field: SchemaField) => void
  onSubmit?: (values: Record<string, unknown>) => Promise<void>
}

interface AutoSaveWrapperProps {
  form: AppFormType
  schema: SchemaField
  onSubmit?: (values: Record<string, unknown>) => Promise<void>
  children: React.ReactNode
}

const AutoSaveWrapper = ({ form, schema, onSubmit, children }: AutoSaveWrapperProps) => {
  const autoSaveTimeout = useRef<ReturnType<typeof setTimeout> | null>(null)
  const fieldValue = useStore(form.baseStore, (state) => (state.values as Record<string, unknown>)[schema.key])
  const previousValue = useRef(fieldValue)

  const handleAutoSave = useCallback(async () => {
    if (!onSubmit || !schema.auto_save_and_refresh) {
      return
    }

    try {
      const autoSaveData = { [schema.key]: fieldValue }
      await onSubmit(autoSaveData)
    } catch (error) {
      console.error('Auto-save failed:', error)
    }
  }, [onSubmit, schema.auto_save_and_refresh, schema.key, fieldValue])

  useEffect(() => {
    if (!schema.auto_save_and_refresh || fieldValue === previousValue.current) {
      return
    }

    if (autoSaveTimeout.current) {
      clearTimeout(autoSaveTimeout.current)
    }

    autoSaveTimeout.current = setTimeout(() => {
      handleAutoSave()
    }, 500)

    previousValue.current = fieldValue

    return () => {
      if (autoSaveTimeout.current) {
        clearTimeout(autoSaveTimeout.current)
      }
    }
  }, [fieldValue, schema.auto_save_and_refresh, handleAutoSave])

  return <>{children}</>
}

export const FieldRenderer = withForm({
  props: {
    schema: {} as SchemaField,
    onOpenSubFields: () => {},
    onSubmit: async () => {},
  } as FieldRendererProps,
  render: ({ form, ...props }) => {
    const { schema, onOpenSubFields, onSubmit } = props as FieldRendererProps
    const subFields = schema.sub_fields || []
    const hasSubFields = subFields.length > 0

    const renderFieldContent = () => {
      switch (schema.type) {
        case 'text':
          return <form.AppField name={schema.key} children={(field) => <field.TextField schema={schema} />} />

        case 'textarea':
          return <form.AppField name={schema.key} children={(field) => <field.TextareaField schema={schema} />} />

        case 'number':
          return <form.AppField name={schema.key} children={(field) => <field.NumberField schema={schema} />} />

        case 'select':
        case 'advancedselect':
        case 'countryselect':
          return <form.AppField name={schema.key} children={(field) => <field.SelectField schema={schema} />} />

        case 'multiselect':
          return <form.AppField name={schema.key} children={(field) => <field.MultiselectField schema={schema} />} />

        case 'checkbox':
          return <form.AppField name={schema.key} children={(field) => <field.CheckboxField schema={schema} />} />

        case 'html':
          return <form.AppField name={schema.key} children={(field) => <field.HtmlRenderer schema={schema} />} />

        case 'header':
          return <form.AppField name={schema.key} children={(field) => <field.Header schema={schema} />} />

        case 'color':
          return <form.AppField name={schema.key} children={(field) => <field.ColorField schema={schema} />} />

        case 'notice':
          return <form.AppField name={schema.key} children={(field) => <field.Notice schema={schema} />} />

        case 'repeater':
          return (
            <form.AppField
              name={schema.key}
              children={(field) => <field.RepeaterField schema={schema} form={form} />}
            />
          )

        case 'tel':
          return <form.AppField name={schema.key} children={(field) => <field.TelField schema={schema} />} />

        case 'image':
          return <form.AppField name={schema.key} children={(field) => <field.ImageField schema={schema} />} />

        default:
          return <div>Unsupported field type: {schema.type}</div>
      }
    }

    return (
      <AutoSaveWrapper form={form} schema={schema} onSubmit={onSubmit}>
        <div className="flex items-center gap-2">
          <div className="flex-1">{renderFieldContent()}</div>
          {hasSubFields && onOpenSubFields && (
            <Button
              type="button"
              variant="ghost"
              size="sm"
              onClick={() => onOpenSubFields(schema)}
              className="h-8 w-8 p-0 text-muted-foreground hover:text-foreground"
            >
              <Settings />
            </Button>
          )}
        </div>
      </AutoSaveWrapper>
    )
  },
})
