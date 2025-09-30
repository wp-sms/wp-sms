import { useSuspenseQueries } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { AlertCircle } from 'lucide-react'

import { SchemaForm } from '@/components/form/schema-form'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { SettingsSchemaSkeleton } from '@/components/ui/skeleton'
import { getSchemaByGroup } from '@/services/settings/get-schema-by-group'
import { getSettingsValuesByGroup } from '@/services/settings/get-settings-values-by-group'
import { useSaveSettingsValues } from '@/services/settings/use-save-settings-values'

export const Route = createFileRoute('/settings/_layout/$name')({
  loader: ({ context, params }) => {
    const name = (params.name || 'general') as SettingGroupName
    return Promise.all([
      context.queryClient.ensureQueryData(getSchemaByGroup({ groupName: name || 'general' })),
      context.queryClient.ensureQueryData(getSettingsValuesByGroup({ groupName: name })),
    ])
  },
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
  const name = Route.useParams().name as SettingGroupName

  const [schemaResult, valuesResult] = useSuspenseQueries({
    queries: [getSchemaByGroup({ groupName: name }), getSettingsValuesByGroup({ groupName: name })],
  })

  const { mutateAsync } = useSaveSettingsValues({ groupName: (name ?? 'general') as SettingGroupName })

  const schema = schemaResult.data.data.data
  const defaultValues = valuesResult.data.data.data

  const handleSubmit = async (values: Record<string, unknown>) => {
    await mutateAsync(values)
  }

  return <SchemaForm formSchema={schema} defaultValues={defaultValues} onSubmit={handleSubmit} />
}
