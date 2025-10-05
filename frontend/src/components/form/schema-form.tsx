import { AlertCircle, X } from 'lucide-react'
import { useEffect, useState } from 'react'

import { GroupTitle } from '@/components/layout/group-title'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle } from '@/components/ui/drawer'
import { getDirtyFormValues, useAppForm } from '@/hooks/use-form'
import type { GroupSchema, SchemaField } from '@/types/settings/group-schema'

import { Tabs, TabsContent, TabsList, TabsTrigger } from '../ui/tabs'
import { FieldRenderer } from './field-renderer'

type SchemaFormProps = {
  formSchema: GroupSchema | null
  defaultValues: Record<string, unknown>
  onSubmit: (values: Record<string, unknown>) => Promise<void>
}

export const SchemaForm = ({ formSchema, defaultValues, onSubmit }: SchemaFormProps) => {
  const [drawerOpen, setDrawerOpen] = useState(false)
  const [selectedField, setSelectedField] = useState<SchemaField | null>(null)

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

  useEffect(() => {
    form.reset(defaultValues)
  }, [defaultValues, form])

  const handleFieldAction = (field: SchemaField) => {
    setSelectedField(field)
    setDrawerOpen(true)
  }

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

        {formSchema.layout === 'default' ? (
          formSchema.sections.map((section, index) => (
            <Card key={`${section?.id}-${index}`} className="flex flex-col gap-y-8">
              <CardHeader>
                <CardTitle>{section.title}</CardTitle>
                {section.subtitle && <CardDescription>{section.subtitle}</CardDescription>}
              </CardHeader>
              <CardContent className="flex flex-col gap-y-8">
                {section.fields?.map((field) => (
                  <FieldRenderer
                    key={field.key}
                    form={form}
                    schema={field}
                    onOpenSubFields={handleFieldAction}
                    onSubmit={onSubmit}
                  />
                ))}
              </CardContent>
            </Card>
          ))
        ) : (
          <Tabs defaultValue={formSchema.sections[0].id} className="w-full">
            <TabsList>
              {formSchema.sections.map((section) => {
                return (
                  <TabsTrigger value={section.id} key={section.id}>
                    {section.title}
                  </TabsTrigger>
                )
              })}
            </TabsList>
            {formSchema.sections.map((section, index) => (
              <TabsContent key={section.id} value={section.id}>
                <Card key={`${section?.id}-${index}`} className="flex flex-col gap-y-8">
                  <CardHeader>
                    <CardTitle>{section.title}</CardTitle>
                    {section.subtitle && <CardDescription>{section.subtitle}</CardDescription>}
                  </CardHeader>
                  <CardContent className="flex flex-col gap-y-8">
                    {section.fields?.map((field) => (
                      <FieldRenderer
                        key={field.key}
                        form={form}
                        schema={field}
                        onOpenSubFields={handleFieldAction}
                        onSubmit={onSubmit}
                      />
                    ))}
                  </CardContent>
                </Card>
              </TabsContent>
            ))}
          </Tabs>
        )}

        <form.AppForm>
          <form.FormActions />
        </form.AppForm>
      </form>

      {/* Sub-fields Drawer */}
      <Drawer open={drawerOpen} onOpenChange={setDrawerOpen} direction="right">
        <DrawerContent className="h-full w-96 mr-0 rounded-none">
          <DrawerHeader className="flex flex-row items-center justify-between">
            <DrawerTitle>{selectedField ? `${selectedField.label} Settings` : 'Field Settings'}</DrawerTitle>
            <Button variant="ghost" size="sm" onClick={() => setDrawerOpen(false)} className="h-8 w-8 p-0">
              <X className="h-4 w-4" />
            </Button>
          </DrawerHeader>
          <div className="flex-1 overflow-y-auto p-4">
            {selectedField && selectedField.sub_fields && selectedField.sub_fields.length > 0 && (
              <div className="space-y-6">
                {selectedField.sub_fields.map((field) => (
                  <FieldRenderer key={field.key} form={form} schema={field} onOpenSubFields={handleFieldAction} />
                ))}
              </div>
            )}
          </div>
        </DrawerContent>
      </Drawer>
    </>
  )
}
