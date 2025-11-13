import { useSuspenseQueries } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { AlertCircle } from 'lucide-react'

import { Chatbox } from '@/components/chatbox/chatbox'
import { SchemaForm } from '@/components/form/schema-form'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { SettingsSchemaSkeleton } from '@/components/ui/skeleton'
import { getSchemaByGroup } from '@/services/settings/get-schema-by-group'
import { getSettingsValuesByGroup } from '@/services/settings/get-settings-values-by-group'
import { useSaveSettingsValues } from '@/services/settings/use-save-settings-values'
import type { ChatboxSettings } from '@/types/chatbox'

export const Route = createFileRoute('/settings/_layout/$name')({
  component: RouteComponent,
  pendingComponent: () => <SettingsSchemaSkeleton />,
  errorComponent: () => (
    <Alert>
      <AlertCircle className="h-4 w-4" />
      <AlertDescription>Something went wrong!</AlertDescription>
    </Alert>
  ),
  loader: async ({ context, params }) => {
    const name = params.name as SettingGroupName

    // Prefetch both queries in parallel
    await Promise.all([
      context.queryClient.ensureQueryData(getSchemaByGroup({ groupName: name })),
      context.queryClient.ensureQueryData(getSettingsValuesByGroup({ groupName: name })),
    ])
  },
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
    await mutateAsync({
      ...values,
      ...(schema?.addon
        ? {
            addon: schema.addon,
          }
        : {}),
    })
  }

  const showChatbox = name === 'message_button'

  return (
    <>
      <SchemaForm formSchema={schema} defaultValues={defaultValues} onSubmit={handleSubmit} />
      {showChatbox && <Chatbox settings={defaultValues as Partial<ChatboxSettings>} />}
    </>
  )
}
