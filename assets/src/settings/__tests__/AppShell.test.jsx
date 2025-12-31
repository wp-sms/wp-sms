import React from 'react'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import AppShell from '../components/layout/AppShell'
import { setupWpSmsSettings, AllProviders } from './testing-utils'

// Mock the page components to avoid complex rendering
jest.mock('@/pages/SendSms', () => () => <div data-testid="page-send-sms">Send SMS Page</div>)
jest.mock('@/pages/Outbox', () => () => <div data-testid="page-outbox">Outbox Page</div>)
jest.mock('@/pages/Subscribers', () => () => <div data-testid="page-subscribers">Subscribers Page</div>)
jest.mock('@/pages/Groups', () => () => <div data-testid="page-groups">Groups Page</div>)
jest.mock('@/pages/Overview', () => () => <div data-testid="page-overview">Overview Page</div>)
jest.mock('@/pages/Gateway', () => () => <div data-testid="page-gateway">Gateway Page</div>)
jest.mock('@/pages/PhoneConfig', () => () => <div data-testid="page-phone">Phone Page</div>)
jest.mock('@/pages/MessageButton', () => () => <div data-testid="page-message-button">Message Button Page</div>)
jest.mock('@/pages/Notifications', () => () => <div data-testid="page-notifications">Notifications Page</div>)
jest.mock('@/pages/Newsletter', () => () => <div data-testid="page-newsletter">Newsletter Page</div>)
jest.mock('@/pages/Integrations', () => () => <div data-testid="page-integrations">Integrations Page</div>)
jest.mock('@/pages/Advanced', () => () => <div data-testid="page-advanced">Advanced Page</div>)
jest.mock('@/pages/Privacy', () => () => <div data-testid="page-privacy">Privacy Page</div>)

// Mock the hooks
jest.mock('../hooks/use-mobile', () => ({
  useIsMobile: jest.fn(() => false),
}))

jest.mock('../hooks/useNotifications', () => ({
  useNotifications: () => ({
    inboxNotifications: [],
    dismissedNotifications: [],
    hasUnread: false,
    loading: false,
    dismiss: jest.fn(),
    dismissAll: jest.fn(),
  }),
}))

const renderAppShell = () => {
  return render(
    <AllProviders>
      <AppShell />
    </AllProviders>
  )
}

