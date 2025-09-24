import { AlertCircle, Save } from 'lucide-react'

import type { FieldValue } from '@/components/form/new/field-renderer'
import { FormField as FormFieldComponent } from '@/components/form/new/form-field'
import { GroupTitle } from '@/components/layout/group-title'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { type FormSchema, useSchemaForm } from '@/hooks/use-schema-form'
import type { SchemaField } from '@/types/settings/group-schema'

type SchemaFormProps = {
  schema: FormSchema | null
  defaultValues: Record<string, unknown>
  onSubmit: (values: Record<string, unknown>) => Promise<void>
  onFieldAction?: (field: SchemaField) => void
}

export const SchemaForm = ({ schema, defaultValues, onSubmit, onFieldAction }: SchemaFormProps) => {
  const { form, shouldShowField } = useSchemaForm({
    defaultValues,
    onSubmit,
    schema,
  })

  const renderField = (field: SchemaField, isSubField = false) => {
    if (!shouldShowField(field)) {
      return null
    }

    return (
      <form.Field
        key={field.key}
        name={field.key}
        children={(fieldApi) => {
          const adaptedFieldApi = {
            name: fieldApi.name,
            state: {
              value: fieldApi.state.value as FieldValue,
              meta: {
                errors: Array.isArray(fieldApi.state.meta.errors)
                  ? (fieldApi.state.meta.errors as unknown[]).filter((e): e is string => typeof e === 'string')
                  : [],
              },
            },
            handleBlur: fieldApi.handleBlur,
            handleChange: (value: unknown) => fieldApi.handleChange(value),
          }

          return (
            <FormFieldComponent
              field={field}
              fieldApi={adaptedFieldApi}
              onOpenSubFields={onFieldAction}
              defaultValues={defaultValues}
            />
          )
        }}
      />
    )
  }

  if (!schema) {
    return (
      <div className="container mx-auto py-8">
        <Alert variant="destructive">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>No schema data available.</AlertDescription>
        </Alert>
      </div>
    )
  }

  return (
    <form
      onSubmit={(e) => {
        e.preventDefault()
        e.stopPropagation()
        form.handleSubmit()
      }}
      className="flex flex-col gap-y-6"
    >
      <GroupTitle label={schema.label || ''} />

      {schema.sections.map((section, index) => (
        <Card key={`${section?.id}-${index}`} className="flex flex-col gap-y-8">
          <CardHeader>
            <CardTitle>{section.title}</CardTitle>
            {section.subtitle && <CardDescription>{section.subtitle}</CardDescription>}
          </CardHeader>
          <CardContent className="flex flex-col gap-y-8">
            {section.fields?.map((field) => renderField(field))}
          </CardContent>
        </Card>
      ))}

      <form.Subscribe selector={(state) => state.isDirty}>
        {(isDirty) => (
          <div className="flex items-center justify-end gap-x-3 sticky bottom-0 bg-background p-3 z-50 mt-2">
            <Button
              disabled={!isDirty || form.state.isSubmitting}
              type="reset"
              variant="secondary"
              onClick={() => form.reset()}
            >
              Reset
            </Button>

            <Button disabled={!isDirty || form.state.isSubmitting} type="submit">
              <Save />
              Save Changes
            </Button>
          </div>
        )}
      </form.Subscribe>
    </form>
  )
}
