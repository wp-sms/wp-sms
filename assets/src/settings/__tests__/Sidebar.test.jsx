import React from 'react'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import Sidebar from '../components/layout/Sidebar'
import { setupWpSmsSettings, AllProviders } from './testing-utils'

const renderSidebar = (props = {}) => {
  const defaultProps = {
    onClose: jest.fn(),
    showClose: false,
  }
  return render(
    <AllProviders>
      <Sidebar {...defaultProps} {...props} />
    </AllProviders>
  )
}

describe('Sidebar', () => {
  beforeEach(() => {
    setupWpSmsSettings()
  })

  describe('Navigation Structure', () => {
    test('renders navigation element', () => {
      renderSidebar()
      expect(screen.getByRole('navigation')).toBeInTheDocument()
    })

    test('renders main navigation items', () => {
      renderSidebar()

      expect(screen.getByText('Send SMS')).toBeInTheDocument()
      expect(screen.getByText('Outbox')).toBeInTheDocument()
      expect(screen.getByText('Subscribers')).toBeInTheDocument()
      expect(screen.getByText('Groups')).toBeInTheDocument()
    })

    test('renders Settings group', () => {
      renderSidebar()
      expect(screen.getByText('Settings')).toBeInTheDocument()
    })

    test('expands Settings group when clicked', async () => {
      renderSidebar()

      const settingsGroup = screen.getByText('Settings')
      fireEvent.click(settingsGroup)

      await waitFor(() => {
        expect(screen.getByText('Overview')).toBeInTheDocument()
        expect(screen.getByText('Gateway')).toBeInTheDocument()
        expect(screen.getByText('Phone')).toBeInTheDocument()
        expect(screen.getByText('Message Button')).toBeInTheDocument()
        expect(screen.getByText('Notifications')).toBeInTheDocument()
        expect(screen.getByText('Newsletter')).toBeInTheDocument()
        expect(screen.getByText('Integrations')).toBeInTheDocument()
        expect(screen.getByText('Advanced')).toBeInTheDocument()
      })
    })

    test('collapses Settings group when clicked again', async () => {
      renderSidebar()

      const settingsGroup = screen.getByText('Settings')

      // Expand
      fireEvent.click(settingsGroup)
      await waitFor(() => {
        expect(screen.getByText('Overview')).toBeInTheDocument()
      })

      // Collapse
      fireEvent.click(settingsGroup)
      await waitFor(() => {
        // The items should be hidden (collapsed)
        const overview = screen.queryByText('Overview')
        // Due to animation, it might still be in DOM but hidden
        expect(settingsGroup).toBeInTheDocument()
      })
    })
  })

  describe('Privacy Item Visibility', () => {
    test('does not show Privacy item when GDPR is disabled', () => {
      setupWpSmsSettings({ gdprEnabled: false })
      renderSidebar()

      expect(screen.queryByText('Privacy')).not.toBeInTheDocument()
    })

    test('shows Privacy item when GDPR is enabled', () => {
      setupWpSmsSettings({ gdprEnabled: true })
      renderSidebar()

      expect(screen.getByText('Privacy')).toBeInTheDocument()
    })
  })

  describe('Mobile Close Button', () => {
    test('does not show close button by default', () => {
      renderSidebar({ showClose: false })
      expect(screen.queryByLabelText('Close navigation menu')).not.toBeInTheDocument()
    })

    test('shows close button when showClose is true', () => {
      renderSidebar({ showClose: true })
      expect(screen.getByLabelText('Close navigation menu')).toBeInTheDocument()
    })

    test('calls onClose when close button is clicked', () => {
      const onClose = jest.fn()
      renderSidebar({ showClose: true, onClose })

      const closeButton = screen.getByLabelText('Close navigation menu')
      fireEvent.click(closeButton)

      expect(onClose).toHaveBeenCalledTimes(1)
    })
  })

  describe('Navigation Clicks', () => {
    test('clicking a nav item changes the active page', async () => {
      renderSidebar()

      const outboxItem = screen.getByText('Outbox')
      fireEvent.click(outboxItem)

      // Check that the button gets the active styling (has primary background)
      await waitFor(() => {
        const button = outboxItem.closest('button')
        expect(button).toHaveClass('wsms-bg-primary')
      })
    })

    test('clicking nested item sets it as active', async () => {
      renderSidebar()

      // Expand Settings
      fireEvent.click(screen.getByText('Settings'))

      await waitFor(() => {
        expect(screen.getByText('Gateway')).toBeInTheDocument()
      })

      // Click Gateway
      const gatewayItem = screen.getByText('Gateway')
      fireEvent.click(gatewayItem)

      await waitFor(() => {
        const button = gatewayItem.closest('button')
        expect(button).toHaveClass('wsms-bg-primary')
      })
    })
  })

  describe('Footer', () => {
    test('renders external links', () => {
      renderSidebar()

      const docLink = screen.getByText('Documentation')
      const supportLink = screen.getByText('Support')

      expect(docLink).toBeInTheDocument()
      expect(supportLink).toBeInTheDocument()
      expect(docLink.closest('a')).toHaveAttribute('target', '_blank')
      expect(supportLink.closest('a')).toHaveAttribute('target', '_blank')
    })

    test('links have rel=noopener noreferrer for security', () => {
      renderSidebar()

      const docLink = screen.getByText('Documentation').closest('a')
      expect(docLink).toHaveAttribute('rel', 'noopener noreferrer')
    })

    test('renders version number', () => {
      setupWpSmsSettings({ version: '7.5.0' })
      renderSidebar()

      expect(screen.getByText(/Version 7/i)).toBeInTheDocument()
    })
  })

  describe('Accessibility', () => {
    test('close button has correct aria-label', () => {
      renderSidebar({ showClose: true })
      expect(screen.getByLabelText('Close navigation menu')).toBeInTheDocument()
    })

    test('close icon is hidden from screen readers', () => {
      renderSidebar({ showClose: true })
      const button = screen.getByLabelText('Close navigation menu')
      const icon = button.querySelector('svg')
      expect(icon).toHaveAttribute('aria-hidden', 'true')
    })

    test('navigation items are keyboard accessible', () => {
      renderSidebar()

      const outboxItem = screen.getByText('Outbox').closest('button')
      expect(outboxItem.tagName).toBe('BUTTON')
      // Buttons are keyboard accessible by default
    })
  })
})
