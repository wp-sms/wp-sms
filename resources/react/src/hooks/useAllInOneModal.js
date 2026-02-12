import { useState, useCallback } from 'react'
import { getWpSettings } from '@/lib/utils'
import { adminNoticesApi } from '@/api/adminNoticesApi'

const MODAL_ID = 'welcome-premium'

/**
 * Compute initial open state once (avoids flash)
 */
function computeInitialOpen() {
  const { aioModal = {}, features = {} } = getWpSettings()
  const seen = aioModal.seen || false
  const isPremium = aioModal.isPremium || false
  const wizardCompleted = features.wizardCompleted || false
  return !seen && !isPremium && wizardCompleted
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
