import React, { useEffect, useCallback } from 'react'
import { Save, X, Loader2 } from 'lucide-react'
import { Button } from '../ui/button'
import { useSettings } from '@/context/SettingsContext'
import { useToast } from '../ui/toaster'
import { __ } from '@/lib/utils'

export default function FloatingSaveBar() {
  const { hasChanges, isSaving, saveSettings, resetChanges } = useSettings()
  const { toast } = useToast()

  const handleSave = useCallback(async () => {
    const result = await saveSettings()

    if (result.success) {
      toast({
        title: __('Settings saved'),
        variant: 'success',
      })
    } else {
      toast({
        title: __('Error saving settings'),
        description: result.error || __('Please try again.'),
        variant: 'destructive',
      })
    }
  }, [saveSettings, toast])

  // Keyboard shortcut: Cmd+S (Mac) or Ctrl+S (Windows/Linux) to save
  useEffect(() => {
    const handleKeyDown = (event) => {
      if ((event.metaKey || event.ctrlKey) && event.key === 's') {
        event.preventDefault()
        if (hasChanges && !isSaving) {
          handleSave()
        }
      }
    }

    window.addEventListener('keydown', handleKeyDown)
    return () => window.removeEventListener('keydown', handleKeyDown)
  }, [hasChanges, isSaving, handleSave])

  const handleDiscard = () => {
    resetChanges()
    toast({
      title: __('Changes discarded'),
    })
  }

  if (!hasChanges) {
    return null
  }

  return (
    <div className="wsms-border-t wsms-border-border wsms-bg-card wsms-px-6 wsms-py-3 wsms-shrink-0">
      <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4">
        <div
          className="wsms-flex wsms-items-center wsms-gap-2"
          role="status"
          aria-live="polite"
          aria-atomic="true"
        >
          <span className="wsms-text-[13px] wsms-text-muted-foreground">
            {__('You have unsaved changes')}
          </span>
        </div>

        <div className="wsms-flex wsms-items-center wsms-gap-2">
          <Button
            variant="ghost"
            size="sm"
            onClick={handleDiscard}
            disabled={isSaving}
          >
            <X className="wsms-h-4 wsms-w-4 wsms-mr-1" aria-hidden="true" />
            {__('Discard')}
          </Button>

          <Button
            size="sm"
            onClick={handleSave}
            disabled={isSaving}
            aria-busy={isSaving}
          >
            {isSaving ? (
              <>
                <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-1 wsms-animate-spin" aria-hidden="true" />
                {__('Saving...')}
              </>
            ) : (
              <>
                <Save className="wsms-h-4 wsms-w-4 wsms-mr-1" aria-hidden="true" />
                {__('Save Changes')}
              </>
            )}
          </Button>
        </div>
      </div>
    </div>
  )
}
