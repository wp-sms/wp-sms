import { useForm } from '@tanstack/react-form'
import { useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { AlertCircle, X } from 'lucide-react'
import { useState } from 'react'

import { FormField } from '@/components/form/new/form-field'
import { GroupTitle } from '@/components/layout/group-title'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle } from '@/components/ui/drawer'
import { getSchemaByGroup } from '@/services/settings/get-schema-by-group'
import type { SchemaField } from '@/types/settings/group-schema'

export const Route = createFileRoute('/otp/_layout/authentication-channels')({
  loader: ({ context }) =>
    context.queryClient.ensureQueryData(getSchemaByGroup({ groupName: 'otp-channel', include_hidden: true })),
  component: RouteComponent,
})

function RouteComponent() {
  const { data: result } = useSuspenseQuery(getSchemaByGroup({ groupName: 'otp-channel', include_hidden: true }))

  const [drawerOpen, setDrawerOpen] = useState(false)
  const [selectedField, setSelectedField] = useState<SchemaField | null>(null)

  const groupSchema = result.data.data

  console.log(groupSchema)

  const form = useForm({
    onSubmit: async ({ value }) => {
      // Add your submission logic here
      await new Promise((resolve) => setTimeout(resolve, 2000)) // Simulate API call

      console.log('Form submitted with values:', value)
    },
  })

  // Helper function to get sub-fields
  const getFieldSubFields = (field: SchemaField) => {
    return field.sub_fields || []
  }

  const handleOpenSubFields = (field: SchemaField) => {
    setSelectedField(field)
    setDrawerOpen(true)
  }

  const renderField = (field: SchemaField, isSubField = false) => {
    return (
      <form.Field
        key={field.key}
        name={field.key}
        children={(fieldApi) => (
          <FormField field={field} fieldApi={fieldApi} isSubField={isSubField} onOpenSubFields={handleOpenSubFields} />
        )}
      />
    )
  }

  if (!groupSchema) {
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
        className="flex flex-col gap-y-4"
      >
        <GroupTitle label={groupSchema.label} icon={groupSchema.icon} />

        {groupSchema.sections.map((section, index) => (
          <Card key={`${section?.id}-${index}`} className="flex flex-col gap-y-8">
            <CardHeader>
              <CardTitle>{section.title}</CardTitle>
              {section.subtitle && <CardDescription>{section.subtitle}</CardDescription>}
            </CardHeader>
            <CardContent className="flex flex-col gap-y-8">
              {section.fields?.map((field) => {
                // Check if field should be shown based on showIf/hideIf conditions
                const shouldShow = Object.entries(field.showIf ?? {}).every(([key, expectedValue]) => {
                  return form.getFieldValue(key) === expectedValue
                })

                const shouldHide = Object.entries(field.hideIf ?? {}).some(([key, expectedValue]) => {
                  return form.getFieldValue(key) === expectedValue
                })

                if (!shouldShow || shouldHide || field.hidden) {
                  return null
                }

                return renderField(field)
              })}
            </CardContent>
          </Card>
        ))}

        <div className="flex items-center gap-x-3 sticky bottom-0 bg-background p-3 z-50 mt-2">
          <Button disabled={!form.state.isDirty} type="submit">
            Save Changes
          </Button>

          <Button disabled={!form.state.isDirty} type="reset" variant="secondary" onClick={() => form.reset()}>
            Reset
          </Button>
        </div>
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
            {selectedField && getFieldSubFields(selectedField).length > 0 && (
              <div className="space-y-6">
                {getFieldSubFields(selectedField).map((field) => renderField(field, false))}
              </div>
            )}
          </div>
        </DrawerContent>
      </Drawer>
    </>
  )
}
