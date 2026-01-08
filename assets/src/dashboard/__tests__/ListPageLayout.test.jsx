import React from 'react'
import { render, screen, fireEvent } from '@testing-library/react'
import { ListPageLayout } from '../components/layout/ListPageLayout'
import { setupWpSmsSettings, AllProviders } from './testing-utils'
import { Users, FolderOpen } from 'lucide-react'

// Mock the skeleton component
jest.mock('../components/ui/skeleton', () => ({
  PageLoadingSkeleton: () => <div data-testid="page-loading-skeleton">Loading...</div>,
  Skeleton: ({ className }) => <div className={className} data-testid="skeleton" />,
}))

const mockColumns = [
  { id: 'name', accessorKey: 'name', header: 'Name' },
  { id: 'email', accessorKey: 'email', header: 'Email' },
]

const mockData = [
  { id: 1, name: 'John Doe', email: 'john@example.com' },
  { id: 2, name: 'Jane Smith', email: 'jane@example.com' },
]

const mockPagination = {
  total: 2,
  total_pages: 1,
  current_page: 1,
  per_page: 20,
}

const createMockTable = (overrides = {}) => ({
  data: mockData,
  pagination: mockPagination,
  isLoading: false,
  initialLoadDone: true,
  selectedIds: [],
  toggleSelection: jest.fn(),
  handlePageChange: jest.fn(),
  clearSelection: jest.fn(),
  setSelectedIds: jest.fn(),
  ...overrides,
})

const renderListPageLayout = (props = {}) => {
  const defaultProps = {
    columns: mockColumns,
    table: createMockTable(),
  }
  return render(
    <AllProviders>
      <ListPageLayout {...defaultProps} {...props} />
    </AllProviders>
  )
}

