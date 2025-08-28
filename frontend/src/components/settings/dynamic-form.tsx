import { useWatch } from 'react-hook-form'
import type { GroupSchema } from '@/types/settings/group-schema'
import { ControlledFieldRenderer } from '@/components/form/controlled-field-renderer'
import { DynamicFieldsSkeleton } from './dynamic-fields-skeleton'
import { SettingsGroupTitle } from './group-title'
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { AlertCircle } from 'lucide-react'

export type SettingsDynamicFormProps = {
  groupSchema: GroupSchema | null | undefined
  isInitialLoading?: boolean
  isRefreshing?: boolean
}

export const SettingsDynamicForm: React.FC<SettingsDynamicFormProps> = ({
  groupSchema,
  isInitialLoading,
  isRefreshing,
}) => {
  const formValues = useWatch()

  if (isInitialLoading) {
    return <DynamicFieldsSkeleton />
  }

  if (!groupSchema) {
    return (
      <Alert>
        <AlertCircle className="h-4 w-4" />
        <AlertDescription>No settings schema available.</AlertDescription>
      </Alert>
    )
  }

  return (
    <div className="flex flex-col gap-y-4">
      <SettingsGroupTitle label={groupSchema?.label} icon={groupSchema?.icon} />

      {groupSchema?.sections?.map((section, idx) => {
        return (
          <Card key={`${section?.id}-${idx}`} className="flex flex-col gap-y-4">
            <CardHeader>
              <CardHeader>
                <CardTitle>{section?.title}</CardTitle>

                <CardDescription>{section?.subtitle}</CardDescription>
              </CardHeader>
            </CardHeader>

            <CardContent className="flex flex-col gap-y-8 max-w-3xl">
              {section?.fields?.map((field) => {
                const shouldShow = Object.entries(field?.showIf ?? {}).every(([key, expectedValue]) => {
                  return formValues[key] === expectedValue
                })

                const shouldHide = Object.entries(field?.hideIf ?? {}).some(([key, expectedValue]) => {
                  return formValues[key] === expectedValue
                })

                if (!shouldShow || shouldHide || Boolean(field?.hidden)) {
                  return null
                }

                return (
                  <ControlledFieldRenderer
                    isLoading={isRefreshing}
                    key={`section-${section?.id}-field-${field?.key}`}
                    schema={field}
                  />
                )
              })}
            </CardContent>
          </Card>
        )
      })}
    </div>
  )
}