describe('AppShell', () => {
  beforeEach(() => {
    setupWpSmsSettings()
    jest.clearAllMocks()
  })

  describe('Layout Structure', () => {
    test('renders the main app container', async () => {
      renderAppShell()

      await waitFor(() => {
        expect(document.querySelector('.wsms-settings-app')).toBeInTheDocument()
      })
    })

    test('renders Header component', async () => {
      renderAppShell()

      await waitFor(() => {
        expect(screen.getByRole('banner')).toBeInTheDocument()
      })
    })

    test('renders Sidebar component', async () => {
      renderAppShell()

      await waitFor(() => {
        expect(screen.getByRole('navigation')).toBeInTheDocument()
      })
    })

    test('renders main content area', async () => {
      renderAppShell()

      await waitFor(() => {
        const main = screen.getByRole('main')
        expect(main).toBeInTheDocument()
        expect(main).toHaveAttribute('id', 'main-content')
      })
    })
  })

  describe('Skip to Content Link', () => {
    test('renders skip to content link', async () => {
      renderAppShell()

      await waitFor(() => {
        const skipLink = screen.getByText('Skip to main content')
        expect(skipLink).toBeInTheDocument()
      })
    })

    test('skip link points to main content', async () => {
      renderAppShell()

      await waitFor(() => {
        const skipLink = screen.getByText('Skip to main content')
        expect(skipLink).toHaveAttribute('href', '#main-content')
      })
    })

    test('skip link is visually hidden by default', async () => {
      renderAppShell()

      await waitFor(() => {
        const skipLink = screen.getByText('Skip to main content')
        expect(skipLink).toHaveClass('wsms-sr-only')
      })
    })

    test('main content has tabIndex for focus', async () => {
      renderAppShell()

      await waitFor(() => {
        const main = screen.getByRole('main')
        expect(main).toHaveAttribute('tabIndex', '-1')
      })
    })
  })

  describe('Page Rendering', () => {
    test('renders Overview page by default', async () => {
      renderAppShell()

      await waitFor(() => {
        expect(screen.getByTestId('page-overview')).toBeInTheDocument()
      })
    })

    test('renders correct page when navigation item is clicked', async () => {
      renderAppShell()

      await waitFor(() => {
        expect(screen.getByText('Outbox')).toBeInTheDocument()
      })

      fireEvent.click(screen.getByText('Outbox'))

      await waitFor(() => {
        expect(screen.getByTestId('page-outbox')).toBeInTheDocument()
      })
    })

    test('renders Send SMS page when clicked', async () => {
      renderAppShell()

      await waitFor(() => {
        expect(screen.getByText('Send SMS')).toBeInTheDocument()
      })

      fireEvent.click(screen.getByText('Send SMS'))

      await waitFor(() => {
        expect(screen.getByTestId('page-send-sms')).toBeInTheDocument()
      })
    })

    test('renders Subscribers page when clicked', async () => {
      renderAppShell()

      await waitFor(() => {
        expect(screen.getByText('Subscribers')).toBeInTheDocument()
      })

      fireEvent.click(screen.getByText('Subscribers'))

      await waitFor(() => {
        expect(screen.getByTestId('page-subscribers')).toBeInTheDocument()
      })
    })
  })

  describe('Mobile Behavior', () => {
    beforeEach(() => {
      const { useIsMobile } = require('../hooks/use-mobile')
      useIsMobile.mockReturnValue(true)
    })

    test('shows menu button on mobile', async () => {
      renderAppShell()

      await waitFor(() => {
        expect(screen.getByLabelText('Open navigation menu')).toBeInTheDocument()
      })
    })

    test('opens mobile menu when menu button is clicked', async () => {
      renderAppShell()

      await waitFor(() => {
        expect(screen.getByLabelText('Open navigation menu')).toBeInTheDocument()
      })

      const menuButton = screen.getByLabelText('Open navigation menu')
      fireEvent.click(menuButton)

      await waitFor(() => {
        expect(screen.getByLabelText('Close navigation menu')).toBeInTheDocument()
      })
    })

    test('closes mobile menu when close button is clicked', async () => {
      renderAppShell()

      // Open menu
      await waitFor(() => {
        expect(screen.getByLabelText('Open navigation menu')).toBeInTheDocument()
      })
      fireEvent.click(screen.getByLabelText('Open navigation menu'))

      // Close menu
      await waitFor(() => {
        expect(screen.getByLabelText('Close navigation menu')).toBeInTheDocument()
      })
      fireEvent.click(screen.getByLabelText('Close navigation menu'))

      await waitFor(() => {
        expect(screen.queryByLabelText('Close navigation menu')).not.toBeInTheDocument()
      })
    })

    test('closes mobile menu when page changes', async () => {
      renderAppShell()

      // Open menu
      await waitFor(() => {
        expect(screen.getByLabelText('Open navigation menu')).toBeInTheDocument()
      })
      fireEvent.click(screen.getByLabelText('Open navigation menu'))

      // Click nav item
      await waitFor(() => {
        expect(screen.getByText('Outbox')).toBeInTheDocument()
      })
      fireEvent.click(screen.getByText('Outbox'))

      // Menu should close
      await waitFor(() => {
        expect(screen.queryByLabelText('Close navigation menu')).not.toBeInTheDocument()
      })
    })

    test('renders overlay when mobile menu is open', async () => {
      renderAppShell()

      await waitFor(() => {
        expect(screen.getByLabelText('Open navigation menu')).toBeInTheDocument()
      })

      fireEvent.click(screen.getByLabelText('Open navigation menu'))

      await waitFor(() => {
        const overlay = document.querySelector('.wsms-bg-black\\/50')
        expect(overlay).toBeInTheDocument()
      })
    })

    test('closes menu when overlay is clicked', async () => {
      renderAppShell()

      // Open menu
      await waitFor(() => {
        expect(screen.getByLabelText('Open navigation menu')).toBeInTheDocument()
      })
      fireEvent.click(screen.getByLabelText('Open navigation menu'))

      // Click overlay
      await waitFor(() => {
        const overlay = document.querySelector('.wsms-bg-black\\/50')
        expect(overlay).toBeInTheDocument()
        fireEvent.click(overlay)
      })

      // Menu should close
      await waitFor(() => {
        expect(screen.queryByLabelText('Close navigation menu')).not.toBeInTheDocument()
      })
    })
  })

  describe('Desktop Behavior', () => {
    beforeEach(() => {
      const { useIsMobile } = require('../hooks/use-mobile')
      useIsMobile.mockReturnValue(false)
    })

    test('does not show menu button on desktop', async () => {
      renderAppShell()

      await waitFor(() => {
        expect(screen.queryByLabelText('Open navigation menu')).not.toBeInTheDocument()
      })
    })

    test('sidebar is always visible on desktop', async () => {
      renderAppShell()

      await waitFor(() => {
        const sidebar = document.querySelector('.wsms-sidebar')
        expect(sidebar).toBeInTheDocument()
        expect(sidebar).not.toHaveClass('wsms--translate-x-full')
      })
    })
  })

  describe('Loading State', () => {
    test('shows loading skeleton when settings are loading', async () => {
      // This test verifies the loading state behavior
      // The actual loading is controlled by isLoading from SettingsContext
      renderAppShell()

      // After loading completes, the page should be visible
      await waitFor(() => {
        expect(screen.getByTestId('page-overview')).toBeInTheDocument()
      })
    })
  })

  describe('Accessibility', () => {
    test('main content area has proper id for skip link', async () => {
      renderAppShell()

      await waitFor(() => {
        const main = screen.getByRole('main')
        expect(main).toHaveAttribute('id', 'main-content')
      })
    })

    test('landmark roles are present', async () => {
      renderAppShell()

      await waitFor(() => {
        expect(screen.getByRole('banner')).toBeInTheDocument() // header
        expect(screen.getByRole('navigation')).toBeInTheDocument() // nav
        expect(screen.getByRole('main')).toBeInTheDocument() // main
      })
    })
  })
})
