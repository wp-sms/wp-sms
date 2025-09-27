import { Save } from 'lucide-react'

import { useFormContext } from '@/context/form-context'

import { Button } from '../ui/button'

export const FormActions = () => {
  const form = useFormContext()

  return (
    <form.Subscribe selector={(state) => state.isDirty}>
      {(isDirty) => (
        <div className="flex items-center justify-end gap-x-3 sticky bottom-0 bg-background p-3 z-50 mt-2">
          <Button
            disabled={!isDirty || form.state.isSubmitting}
            type="reset"
            variant="secondary"
            onClick={() => form.reset()}
          >
            Reset
          </Button>

          <Button disabled={!isDirty || form.state.isSubmitting} type="submit">
            <Save />
            Save Changes
          </Button>
        </div>
      )}
    </form.Subscribe>
  )
}
