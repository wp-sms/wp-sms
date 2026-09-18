import React from 'react'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import Sidebar from '@/components/layout/Sidebar'
import { canAccessPage, getAccessiblePageIds, getValidPageIds } from '@/lib/pageRegistry'
import { resolvePage } from '@/context/SettingsContext'
import { setupWpSmsSettings, AllProviders } from './testing-utils'

// Capabilities as PHP localizes them from Dashboard::getUserCapabilities()
const allCapabilities = {
  canSendSms: true,
  canViewOutbox: true,
  canViewInbox: true,
  canManageSubscribers: true,
  canManageSettings: true,
  canManageOptions: true,
}

const only = (...keys) => {
  const capabilities = {}
  for (const key of Object.keys(allCapabilities)) {
    capabilities[key] = keys.includes(key)
  }
  return capabilities
}

const renderSidebar = () =>
  render(
    <AllProviders>
      <Sidebar onClose={jest.fn()} showClose={false} />
    </AllProviders>
  )

describe('page access by capability', () => {
  describe('pageRegistry', () => {
    test('every page declares a capability', () => {
      for (const pageId of getValidPageIds()) {
        expect(canAccessPage(pageId, only())).toBe(false)
      }
    })

    test('missing capabilities data hides nothing', () => {
      expect(canAccessPage('gateway', undefined)).toBe(true)
      expect(getAccessiblePageIds(undefined)).toEqual(getValidPageIds())
    })

    test('settings pages need the settings capability', () => {
      const caps = only('canSendSms')
      expect(canAccessPage('send-sms', caps)).toBe(true)
      expect(canAccessPage('gateway', caps)).toBe(false)
      expect(canAccessPage('two-way-settings', caps)).toBe(false)
    })

    test('the inbox needs only the inbox capability', () => {
      const caps = only('canViewInbox')
      expect(getAccessiblePageIds(caps)).toEqual(['two-way-inbox'])
    })

    test('unknown pages are never accessible', () => {
      expect(canAccessPage('no-such-page', allCapabilities)).toBe(false)
    })
  })

  describe('resolvePage', () => {
    test('keeps a page the user may open', () => {
      setupWpSmsSettings({ capabilities: allCapabilities })
      expect(resolvePage('gateway')).toBe('gateway')
    })

    test('sends a forbidden page to Send SMS when that is allowed', () => {
      setupWpSmsSettings({ capabilities: only('canSendSms') })
      expect(resolvePage('gateway')).toBe('send-sms')
    })

    test('sends a forbidden page to the first allowed page otherwise', () => {
      setupWpSmsSettings({ capabilities: only('canManageSubscribers') })
      expect(resolvePage('gateway')).toBe('subscribers')
      expect(resolvePage(null)).toBe('subscribers')
    })

    test('returns null when nothing is allowed', () => {
      setupWpSmsSettings({ capabilities: only() })
      expect(resolvePage('send-sms')).toBeNull()
    })
  })

  describe('Sidebar', () => {
    test('shows every section to a user with every capability', () => {
      setupWpSmsSettings({ capabilities: allCapabilities })
      renderSidebar()

      expect(screen.getByText('Send SMS')).toBeInTheDocument()
      expect(screen.getByText('Outbox')).toBeInTheDocument()
      expect(screen.getByText('Subscribers')).toBeInTheDocument()
      expect(screen.getByText('Settings')).toBeInTheDocument()
    })

    test('hides sections the user may not open', () => {
      setupWpSmsSettings({ capabilities: only('canSendSms', 'canViewOutbox') })
      renderSidebar()

      expect(screen.getByText('Send SMS')).toBeInTheDocument()
      expect(screen.getByText('Outbox')).toBeInTheDocument()
      expect(screen.queryByText('Subscribers')).not.toBeInTheDocument()
      expect(screen.queryByText('Groups')).not.toBeInTheDocument()
      expect(screen.queryByText('Settings')).not.toBeInTheDocument()
    })

    test('hides the gateway status when the user cannot manage settings', () => {
      setupWpSmsSettings({ capabilities: only('canSendSms') })
      renderSidebar()

      expect(screen.queryByText(/Gateway not configured/)).not.toBeInTheDocument()
    })

    test('hides single pages inside a group the user may partly open', async () => {
      setupWpSmsSettings({
        capabilities: only('canManageSettings'),
        addons: { 'two-way': true },
      })
      renderSidebar()

      fireEvent.click(screen.getByText('Two-Way SMS'))
      await waitFor(() => {
        expect(screen.getByText('Commands')).toBeInTheDocument()
      })
      expect(screen.queryByText('Inbox')).not.toBeInTheDocument()
    })
  })
})
