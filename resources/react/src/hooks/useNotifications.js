import { useState, useEffect, useCallback } from 'react'
import { notificationsApi } from '../api/notificationsApi'

/**
 * Custom hook for managing notifications state
 *
 * @returns {object} Notifications state and actions
 */
export function useNotifications() {
  const [notifications, setNotifications] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)

  // Separate notifications into inbox and dismissed
  // Live offers (promo cards) always come first; the rest keep their order.
  const isOffer = n => n.backgroundColor === 'promo'
  const unreadNotifications = notifications.filter(n => !n.dismissed)
  const inboxNotifications = [
    ...unreadNotifications.filter(isOffer),
    ...unreadNotifications.filter(n => !isOffer(n)),
  ]
  const dismissedNotifications = notifications.filter(n => n.dismissed)
  const unreadCount = inboxNotifications.length
  const hasUnread = unreadCount > 0

  /**
   * Fetch notifications from API
   */
  const fetchNotifications = useCallback(async () => {
    try {
      setLoading(true)
      setError(null)
      const data = await notificationsApi.getNotifications()
      setNotifications(data.notifications || [])
    } catch (err) {
      console.error('Failed to fetch notifications:', err)
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [])

  /**
   * Dismiss a single notification
   * @param {number} id - Notification ID
   */
  const dismiss = useCallback(async (id) => {
    // Optimistic update
    setNotifications(prev =>
      prev.map(n => n.id === id ? { ...n, dismissed: true } : n)
    )

    try {
      await notificationsApi.dismissNotification(id)
    } catch (err) {
      console.error('Failed to dismiss notification:', err)
      // Revert on error
      setNotifications(prev =>
        prev.map(n => n.id === id ? { ...n, dismissed: false } : n)
      )
    }
  }, [])

  /**
   * Dismiss all notifications
   */
  const dismissAll = useCallback(async () => {
    // Store previous state for potential rollback
    const previousNotifications = [...notifications]

    // Optimistic update
    setNotifications(prev =>
      prev.map(n => (n.localOnly ? n : { ...n, dismissed: true }))
    )

    try {
      await notificationsApi.dismissAllNotifications()
    } catch (err) {
      console.error('Failed to dismiss all notifications:', err)
      // Revert on error
      setNotifications(previousNotifications)
    }
  }, [notifications])

  /**
   * Refetch notifications
   */
  const refetch = useCallback(() => {
    return fetchNotifications()
  }, [fetchNotifications])

  // Initial fetch
  useEffect(() => {
    fetchNotifications()
  }, [fetchNotifications])

  return {
    notifications,
    inboxNotifications,
    dismissedNotifications,
    unreadCount,
    hasUnread,
    loading,
    error,
    dismiss,
    dismissAll,
    refetch,
  }
}

export default useNotifications
