import { Settings } from 'lucide-react'

import { withForm } from '@/hooks/use-form'
import type { SchemaField } from '@/types/settings/group-schema'

import { Button } from '../ui/button'

interface FieldRendererProps {
  schema: SchemaField
  onOpenSubFields?: (field: SchemaField) => void
}

export const FieldRenderer = withForm({
  props: {
    schema: {} as SchemaField,
    onOpenSubFields: () => {},
  } as FieldRendererProps,
  render: ({ form, ...props }) => {
    const { schema, onOpenSubFields } = props as FieldRendererProps
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
    )
  },
})
