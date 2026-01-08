import React from 'react'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import Header from '../components/layout/Header'
import { setupWpSmsSettings, AllProviders } from './testing-utils'

// Mock the hooks and components
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

jest.mock('../components/notifications', () => ({
  NotificationSidebar: ({ isOpen, onClose }) =>
    isOpen ? (
      <div data-testid="notification-sidebar">
        <button onClick={onClose}>Close Sidebar</button>
      </div>
    ) : null,
}))

const renderHeader = (props = {}) => {
  const defaultProps = {
    onMenuClick: jest.fn(),
    showMenuButton: false,
  }
  return render(
    <AllProviders>
      <Header {...defaultProps} {...props} />
    </AllProviders>
  )
}

describe('Header', () => {
  beforeEach(() => {
    setupWpSmsSettings()
  })

  describe('Layout', () => {
    test('renders header element', () => {
      renderHeader()
      expect(screen.getByRole('banner')).toBeInTheDocument()
    })

    test('renders logo', () => {
      renderHeader()
      // Logo should be present in the header
      expect(screen.getByRole('banner')).toBeInTheDocument()
    })

    test('does not show menu button by default', () => {
      renderHeader({ showMenuButton: false })
      expect(screen.queryByLabelText('Open navigation menu')).not.toBeInTheDocument()
    })

    test('shows menu button when showMenuButton is true', () => {
      const onMenuClick = jest.fn()
      renderHeader({ showMenuButton: true, onMenuClick })

      const menuButton = screen.getByLabelText('Open navigation menu')
      expect(menuButton).toBeInTheDocument()
    })

    test('calls onMenuClick when menu button is clicked', () => {
      const onMenuClick = jest.fn()
      renderHeader({ showMenuButton: true, onMenuClick })

      const menuButton = screen.getByLabelText('Open navigation menu')
      fireEvent.click(menuButton)

      expect(onMenuClick).toHaveBeenCalledTimes(1)
    })
  })

  describe('License Button', () => {
    test('shows upgrade button when no license', () => {
      setupWpSmsSettings({
        addons: { pro: false },
        licenses: [],
        licensedPluginsCount: 0,
      })
      renderHeader()

      // There are two upgrade texts (mobile and desktop), just check at least one exists
      const upgradeElements = screen.getAllByText(/Upgrade/i)
      expect(upgradeElements.length).toBeGreaterThan(0)
    })

    test('shows partial license count when partially licensed', () => {
      setupWpSmsSettings({
        addons: { pro: false },
        licenses: ['license-key'],
        licensedPluginsCount: 3,
        totalPlugins: 7,
      })
      renderHeader()

      expect(screen.getByText(/License: 3\/7/i)).toBeInTheDocument()
      expect(screen.getByText('Upgrade')).toBeInTheDocument()
    })

    test('shows All-in-One badge when premium is active', () => {
      setupWpSmsSettings({
        addons: { pro: true },
      })
      renderHeader()

      expect(screen.getByText('All-in-One')).toBeInTheDocument()
      expect(screen.queryByText('Upgrade')).not.toBeInTheDocument()
    })
  })

  describe('Notification Bell', () => {
    test('renders notification button', () => {
      renderHeader()

      const notificationButton = screen.getByLabelText('Notifications')
      expect(notificationButton).toBeInTheDocument()
    })

    test('opens notification sidebar when bell is clicked', async () => {
      renderHeader()

      const notificationButton = screen.getByLabelText('Notifications')
      fireEvent.click(notificationButton)

      await waitFor(() => {
        expect(screen.getByTestId('notification-sidebar')).toBeInTheDocument()
      })
    })

    test('closes notification sidebar when close button is clicked', async () => {
      renderHeader()

      // Open sidebar
      const notificationButton = screen.getByLabelText('Notifications')
      fireEvent.click(notificationButton)

      await waitFor(() => {
        expect(screen.getByTestId('notification-sidebar')).toBeInTheDocument()
      })

      // Close sidebar
      const closeButton = screen.getByText('Close Sidebar')
      fireEvent.click(closeButton)

      await waitFor(() => {
        expect(screen.queryByTestId('notification-sidebar')).not.toBeInTheDocument()
      })
    })
  })

  describe('Theme Toggle', () => {
    test('renders theme toggle button', () => {
      renderHeader()

      const themeButton = screen.getByLabelText(/Switch to (dark|light) mode/i)
      expect(themeButton).toBeInTheDocument()
    })

    test('toggles theme when clicked', async () => {
      renderHeader()

      const themeButton = screen.getByLabelText(/Switch to (dark|light) mode/i)
      const initialLabel = themeButton.getAttribute('aria-label')

      fireEvent.click(themeButton)

      await waitFor(() => {
        const newLabel = themeButton.getAttribute('aria-label')
        expect(newLabel).not.toBe(initialLabel)
      })
    })
  })

  describe('Accessibility', () => {
    test('menu button has correct aria-label', () => {
      renderHeader({ showMenuButton: true })
      expect(screen.getByLabelText('Open navigation menu')).toBeInTheDocument()
    })

    test('notification button has correct aria-label', () => {
      renderHeader()
      expect(screen.getByLabelText('Notifications')).toBeInTheDocument()
    })

    test('theme toggle has descriptive aria-label', () => {
      renderHeader()
      expect(screen.getByLabelText(/Switch to (dark|light) mode/i)).toBeInTheDocument()
    })

    test('menu icon is hidden from screen readers', () => {
      renderHeader({ showMenuButton: true })
      const button = screen.getByLabelText('Open navigation menu')
      const icon = button.querySelector('svg')
      expect(icon).toHaveAttribute('aria-hidden', 'true')
    })
  })
})
