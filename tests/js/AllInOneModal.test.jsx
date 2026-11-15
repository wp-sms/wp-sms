import React from 'react'
import { render, screen } from '@testing-library/react'
import AllInOneModal from '@/components/modals/AllInOneModal'

jest.mock('@wordpress/i18n', () => ({
  __: (text) => text,
  sprintf: (text, value) => text.replace('%s', value),
}))

jest.mock('@/hooks/useAllInOneModal', () => ({
  useAllInOneModal: () => ({
    isOpen: true,
    close: jest.fn(),
    open: jest.fn(),
    addons: [],
    isPremium: false,
  }),
}))

jest.mock('@/hooks/useRootZIndex', () => ({ useRootZIndex: jest.fn() }))

jest.mock('@/lib/utils', () => ({
  cn: (...classes) => classes.filter(Boolean).join(' '),
  getWpSettings: () => ({ adminUrl: '/wp-admin/', pluginUrl: '/wp-content/plugins/wp-sms/' }),
}))

describe('AllInOneModal', () => {
  beforeEach(() => {
    const root = document.createElement('div')
    root.id = 'wpsms-settings-root'
    document.body.appendChild(root)
  })

  afterEach(() => {
    document.getElementById('wpsms-settings-root')?.remove()
  })

  test('names every icon-only dialog control', () => {
    render(<AllInOneModal />)

    expect(screen.getByRole('button', { name: 'Close' })).toBeInTheDocument()
    expect(screen.getAllByRole('button', { name: 'Previous' })).toHaveLength(2)
    expect(screen.getAllByRole('button', { name: 'Next' })).toHaveLength(2)
  })
})
