import React from 'react'
import { render, screen, fireEvent, waitFor, act } from '@testing-library/react'
import FloatingSaveBar from '@/components/layout/FloatingSaveBar'
import { SettingsProvider, useSettings } from '@/context/SettingsContext'
import { Toaster } from '@/components/ui/toaster'
import { setupWpSmsSettings } from './testing-utils'

// Mock the settings API
jest.mock('../api/settingsApi', () => ({
  settingsApi: {
    updateSettings: jest.fn().mockResolvedValue({ success: true }),
    getSettings: jest.fn().mockResolvedValue({ settings: {}, proSettings: {} }),
    testGateway: jest.fn().mockResolvedValue({ success: true }),
  },
}))

// Helper component to trigger changes
function TriggerChange() {
  const { updateSetting } = useSettings()

  return (
    <button
      data-testid="trigger-change"
      onClick={() => updateSetting('test_key', 'test_value_' + Date.now())}
    >
      Trigger Change
    </button>
  )
}

const renderFloatingSaveBar = () => {
  setupWpSmsSettings()
  return render(
    <SettingsProvider>
      <Toaster>
        <TriggerChange />
        <FloatingSaveBar />
      </Toaster>
    </SettingsProvider>
  )
}

describe('FloatingSaveBar', () => {
  beforeEach(() => {
    setupWpSmsSettings()
    jest.clearAllMocks()
  })

  describe('Visibility', () => {
    test('is hidden when there are no changes', async () => {
      render(
        <SettingsProvider>
          <Toaster>
            <FloatingSaveBar />
          </Toaster>
        </SettingsProvider>
      )

      // Wait for initial render
      await waitFor(() => {
        expect(screen.queryByText('You have unsaved changes')).not.toBeInTheDocument()
      })
    })

    test('is visible when there are unsaved changes', async () => {
      renderFloatingSaveBar()

      // Trigger a change
      const triggerButton = screen.getByTestId('trigger-change')
      fireEvent.click(triggerButton)

      await waitFor(() => {
        expect(screen.getByText('You have unsaved changes')).toBeInTheDocument()
      })
    })
  })

  describe('Buttons', () => {
    test('renders Save Changes button when visible', async () => {
      renderFloatingSaveBar()

      // Trigger a change
      fireEvent.click(screen.getByTestId('trigger-change'))

      await waitFor(() => {
        expect(screen.getByText('Save Changes')).toBeInTheDocument()
      })
    })

    test('renders Discard button when visible', async () => {
      renderFloatingSaveBar()

      // Trigger a change
      fireEvent.click(screen.getByTestId('trigger-change'))

      await waitFor(() => {
        expect(screen.getByText('Discard')).toBeInTheDocument()
      })
    })
  })

  describe('Accessibility', () => {
    test('status message has role="status"', async () => {
      renderFloatingSaveBar()

      // Trigger a change
      fireEvent.click(screen.getByTestId('trigger-change'))

      await waitFor(() => {
        const statusElement = screen.getByRole('status')
        expect(statusElement).toBeInTheDocument()
      })
    })

    test('status message has aria-live="polite"', async () => {
      renderFloatingSaveBar()

      // Trigger a change
      fireEvent.click(screen.getByTestId('trigger-change'))

      await waitFor(() => {
        const statusElement = screen.getByRole('status')
        expect(statusElement).toHaveAttribute('aria-live', 'polite')
      })
    })

    test('status message has aria-atomic="true"', async () => {
      renderFloatingSaveBar()

      // Trigger a change
      fireEvent.click(screen.getByTestId('trigger-change'))

      await waitFor(() => {
        const statusElement = screen.getByRole('status')
        expect(statusElement).toHaveAttribute('aria-atomic', 'true')
      })
    })

    test('icons are hidden from screen readers', async () => {
      renderFloatingSaveBar()

      // Trigger a change
      fireEvent.click(screen.getByTestId('trigger-change'))

      await waitFor(() => {
        expect(screen.getByText('Save Changes')).toBeInTheDocument()
      })

      const saveButton = screen.getByText('Save Changes').closest('button')
      const icons = saveButton.querySelectorAll('svg')

      icons.forEach((icon) => {
        expect(icon).toHaveAttribute('aria-hidden', 'true')
      })
    })
  })

  describe('Actions', () => {
    test('discards changes when Discard button is clicked', async () => {
      renderFloatingSaveBar()

      // Trigger a change
      fireEvent.click(screen.getByTestId('trigger-change'))

      await waitFor(() => {
        expect(screen.getByText('Discard')).toBeInTheDocument()
      })

      // Click discard
      fireEvent.click(screen.getByText('Discard'))

      // Bar should disappear after discard
      await waitFor(() => {
        expect(screen.queryByText('You have unsaved changes')).not.toBeInTheDocument()
      })
    })

    test('calls save settings when Save Changes is clicked', async () => {
      const settingsApi = require('../api/settingsApi').settingsApi
      renderFloatingSaveBar()

      // Trigger a change
      fireEvent.click(screen.getByTestId('trigger-change'))

      await waitFor(() => {
        expect(screen.getByText('Save Changes')).toBeInTheDocument()
      })

      // Click save
      fireEvent.click(screen.getByText('Save Changes'))

      await waitFor(() => {
        expect(settingsApi.updateSettings).toHaveBeenCalled()
      })
    })
  })
})
