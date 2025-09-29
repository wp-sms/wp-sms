import { AlertCircle } from 'lucide-react'
import { useEffect } from 'react'

import { GroupTitle } from '@/components/layout/group-title'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { useApplicationForm } from '@/hooks/use-application-form'
import type { GroupSchema, SchemaField } from '@/types/settings/group-schema'

import { FieldRenderer } from './field-renderer'

type SchemaFormProps = {
  formSchema: GroupSchema | null
  defaultValues: Record<string, unknown>
  onSubmit: (values: Record<string, unknown>) => Promise<void>
  onFieldAction?: (field: SchemaField) => void
}

export const SchemaForm = ({ formSchema, defaultValues, onSubmit, onFieldAction }: SchemaFormProps) => {
  const { form, shouldShowField } = useApplicationForm({
    defaultValues,
    onSubmit,
    formSchema,
  })

  useEffect(() => {
    form.reset(defaultValues)
  }, [defaultValues, form])

  if (!formSchema) {
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
      <GroupTitle label={formSchema.label || ''} />

      {formSchema.sections.map((section, index) => (
        <Card key={`${section?.id}-${index}`} className="flex flex-col gap-y-8">
          <CardHeader>
            <CardTitle>{section.title}</CardTitle>
            {section.subtitle && <CardDescription>{section.subtitle}</CardDescription>}
          </CardHeader>
          <CardContent className="flex flex-col gap-y-8">
            {section.fields?.map((field) => {
              if (!shouldShowField(field)) return null
              return <FieldRenderer form={form} schema={field} onOpenSubFields={onFieldAction} onSubmit={onSubmit} />
            })}
          </CardContent>
        </Card>
      ))}

      <form.AppForm>
        <form.FormActions />
      </form.AppForm>
    </form>
  )
}
