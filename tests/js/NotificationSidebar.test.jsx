import React from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { NotificationSidebar } from '@/components/notifications/NotificationSidebar'

jest.mock('@wordpress/i18n', () => ({
  __: (text) => text === 'Close notifications' ? 'Close "notifications"' : text,
}))

describe('NotificationSidebar', () => {
  test('focuses the close button when its translated label contains quotes', async () => {
    render(
      <NotificationSidebar
        isOpen
        onClose={jest.fn()}
        onDismiss={jest.fn()}
        onDismissAll={jest.fn()}
      />
    )

    const closeButton = screen.getByLabelText('Close "notifications"')

    await waitFor(() => {
      expect(closeButton).toHaveFocus()
    })
  })
})
