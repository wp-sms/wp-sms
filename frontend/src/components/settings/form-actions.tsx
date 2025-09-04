import { useCallback } from 'react'
import { useFormContext } from 'react-hook-form'
import { toast } from 'sonner'

import { Button } from '@/components/ui/button'
import { pickFormDirtyValues } from '@/lib/pick-form-dirty-values'
import { useSaveSettingsValues } from '@/services/settings/use-save-settings-values'

export const SettingsFormActions = () => {
  const { formState, reset, handleSubmit, setError } = useFormContext()

  const saveSettings = useSaveSettingsValues()

  const handleSave = useCallback(
    async (values: Record<string, any>) => {
      const valuesToSave = pickFormDirtyValues(formState.dirtyFields, values)

      try {
        await saveSettings.mutateAsync(valuesToSave)
        reset(values)
      } catch (error: any) {
        toast.error('Failed to save settings')

        for (const field in error?.response?.data?.data?.fields) {
          setError(field, {
            message: error?.response?.data?.data?.fields[field],
          })
        }
      }
    },
    [formState.dirtyFields, saveSettings, reset]
  )

  const handleReset = useCallback(() => {
    reset(formState.defaultValues)
  }, [reset, formState])

  return (
    <div className="flex items-center gap-x-3 sticky bottom-0 bg-background p-3 z-50 mt-2">
      <Button disabled={!formState.isDirty} type="submit" onClick={handleSubmit(handleSave)}>
        Save Changes
      </Button>

      <Button disabled={!formState.isDirty} type="reset" variant="secondary" onClick={handleReset}>
        Reset
      </Button>
    </div>
  )
}
