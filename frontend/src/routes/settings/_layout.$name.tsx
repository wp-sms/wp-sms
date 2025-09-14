import { useSuspenseQueries } from '@tanstack/react-query'
import { createFileRoute } from '@tanstack/react-router'
import { useEffect } from 'react'
import { FormProvider, useForm } from 'react-hook-form'

import { SettingsDynamicForm } from '@/components/settings/dynamic-form'
import { SettingsFormActions } from '@/components/settings/form-actions'
import { useStableCallback } from '@/hooks/use-stable-callback'
import { useGetGroupSchema } from '@/services/settings/use-get-group-schema'
import { useGetGroupValues } from '@/services/settings/use-get-group-values'

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

  useEffect(() => {
    initForm()
  }, [groupValues?.data, groupSchema?.data, initForm])

  return (
    <FormProvider {...form}>
      <div className="flex flex-col gap-y-4">
        <SettingsDynamicForm
          groupSchema={groupSchema?.data}
          isInitialLoading={isGroupSchemaLoading || isGroupValuesLoading}
          isRefreshing={isGroupSchemaRefetching || isGroupValuesRefetching}
        />

        <SettingsFormActions />
      </div>
    </FormProvider>
  )
}