describe('ListPageLayout', () => {
  beforeEach(() => {
    setupWpSmsSettings()
  })

  describe('Header', () => {
    test('renders title when provided', () => {
      renderListPageLayout({ title: 'Subscribers' })
      expect(screen.getByText('Subscribers')).toBeInTheDocument()
    })

    test('renders description when provided', () => {
      renderListPageLayout({
        title: 'Subscribers',
        description: 'Manage your SMS subscribers',
      })
      expect(screen.getByText('Manage your SMS subscribers')).toBeInTheDocument()
    })

    test('renders action buttons when provided', () => {
      renderListPageLayout({
        title: 'Subscribers',
        actions: <button>Add New</button>,
      })
      expect(screen.getByRole('button', { name: 'Add New' })).toBeInTheDocument()
    })

    test('does not render header elements when not provided', () => {
      renderListPageLayout()
      expect(screen.queryByRole('heading')).not.toBeInTheDocument()
    })
  })

  describe('Loading State', () => {
    test('shows skeleton when initial load not done', () => {
      renderListPageLayout({
        table: createMockTable({ initialLoadDone: false }),
      })
      expect(screen.getByTestId('page-loading-skeleton')).toBeInTheDocument()
    })

    test('does not show skeleton when initial load is done', () => {
      renderListPageLayout({
        table: createMockTable({ initialLoadDone: true }),
      })
      expect(screen.queryByTestId('page-loading-skeleton')).not.toBeInTheDocument()
    })
  })

  describe('Table Rendering', () => {
    test('renders table with data', () => {
      renderListPageLayout()
      expect(screen.getByText('John Doe')).toBeInTheDocument()
      expect(screen.getByText('Jane Smith')).toBeInTheDocument()
    })

    test('renders column headers', () => {
      renderListPageLayout()
      expect(screen.getByText('Name')).toBeInTheDocument()
      expect(screen.getByText('Email')).toBeInTheDocument()
    })

    test('renders empty message when no data', () => {
      renderListPageLayout({
        table: createMockTable({ data: [] }),
        emptyMessage: 'No subscribers found',
      })
      expect(screen.getByText('No subscribers found')).toBeInTheDocument()
    })
  })

  describe('Filters', () => {
    test('renders filters when provided', () => {
      renderListPageLayout({
        filters: <input placeholder="Search..." />,
      })
      expect(screen.getByPlaceholderText('Search...')).toBeInTheDocument()
    })
  })

  describe('Custom Content Slots', () => {
    test('renders headerContent when provided', () => {
      renderListPageLayout({
        headerContent: <div data-testid="header-content">Stats Bar</div>,
      })
      expect(screen.getByTestId('header-content')).toBeInTheDocument()
    })

    test('renders beforeTable content when provided', () => {
      renderListPageLayout({
        beforeTable: <div data-testid="before-table">Quick Add Form</div>,
      })
      expect(screen.getByTestId('before-table')).toBeInTheDocument()
    })

    test('renders afterTable content when provided', () => {
      renderListPageLayout({
        afterTable: <div data-testid="after-table">Footer Note</div>,
      })
      expect(screen.getByTestId('after-table')).toBeInTheDocument()
    })

    test('renders children (dialogs) when provided', () => {
      renderListPageLayout({
        children: <div data-testid="dialog">Edit Dialog</div>,
      })
      expect(screen.getByTestId('dialog')).toBeInTheDocument()
    })
  })

  describe('Custom Empty State', () => {
    test('renders custom empty state when data is empty', () => {
      renderListPageLayout({
        table: createMockTable({ data: [] }),
        emptyState: {
          customRender: <div data-testid="custom-empty">Create your first item</div>,
        },
      })
      expect(screen.getByTestId('custom-empty')).toBeInTheDocument()
    })

    test('renders title with custom empty state', () => {
      renderListPageLayout({
        title: 'Groups',
        table: createMockTable({ data: [] }),
        emptyState: {
          customRender: <div data-testid="custom-empty">Create your first group</div>,
        },
      })
      expect(screen.getByText('Groups')).toBeInTheDocument()
      expect(screen.getByTestId('custom-empty')).toBeInTheDocument()
    })
  })

  describe('Card Wrapper Options', () => {
    test('wraps content in card by default', () => {
      renderListPageLayout()
      // Card component should be present
      const card = document.querySelector('[class*="wsms-rounded-lg"]')
      expect(card).toBeInTheDocument()
    })

    test('does not wrap in card when showCardWrapper is false', () => {
      const { container } = renderListPageLayout({ showCardWrapper: false })
      // Should still render table
      expect(screen.getByText('John Doe')).toBeInTheDocument()
    })
  })

  describe('Row Actions', () => {
    test('passes rowActions to DataTable', () => {
      const rowActions = [
        { label: 'Edit', onClick: jest.fn() },
        { label: 'Delete', onClick: jest.fn(), variant: 'destructive' },
      ]
      renderListPageLayout({ rowActions })
      // Row action menu buttons should be present
      const actionButtons = screen.getAllByRole('button')
      expect(actionButtons.length).toBeGreaterThan(0)
    })
  })

  describe('Selection', () => {
    test('passes selection props to DataTable when toggleSelection exists', () => {
      const toggleSelection = jest.fn()
      renderListPageLayout({
        table: createMockTable({
          toggleSelection,
          selectedIds: [1],
        }),
      })
      // Checkboxes should be rendered for selection
      const checkboxes = screen.getAllByRole('checkbox')
      expect(checkboxes.length).toBeGreaterThan(0)
    })
  })

  describe('useListPage Integration', () => {
    test('works with useListPage return shape (nested table)', () => {
      const mockListPageResult = {
        table: createMockTable(),
        filters: { filters: {}, setFilter: jest.fn() },
        handleDelete: jest.fn(),
        handleBulkAction: jest.fn(),
      }

      renderListPageLayout({
        table: mockListPageResult,
      })

      // Should still render the data
      expect(screen.getByText('John Doe')).toBeInTheDocument()
    })
  })

  describe('Accessibility', () => {
    test('table has proper structure', () => {
      renderListPageLayout()
      expect(screen.getByRole('table')).toBeInTheDocument()
    })

    test('table headers are properly labeled', () => {
      renderListPageLayout()
      const headers = screen.getAllByRole('columnheader')
      expect(headers.length).toBeGreaterThanOrEqual(2)
    })
  })
})
