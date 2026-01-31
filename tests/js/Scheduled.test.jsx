import React from 'react'
import { render, screen, fireEvent, waitFor } from '@testing-library/react'
import { setupWpSmsSettings, AllProviders } from './testing-utils'
import Scheduled from '@/pages/Scheduled'

// Mock the API modules
jest.mock('../api/scheduledApi', () => ({
  scheduledApi: {
    getMessages: jest.fn(),
    deleteMessage: jest.fn(),
    sendNow: jest.fn(),
    bulkAction: jest.fn(),
    exportCsv: jest.fn(),
  },
}))

jest.mock('../api/repeatingApi', () => ({
  repeatingApi: {
    getMessages: jest.fn(),
    deleteMessage: jest.fn(),
    bulkAction: jest.fn(),
    exportCsv: jest.fn(),
    formatInterval: jest.fn((val, unit) => `Every ${val} ${unit}`),
    getIntervalUnitOptions: jest.fn(() => [
      { value: 'minute', label: 'Minute(s)' },
    ]),
  },
}))

const { scheduledApi } = require('../api/scheduledApi')
const { repeatingApi } = require('../api/repeatingApi')

const mockScheduledResponse = {
  items: [
    {
      id: 1,
      date: '2025-01-15 10:00:00',
      sender: 'TestSender',
      recipient: '+1234567890',
      recipient_count: 1,
      message: 'Test scheduled message',
      status: 'pending',
      media: [],
    },
    {
      id: 2,
      date: '2025-01-14 08:00:00',
      sender: 'TestSender',
      recipient: '+0987654321',
      recipient_count: 1,
      message: 'Already sent message',
      status: 'sent',
      media: [],
    },
  ],
  pagination: { total: 2, total_pages: 1, current_page: 1, per_page: 20 },
  stats: { total: 2, pending: 1, sent: 1, failed: 0 },
}

const mockRepeatingResponse = {
  items: [
    {
      id: 10,
      sender: 'RepSender',
      recipient: '+1111111111',
      recipient_count: 1,
      message: 'Repeating message',
      interval: 5,
      interval_unit: 'minutes',
      interval_display: 'Every 5 minutes',
      status: 'active',
      starts_at: 1705312800,
      starts_at_date: '2025-01-15 10:00:00',
      ends_at: null,
      ends_at_date: null,
      next_occurrence: '2025-01-20 10:05:00',
      media: [],
    },
  ],
  pagination: { total: 1, total_pages: 1, current_page: 1, per_page: 20 },
  stats: { total: 1, active: 1, ended: 0 },
}

const renderScheduled = () => {
  return render(
    <AllProviders>
      <Scheduled />
    </AllProviders>
  )
}

describe('Scheduled Page', () => {
  beforeEach(() => {
    jest.clearAllMocks()
    setupWpSmsSettings({ hasProAddon: true })
    scheduledApi.getMessages.mockResolvedValue(mockScheduledResponse)
    repeatingApi.getMessages.mockResolvedValue(mockRepeatingResponse)
  })

  describe('Pro addon check', () => {
    test('shows pro required message when addon is not active', () => {
      setupWpSmsSettings({ hasProAddon: false })
      renderScheduled()

      expect(screen.getByText(/WP SMS Pro Required/i)).toBeInTheDocument()
      expect(screen.getByText(/Learn More/i)).toBeInTheDocument()
    })

    test('shows tabs when pro addon is active', async () => {
      renderScheduled()

      await waitFor(() => {
        expect(screen.getByText(/Scheduled SMS/i)).toBeInTheDocument()
        expect(screen.getByText(/Repeating SMS/i)).toBeInTheDocument()
      })
    })
  })

  describe('Scheduled SMS Tab', () => {
    test('loads and displays scheduled messages', async () => {
      renderScheduled()

      await waitFor(() => {
        expect(scheduledApi.getMessages).toHaveBeenCalled()
      })

      await waitFor(() => {
        expect(screen.getByText('Test scheduled message')).toBeInTheDocument()
      })
    })

    test('displays stats correctly', async () => {
      renderScheduled()

      await waitFor(() => {
        expect(screen.getByText('2')).toBeInTheDocument() // total
      })
    })

    test('fetches data only once on initial load', async () => {
      renderScheduled()

      await waitFor(() => {
        expect(scheduledApi.getMessages).toHaveBeenCalledTimes(1)
      })
    })
  })

  describe('Tab switching', () => {
    test('switches between scheduled and repeating tabs', async () => {
      renderScheduled()

      await waitFor(() => {
        expect(screen.getByText('Test scheduled message')).toBeInTheDocument()
      })

      // Switch to repeating tab
      fireEvent.click(screen.getByText(/Repeating SMS/i))

      await waitFor(() => {
        expect(screen.getByText('Repeating message')).toBeInTheDocument()
      })
    })

    test('does not refetch data when switching back to a tab', async () => {
      renderScheduled()

      await waitFor(() => {
        expect(scheduledApi.getMessages).toHaveBeenCalledTimes(1)
      })

      // Switch to repeating tab
      fireEvent.click(screen.getByText(/Repeating SMS/i))

      await waitFor(() => {
        expect(repeatingApi.getMessages).toHaveBeenCalledTimes(1)
      })

      // Switch back to scheduled tab
      fireEvent.click(screen.getByText(/Scheduled SMS/i))

      // Should not refetch - still only 1 call
      expect(scheduledApi.getMessages).toHaveBeenCalledTimes(1)
    })
  })

  describe('Repeating SMS Tab', () => {
    test('loads and displays repeating messages', async () => {
      renderScheduled()

      // Switch to repeating tab
      fireEvent.click(screen.getByText(/Repeating SMS/i))

      await waitFor(() => {
        expect(repeatingApi.getMessages).toHaveBeenCalled()
      })

      await waitFor(() => {
        expect(screen.getByText('Repeating message')).toBeInTheDocument()
      })
    })

    test('calls repeating API when tab is rendered', async () => {
      renderScheduled()

      await waitFor(() => {
        expect(repeatingApi.getMessages).toHaveBeenCalled()
      })
    })

    test('does not show pause or resume actions', async () => {
      renderScheduled()

      fireEvent.click(screen.getByText(/Repeating SMS/i))

      await waitFor(() => {
        expect(screen.getByText('Repeating message')).toBeInTheDocument()
      })

      // Pause and Resume should not be in the document
      expect(screen.queryByText('Pause')).not.toBeInTheDocument()
      expect(screen.queryByText('Resume')).not.toBeInTheDocument()
    })
  })

  describe('Empty and error states', () => {
    test('shows empty state when both tabs have no data', async () => {
      scheduledApi.getMessages.mockResolvedValue({
        items: [],
        pagination: { total: 0, total_pages: 1, current_page: 1, per_page: 20 },
        stats: { total: 0, pending: 0, sent: 0, failed: 0 },
      })
      repeatingApi.getMessages.mockResolvedValue({
        items: [],
        pagination: { total: 0, total_pages: 1, current_page: 1, per_page: 20 },
        stats: { total: 0, active: 0, ended: 0 },
      })

      renderScheduled()

      await waitFor(() => {
        expect(screen.getByText(/No scheduled messages/i)).toBeInTheDocument()
      })
    })

    test('shows error state when both APIs fail', async () => {
      scheduledApi.getMessages.mockRejectedValue(new Error('Failed to load'))
      repeatingApi.getMessages.mockRejectedValue(new Error('Failed to load'))

      renderScheduled()

      await waitFor(() => {
        expect(screen.getByText(/Failed to load scheduled messages/i)).toBeInTheDocument()
      })
    })
  })
})
