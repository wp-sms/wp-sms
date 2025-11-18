import { __ } from '@wordpress/i18n'
import { useBlocker } from '@tanstack/react-router'
import { AlertCircle } from 'lucide-react'
import { useEffect, useState } from 'react'

import { GroupTitle } from '@/components/layout/group-title'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { getDirtyFormValues, useAppForm } from '@/hooks/use-form'
import type { GroupSchema, SchemaField } from '@/types/settings/group-schema'

import { TagBadge } from '../ui/tag-badge'
import { FieldRenderer } from './field-renderer'
import { UnsavedChangesDialog } from './unsaved-changes-dialog'

type SchemaFormProps = {
  formSchema: GroupSchema | null
  defaultValues: Record<string, unknown>
  onSubmit: (values: Record<string, unknown>) => Promise<void>
  onFieldAction?: (field: SchemaField) => void
}

export const SchemaForm = ({ formSchema, defaultValues, onSubmit, onFieldAction }: SchemaFormProps) => {
  const [showDialog, setShowDialog] = useState(false)

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

  const { proceed, reset, status } = useBlocker({
    shouldBlockFn: () => form.state.isDirty,
    enableBeforeUnload: form.state.isDirty,
    withResolver: true,
  })

  useEffect(() => {
    if (status === 'blocked') {
      setShowDialog(true)
    }
  }, [status])

  const handleStay = () => {
    setShowDialog(false)
    reset!()
  }

  const handleDiscard = () => {
    setShowDialog(false)
    proceed!()
  }

  useEffect(() => {
    form.reset(defaultValues)
  }, [defaultValues, form])

  if (!formSchema) {
    return (
      <div className="container mx-auto py-8">
        <Alert variant="destructive">
          <AlertCircle className="h-4 w-4" />
          <AlertDescription>{__('No schema data available.', 'wp-sms')}</AlertDescription>
        </Alert>
      </div>
    )
  }

  return (
    <>
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
              <CardTitle>
                {section.title}
                {section.tag && <TagBadge className="ms-2" tag={section.tag} />}
              </CardTitle>
              {section.subtitle && <CardDescription>{section.subtitle}</CardDescription>}
            </CardHeader>
            <CardContent className="flex flex-col gap-y-8">
              {section.fields?.map((field) => (
                <FieldRenderer
                  key={field.key}
                  form={form}
                  schema={field}
                  onOpenSubFields={onFieldAction}
                  onSubmit={onSubmit}
                />
              ))}
            </CardContent>
          </Card>
        ))}

        <form.AppForm>
          <form.FormActions />
        </form.AppForm>
      </form>

      <UnsavedChangesDialog open={showDialog} onStay={handleStay} onDiscard={handleDiscard} />
    </>
  )
}
