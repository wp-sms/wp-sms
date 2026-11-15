import { useState, useCallback } from 'react'
import { getWpSettings } from '@/lib/utils'
import { adminNoticesApi } from '@/api/adminNoticesApi'

const MODAL_ID = 'welcome-premium'

/**
 * The popup no longer opens on its own. The setup wizard already shows the All-in-One comparison, so
 * showing this older tour right after it repeats the same offer. It still opens from the
 * window.wpSmsOpenAioModal() trigger.
 */
function computeInitialOpen() {
  return false
}

/**
 * Hook managing the All-in-One upgrade modal state
 */
export function useAllInOneModal() {
  const { aioModal = {} } = getWpSettings()
  const [isOpen, setIsOpen] = useState(computeInitialOpen)

  const addons = aioModal.addons || []
  const isPremium = aioModal.isPremium || false

  const close = useCallback(async () => {
    setIsOpen(false)
    try {
      await adminNoticesApi.markModalSeen(MODAL_ID)
    } catch (error) {
      console.error('Failed to mark AIO modal as seen:', error)
    }
  }, [])

  const open = useCallback(() => {
    setIsOpen(true)
  }, [])

  return { isOpen, close, open, addons, isPremium }
}
