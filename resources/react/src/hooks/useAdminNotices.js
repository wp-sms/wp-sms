import { useState, useCallback, useMemo } from 'react'
import { getWpSettings } from '@/lib/utils'
import { adminNoticesApi } from '@/api/adminNoticesApi'

/**
 * Hook for managing admin notices in the React dashboard.
 * Initializes from server-rendered data (no API fetch needed).
 */
export function useAdminNotices() {
  const [notices, setNotices] = useState(() => {
    const { adminNotices } = getWpSettings()
    return Array.isArray(adminNotices) ? adminNotices : []
  })

  const hasNotices = notices.length > 0

  const dismissNotice = useCallback(async (id, store) => {
    try {
      await adminNoticesApi.dismiss(id, store)
      setNotices((prev) => prev.filter((n) => n.id !== id))
    } catch (err) {
      console.error('Failed to dismiss admin notice:', err)
    }
  }, [])

  const executeAction = useCallback(async (id, action) => {
    try {
      await adminNoticesApi.executeAction(id, action)
      // Remove notice after successful action
      setNotices((prev) => prev.filter((n) => n.id !== id))
    } catch (err) {
      console.error('Failed to execute admin notice action:', err)
    }
  }, [])

  return useMemo(
    () => ({ notices, dismissNotice, executeAction, hasNotices }),
    [notices, dismissNotice, executeAction, hasNotices]
  )
}

export default useAdminNotices
