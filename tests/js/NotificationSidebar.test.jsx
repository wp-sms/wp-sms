import React from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { NotificationSidebar } from '@/components/notifications/NotificationSidebar'

jest.mock('@wordpress/i18n', () => ({
  __: (text) => text === 'Close notifications' ? 'Close "notifications"' : text,
  _n: (single, plural, count) => count === 1 ? single : plural,
  sprintf: (text, count) => text.replace('%d', String(count)),
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

  test('keeps a closed sidebar out of the accessibility tree and tab order', () => {
    render(
      <NotificationSidebar
        isOpen={false}
        onClose={jest.fn()}
        onDismiss={jest.fn()}
        onDismissAll={jest.fn()}
      />
    )

    expect(screen.queryByRole('dialog')).not.toBeInTheDocument()
    expect(screen.getByRole('dialog', { hidden: true })).toHaveAttribute('inert')
  })

  test('uses a translatable plural phrase for the unread count', () => {
    render(
      <NotificationSidebar
        isOpen
        inboxNotifications={[{ id: 1 }, { id: 2 }]}
        onClose={jest.fn()}
        onDismiss={jest.fn()}
        onDismissAll={jest.fn()}
      />
    )

    expect(screen.getByText('2 unread notifications')).toBeInTheDocument()
  })
})
