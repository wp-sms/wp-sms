import { useState, useCallback, useRef, useEffect } from 'react'

/**
 * Custom hook for managing notification/toast state
 * Consolidates notification display with auto-clear functionality
 *
 * @param {Object} options - Hook options
 * @param {number} options.autoClearMs - Auto-clear delay in ms (default: 5000, 0 to disable)
 * @returns {Object} Notification state and methods
 */
export function useNotificationToast({ autoClearMs = 5000 } = {}) {
  const [notification, setNotification] = useState(null)
  const timerRef = useRef(null)

  /**
   * Clear any existing timer
   */
  const clearTimer = useCallback(() => {
    if (timerRef.current) {
      clearTimeout(timerRef.current)
      timerRef.current = null
    }
  }, [])

  /**
   * Clear the notification
   */
  const clear = useCallback(() => {
    clearTimer()
    setNotification(null)
  }, [clearTimer])

  /**
   * Show a notification
   * @param {string} message - Notification message
   * @param {string} type - Notification type (success, error, warning, info)
   * @param {Object} options - Additional options
   * @param {number} options.duration - Override auto-clear duration
   * @param {boolean} options.persistent - If true, don't auto-clear
   */
  const show = useCallback(
    (message, type = 'info', options = {}) => {
      clearTimer()

      const { duration = autoClearMs, persistent = false } = options

      setNotification({ message, type })

      if (!persistent && duration > 0) {
        timerRef.current = setTimeout(() => {
          setNotification(null)
        }, duration)
      }
    },
    [autoClearMs, clearTimer]
  )

  /**
   * Show a success notification
   * @param {string} message - Success message
   * @param {Object} options - Additional options
   */
  const showSuccess = useCallback(
    (message, options = {}) => {
      show(message, 'success', options)
    },
    [show]
  )

  /**
   * Show an error notification
   * @param {string} message - Error message
   * @param {Object} options - Additional options
   */
  const showError = useCallback(
    (message, options = {}) => {
      // Errors are slightly longer by default
      show(message, 'error', { duration: autoClearMs * 1.5, ...options })
    },
    [show, autoClearMs]
  )

  /**
   * Show a warning notification
   * @param {string} message - Warning message
   * @param {Object} options - Additional options
   */
  const showWarning = useCallback(
    (message, options = {}) => {
      show(message, 'warning', options)
    },
    [show]
  )

  /**
   * Show an info notification
   * @param {string} message - Info message
   * @param {Object} options - Additional options
   */
  const showInfo = useCallback(
    (message, options = {}) => {
      show(message, 'info', options)
    },
    [show]
  )

  /**
   * Show notification from an error object
   * @param {Error|string} error - Error object or message
   * @param {Object} options - Additional options
   */
  const showFromError = useCallback(
    (error, options = {}) => {
      const message = error?.message || String(error) || 'An error occurred'
      showError(message, options)
    },
    [showError]
  )

  // Cleanup timer on unmount
  useEffect(() => {
    return () => {
      clearTimer()
    }
  }, [clearTimer])

  return {
    // State
    notification,
    hasNotification: notification !== null,
    isSuccess: notification?.type === 'success',
    isError: notification?.type === 'error',
    isWarning: notification?.type === 'warning',
    isInfo: notification?.type === 'info',

    // Actions
    show,
    showSuccess,
    showError,
    showWarning,
    showInfo,
    showFromError,
    clear,
  }
}

export default useNotificationToast
