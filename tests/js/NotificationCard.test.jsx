import React from 'react'
import { render, screen } from '@testing-library/react'
import { NotificationCard } from '@/components/notifications/NotificationCard'

jest.mock('@wordpress/i18n', () => ({
  __: (text) => text,
}))

const promo = (extra = {}) => ({
  id: 1,
  title: 'Sale title',
  description: '<p>Body</p>',
  backgroundColor: 'promo',
  highlight: '30% OFF',
  endsAt: new Date(Date.now() + 3 * 86400000).toISOString(),
  primaryButton: { title: 'Get the deal', url: 'https://wsms.io/pricing/' },
  ...extra,
})

describe('NotificationCard promo', () => {
  test('hides the countdown unless countdown is on', () => {
    render(<NotificationCard notification={promo()} />)
    expect(screen.queryByText(/Ends in/)).not.toBeInTheDocument()
  })

  test('shows the countdown when countdown is on', () => {
    render(<NotificationCard notification={promo({ countdown: true })} />)
    expect(screen.getByText(/Ends in/)).toBeInTheDocument()
  })

  test('shows no countdown after the end date', () => {
    render(<NotificationCard notification={promo({ countdown: true, endsAt: new Date(Date.now() - 1000).toISOString() })} />)
    expect(screen.queryByText(/Ends in/)).not.toBeInTheDocument()
  })

  test('uses the eyebrow, falls back to the title', () => {
    const { rerender } = render(<NotificationCard notification={promo({ eyebrow: 'Black Friday' })} />)
    expect(screen.getByText('Black Friday')).toBeInTheDocument()
    expect(screen.queryByText('Sale title')).not.toBeInTheDocument()
    rerender(<NotificationCard notification={promo()} />)
    expect(screen.getByText('Sale title')).toBeInTheDocument()
  })

  test.each(['https://wsms.io/x', 'https://www.wsms.io/x', 'https://my.wsms.io/x', 'https://wp-statistics.com/x'])(
    'own site link %s has no arrow but still opens in a new tab',
    (url) => {
      const { container } = render(<NotificationCard notification={promo({ primaryButton: { title: 'Go', url } })} />)
      const link = screen.getByRole('link', { name: /Go/ })
      expect(link).toHaveAttribute('target', '_blank')
      expect(container.querySelector('a svg')).toBeNull()
    }
  )

  test('other external links keep the arrow', () => {
    const { container } = render(<NotificationCard notification={promo({ primaryButton: { title: 'Go', url: 'https://example.com/x' } })} />)
    expect(container.querySelector('a svg')).not.toBeNull()
  })
})
