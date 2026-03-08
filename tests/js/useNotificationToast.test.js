import { renderHook, act } from '@testing-library/react'
import { useNotificationToast } from '@/hooks/useNotificationToast'

describe('useNotificationToast', () => {
  beforeEach(() => {
    jest.useFakeTimers()
  })

  afterEach(() => {
    jest.useRealTimers()
  })

  describe('initialization', () => {
    test('initializes with no notification', () => {
      const { result } = renderHook(() => useNotificationToast())

      expect(result.current.notification).toBeNull()
      expect(result.current.hasNotification).toBe(false)
    })
  })

  describe('show notifications', () => {
    test('shows a notification', () => {
      const { result } = renderHook(() => useNotificationToast())

      act(() => {
        result.current.show('Test message', 'info')
      })

      expect(result.current.notification).toEqual({
        message: 'Test message',
        type: 'info',
      })
      expect(result.current.hasNotification).toBe(true)
      expect(result.current.isInfo).toBe(true)
    })

    test('showSuccess sets success type', () => {
      const { result } = renderHook(() => useNotificationToast())

      act(() => {
        result.current.showSuccess('Success!')
      })

      expect(result.current.notification.type).toBe('success')
      expect(result.current.isSuccess).toBe(true)
    })

    test('showError sets error type', () => {
      const { result } = renderHook(() => useNotificationToast())

      act(() => {
        result.current.showError('Error!')
      })

      expect(result.current.notification.type).toBe('error')
      expect(result.current.isError).toBe(true)
    })

    test('showWarning sets warning type', () => {
      const { result } = renderHook(() => useNotificationToast())

      act(() => {
        result.current.showWarning('Warning!')
      })

      expect(result.current.notification.type).toBe('warning')
      expect(result.current.isWarning).toBe(true)
    })

    test('showFromError handles Error objects', () => {
      const { result } = renderHook(() => useNotificationToast())

      act(() => {
        result.current.showFromError(new Error('Something went wrong'))
      })

      expect(result.current.notification.message).toBe('Something went wrong')
      expect(result.current.notification.type).toBe('error')
    })

    test('showFromError handles string errors', () => {
      const { result } = renderHook(() => useNotificationToast())

      act(() => {
        result.current.showFromError('String error')
      })

      expect(result.current.notification.message).toBe('String error')
    })
  })

  describe('auto-clear', () => {
    test('auto-clears after default duration', () => {
      const { result } = renderHook(() => useNotificationToast())

      act(() => {
        result.current.show('Test', 'info')
      })

      expect(result.current.notification).not.toBeNull()

      act(() => {
        jest.advanceTimersByTime(5000)
      })

      expect(result.current.notification).toBeNull()
    })

    test('uses custom autoClearMs', () => {
      const { result } = renderHook(() => useNotificationToast({ autoClearMs: 2000 }))

      act(() => {
        result.current.show('Test', 'info')
      })

      act(() => {
        jest.advanceTimersByTime(1999)
      })

      expect(result.current.notification).not.toBeNull()

      act(() => {
        jest.advanceTimersByTime(1)
      })

      expect(result.current.notification).toBeNull()
    })

    test('errors have longer duration by default', () => {
      const { result } = renderHook(() => useNotificationToast({ autoClearMs: 2000 }))

      act(() => {
        result.current.showError('Error!')
      })

      // Error duration is 1.5x default
      act(() => {
        jest.advanceTimersByTime(2999)
      })

      expect(result.current.notification).not.toBeNull()

      act(() => {
        jest.advanceTimersByTime(1)
      })

      expect(result.current.notification).toBeNull()
    })

    test('persistent option prevents auto-clear', () => {
      const { result } = renderHook(() => useNotificationToast())

      act(() => {
        result.current.show('Persistent', 'info', { persistent: true })
      })

      act(() => {
        jest.advanceTimersByTime(10000)
      })

      expect(result.current.notification).not.toBeNull()
    })

    test('custom duration overrides default', () => {
      const { result } = renderHook(() => useNotificationToast())

      act(() => {
        result.current.show('Custom', 'info', { duration: 1000 })
      })

      act(() => {
        jest.advanceTimersByTime(999)
      })

      expect(result.current.notification).not.toBeNull()

      act(() => {
        jest.advanceTimersByTime(1)
      })

      expect(result.current.notification).toBeNull()
    })
  })

  describe('clear', () => {
    test('clears notification manually', () => {
      const { result } = renderHook(() => useNotificationToast())

      act(() => {
        result.current.show('Test', 'info')
      })

      expect(result.current.notification).not.toBeNull()

      act(() => {
        result.current.clear()
      })

      expect(result.current.notification).toBeNull()
    })

    test('clears pending timer when clearing manually', () => {
      const { result } = renderHook(() => useNotificationToast())

      act(() => {
        result.current.show('Test', 'info')
      })

      act(() => {
        result.current.clear()
      })

      // Show new notification
      act(() => {
        result.current.show('New', 'info')
      })

      // Old timer should not clear new notification
      act(() => {
        jest.advanceTimersByTime(4999)
      })

      expect(result.current.notification).not.toBeNull()
    })
  })

  describe('replacing notifications', () => {
    test('new notification replaces old one', () => {
      const { result } = renderHook(() => useNotificationToast())

      act(() => {
        result.current.show('First', 'info')
      })

      act(() => {
        result.current.show('Second', 'success')
      })

      expect(result.current.notification).toEqual({
        message: 'Second',
        type: 'success',
      })
    })

    test('new notification resets timer', () => {
      const { result } = renderHook(() => useNotificationToast({ autoClearMs: 2000 }))

      act(() => {
        result.current.show('First', 'info')
      })

      act(() => {
        jest.advanceTimersByTime(1500)
      })

      act(() => {
        result.current.show('Second', 'info')
      })

      // Should start new 2s timer
      act(() => {
        jest.advanceTimersByTime(1999)
      })

      expect(result.current.notification).not.toBeNull()

      act(() => {
        jest.advanceTimersByTime(1)
      })

      expect(result.current.notification).toBeNull()
    })
  })
})
