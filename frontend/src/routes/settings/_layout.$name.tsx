import { createFileRoute } from '@tanstack/react-router'
import { useEffect } from 'react'
import { FormProvider, useForm } from 'react-hook-form'

import { SettingsDynamicForm } from '@/components/settings/dynamic-form'
import { SettingsFormActions } from '@/components/settings/form-actions'
import { useStableCallback } from '@/hooks/use-stable-callback'
import { useGetGroupSchema } from '@/services/settings/use-get-group-schema'
import { useGetGroupValues } from '@/services/settings/use-get-group-values'

export const Route = createFileRoute('/settings/_layout/$name')({
  component: RouteComponent,
})

function RouteComponent() {
  const { name } = Route.useParams()

  const {
    data: groupSchema,
    isLoading: isGroupSchemaLoading,
    isRefetching: isGroupSchemaRefetching,
  } = useGetGroupSchema({
    params: {
      groupName: name ?? 'general',
    },
  })

  const {
    data: groupValues,
    isLoading: isGroupValuesLoading,
    isRefetching: isGroupValuesRefetching,
  } = useGetGroupValues({
    params: {
      groupName: name ?? 'general',
    },
  })

  const form = useForm({
    defaultValues: {},
  })

  const initForm = useStableCallback(async () => {
    if (groupValues?.data && groupSchema?.data) {
      form.reset(groupValues?.data ?? {})
    }
  }, [groupValues?.data, groupSchema?.data, form])

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
