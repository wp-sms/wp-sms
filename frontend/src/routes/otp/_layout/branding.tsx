import { useSuspenseQuery } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { AlertCircle } from 'lucide-react'

import { SchemaForm } from '@/components/form/schema-form'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { SettingsSchemaSkeleton } from '@/components/ui/skeleton'
import { getSchemaByGroup } from '@/services/settings/get-schema-by-group'
import { getSettingsValuesByGroup } from '@/services/settings/get-settings-values-by-group'
import { useSaveSettingsValues } from '@/services/settings/use-save-settings-values'

export const Route = createFileRoute('/otp/_layout/branding')({
  loader: ({ context }) =>
    Promise.all([
      context.queryClient.ensureQueryData(getSchemaByGroup({ groupName: 'otp-branding', include_hidden: true })),
      context.queryClient.ensureQueryData(getSettingsValuesByGroup({ groupName: 'otp-branding' })),
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
  const { data: result } = useSuspenseQuery(getSchemaByGroup({ groupName: 'otp-branding', include_hidden: true }))
  const { data: valuesResult } = useSuspenseQuery(getSettingsValuesByGroup({ groupName: 'otp-branding' }))
  const { mutateAsync } = useSaveSettingsValues({ groupName: 'otp-channel', include_hidden: true })

  const schema = result.data.data

  const handleSubmit = async (values: Record<string, unknown>) => {
    await mutateAsync(values)
  }

  return <SchemaForm formSchema={schema} defaultValues={valuesResult?.data?.data ?? {}} onSubmit={handleSubmit} />
}
