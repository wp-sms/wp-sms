import { renderHook, waitFor } from '@testing-library/react'
import { useNotifications } from '@/hooks/useNotifications'
import { notificationsApi } from '@/api/notificationsApi'

jest.mock('@/api/notificationsApi', () => ({
  notificationsApi: { getNotifications: jest.fn() },
}))

test('inbox lists promo cards first and keeps the rest in order', async () => {
  notificationsApi.getNotifications.mockResolvedValue({
    notifications: [
      { id: 1, backgroundColor: 'info' },
      { id: 2, backgroundColor: 'warning' },
      { id: 3, backgroundColor: 'promo' },
      { id: 4, backgroundColor: 'promo', dismissed: true },
    ],
  })
  const { result } = renderHook(() => useNotifications())
  await waitFor(() => expect(result.current.inboxNotifications).toHaveLength(3))
  expect(result.current.inboxNotifications.map(n => n.id)).toEqual([3, 1, 2])
})
