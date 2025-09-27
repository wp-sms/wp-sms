import { useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { AlertCircle, X } from 'lucide-react'
import { useState } from 'react'

import { FieldRenderer } from '@/components/form/field-renderer'
import { SchemaForm } from '@/components/form/schema-form'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import { Drawer, DrawerContent, DrawerHeader, DrawerTitle } from '@/components/ui/drawer'
import { SettingsSchemaSkeleton } from '@/components/ui/skeleton'
import { useApplicationForm } from '@/hooks/use-application-form'
import { getSchemaByGroup } from '@/services/settings/get-schema-by-group'
import { getSettingsValuesByGroup } from '@/services/settings/get-settings-values-by-group'
import { useSaveSettingsValues } from '@/services/settings/use-save-settings-values'
import type { SchemaField } from '@/types/settings/group-schema'

export const Route = createFileRoute('/otp/_layout/authentication-channels')({
  loader: ({ context }) =>
    Promise.all([
      context.queryClient.ensureQueryData(getSchemaByGroup({ groupName: 'otp-channel', include_hidden: true })),
      context.queryClient.ensureQueryData(getSettingsValuesByGroup({ groupName: 'otp-channel' })),
    ]),
  component: RouteComponent,
  pendingComponent: () => <SettingsSchemaSkeleton />,
  errorComponent: () => (
    <Alert>
      <AlertCircle className="h-4 w-4" />
      <AlertDescription>Something went wrong!</AlertDescription>
    </Alert>
  ),
})

function RouteComponent() {
  const { data: result } = useSuspenseQuery(getSchemaByGroup({ groupName: 'otp-channel', include_hidden: true }))
  const { data: valuesResult } = useSuspenseQuery(getSettingsValuesByGroup({ groupName: 'otp-channel' }))
  const { mutateAsync } = useSaveSettingsValues({ groupName: 'otp-channel', include_hidden: true })

  const [drawerOpen, setDrawerOpen] = useState(false)
  const [selectedField, setSelectedField] = useState<SchemaField | null>(null)

  const schema = result.data.data

  const handleSubmit = async (values: Record<string, unknown>) => {
    await mutateAsync(values)
  }

  const handleFieldAction = (field: SchemaField) => {
    setSelectedField(field)
    setDrawerOpen(true)
  }

  const { form, shouldShowField, getSubFields } = useApplicationForm({
    defaultValues: valuesResult?.data?.data ?? {},
    onSubmit: handleSubmit,
    formSchema: schema,
  })

  return (
    <>
      <SchemaForm
        formSchema={schema}
        defaultValues={valuesResult?.data?.data ?? {}}
        onSubmit={handleSubmit}
        onFieldAction={handleFieldAction}
      />

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
            {selectedField && getSubFields(selectedField).length > 0 && (
              <div className="space-y-6">
                {getSubFields(selectedField).map((field) => {
                  if (!shouldShowField(field)) return null
                  return <FieldRenderer form={form} schema={field} onOpenSubFields={handleFieldAction} />
                })}
              </div>
            )}
          </div>
        </DrawerContent>
      </Drawer>
    </>
  )
}
