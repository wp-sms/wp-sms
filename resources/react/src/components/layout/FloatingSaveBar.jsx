import React, { useEffect, useCallback, useRef } from 'react'
import { Save, X, Loader2 } from 'lucide-react'
import { Button } from '../ui/button'
import { useSettings } from '@/context/SettingsContext'
import { useToast } from '../ui/toaster'
import { __ } from '@/lib/utils'

export default function FloatingSaveBar() {
  const { hasChanges, isSaving, saveSettings, resetChanges } = useSettings()
  const { toast } = useToast()
  const barRef = useRef(null)
  const rafIdRef = useRef(0)

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

  const scheduleLayoutUpdate = useCallback(() => {
    if (rafIdRef.current) {
      cancelAnimationFrame(rafIdRef.current)
    }

    rafIdRef.current = requestAnimationFrame(() => {
      const barEl = barRef.current
      const rootEl = document.getElementById('wpsms-settings-root')
      if (!barEl || !rootEl) return

      // Align the fixed bar to the main content area only (avoid covering the sidebar).
      const anchorEl = rootEl.querySelector('.wsms-main') || rootEl
      const rect = anchorEl.getBoundingClientRect()
      const viewportWidth =
        (window.visualViewport && window.visualViewport.width) ||
        document.documentElement.clientWidth ||
        window.innerWidth

      const insetStart = Math.max(0, rect.left)
      const insetEnd = Math.max(0, viewportWidth - rect.right)
      barEl.style.setProperty('--wsms-fixed-inset-start', `${insetStart}px`)
      barEl.style.setProperty('--wsms-fixed-inset-end', `${insetEnd}px`)
      barEl.style.setProperty('--wsms-fixed-left', `${insetStart}px`)
      barEl.style.setProperty('--wsms-fixed-width', `${Math.max(0, rect.width)}px`)

      // Ensure scrollable content doesn't get covered by the fixed bar.
      rootEl.style.setProperty('--wsms-floating-savebar-h', `${barEl.offsetHeight}px`)
    })
  }, [])

  // Toggle root class and keep layout variables in sync while the bar is visible.
  useEffect(() => {
    const rootEl = document.getElementById('wpsms-settings-root')
    if (!rootEl) return

    rootEl.classList.toggle('wsms-has-floating-savebar', Boolean(hasChanges))

    if (!hasChanges) {
      rootEl.style.removeProperty('--wsms-floating-savebar-h')
      return
    }

    scheduleLayoutUpdate()
    window.addEventListener('resize', scheduleLayoutUpdate)

    const resizeObserver =
      typeof ResizeObserver !== 'undefined' ? new ResizeObserver(scheduleLayoutUpdate) : null

    if (resizeObserver) {
      resizeObserver.observe(rootEl)
      const anchorEl = rootEl.querySelector('.wsms-main')
      if (anchorEl) resizeObserver.observe(anchorEl)
      if (barRef.current) resizeObserver.observe(barRef.current)
    }

    const mutationObserver =
      typeof MutationObserver !== 'undefined'
        ? new MutationObserver(scheduleLayoutUpdate)
        : null

    // WP admin menu collapse/expand toggles body classes and changes #wpcontent offsets.
    if (mutationObserver) {
      mutationObserver.observe(document.body, {
        attributes: true,
        attributeFilter: ['class'],
      })
    }

    return () => {
      window.removeEventListener('resize', scheduleLayoutUpdate)
      resizeObserver?.disconnect()
      mutationObserver?.disconnect()
    }
  }, [hasChanges, scheduleLayoutUpdate])

  useEffect(() => {
    return () => {
      if (rafIdRef.current) cancelAnimationFrame(rafIdRef.current)
    }
  }, [])

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
    <div
      ref={barRef}
      className="wsms-floating-savebar wsms-border-t wsms-border-border wsms-bg-card wsms-px-6 wsms-py-3"
    >
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
            <X className="wsms-h-4 wsms-w-4 wsms-me-1" aria-hidden="true" />
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
                <Loader2 className="wsms-h-4 wsms-w-4 wsms-me-1 wsms-animate-spin" aria-hidden="true" />
                {__('Saving...')}
              </>
            ) : (
              <>
                <Save className="wsms-h-4 wsms-w-4 wsms-me-1" aria-hidden="true" />
                {__('Save Changes')}
              </>
            )}
          </Button>
        </div>
      </div>
    </div>
  )
}
