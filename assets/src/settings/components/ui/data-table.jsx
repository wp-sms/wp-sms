import * as React from 'react'
import { useState, useEffect, useMemo, useCallback } from 'react'
import PropTypes from 'prop-types'
import {
  ChevronUp,
  ChevronDown,
  ChevronsUpDown,
  ChevronLeft,
  ChevronRight,
  Search,
  MoreHorizontal,
  Inbox,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Checkbox } from '@/components/ui/checkbox'
import { Skeleton } from '@/components/ui/skeleton'
import * as DropdownMenu from '@radix-ui/react-dropdown-menu'

// Debounce hook
function useDebounce(value, delay) {
  const [debouncedValue, setDebouncedValue] = useState(value)

  useEffect(() => {
    const handler = setTimeout(() => {
      setDebouncedValue(value)
    }, delay)

    return () => {
      clearTimeout(handler)
    }
  }, [value, delay])

  return debouncedValue
}

// Sort indicator component
function SortIndicator({ direction }) {
  if (!direction) {
    return <ChevronsUpDown className="wsms-h-3.5 wsms-w-3.5 wsms-text-muted-foreground/50" />
  }
  return direction === 'asc' ? (
    <ChevronUp className="wsms-h-3.5 wsms-w-3.5 wsms-text-primary" />
  ) : (
    <ChevronDown className="wsms-h-3.5 wsms-w-3.5 wsms-text-primary" />
  )
}

// Table skeleton loader
function TableSkeleton({ columns, rows = 5, hasSelection }) {
  const colCount = columns + (hasSelection ? 1 : 0)
  return (
    <>
      {Array.from({ length: rows }).map((_, rowIndex) => (
        <tr
          key={rowIndex}
          className="wsms-border-b wsms-border-border last:wsms-border-0"
        >
          {Array.from({ length: colCount }).map((_, colIndex) => (
            <td key={colIndex} className="wsms-p-3">
              <Skeleton className="wsms-h-4 wsms-w-full" />
            </td>
          ))}
        </tr>
      ))}
    </>
  )
}

// Empty state component
function EmptyState({ icon: Icon = Inbox, message = 'No items found' }) {
  return (
    <div className="wsms-flex wsms-flex-col wsms-items-center wsms-justify-center wsms-py-12 wsms-px-4">
      <div className="wsms-flex wsms-h-14 wsms-w-14 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-muted/50 wsms-mb-4">
        <Icon className="wsms-h-6 wsms-w-6 wsms-text-muted-foreground/70" strokeWidth={1.5} />
      </div>
      <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-text-center">{message}</p>
    </div>
  )
}

// Bulk actions dropdown
function BulkActionsDropdown({ actions, selectedCount, onAction }) {
  if (!actions || actions.length === 0) return null

  return (
    <DropdownMenu.Root>
      <DropdownMenu.Trigger asChild>
        <Button variant="outline" size="sm" className="wsms-gap-1.5">
          <span>Actions</span>
          <span className="wsms-flex wsms-h-5 wsms-min-w-5 wsms-items-center wsms-justify-center wsms-rounded wsms-bg-primary/10 wsms-px-1.5 wsms-text-[11px] wsms-font-semibold wsms-text-primary">
            {selectedCount}
          </span>
        </Button>
      </DropdownMenu.Trigger>
      <DropdownMenu.Portal>
        <DropdownMenu.Content
          className="wsms-z-50 wsms-min-w-[140px] wsms-overflow-hidden wsms-rounded-md wsms-border wsms-border-border wsms-bg-card wsms-p-1 wsms-shadow-lg"
          sideOffset={5}
        >
          {actions.map((action, index) => (
            <DropdownMenu.Item
              key={index}
              className={cn(
                'wsms-relative wsms-flex wsms-cursor-pointer wsms-select-none wsms-items-center wsms-rounded-sm wsms-px-2.5 wsms-py-1.5 wsms-text-[13px] wsms-outline-none wsms-transition-colors',
                'focus:wsms-bg-accent focus:wsms-text-accent-foreground',
                'data-[disabled]:wsms-pointer-events-none data-[disabled]:wsms-opacity-50',
                action.variant === 'destructive' && 'wsms-text-destructive focus:wsms-bg-destructive/10'
              )}
              onClick={() => onAction(action)}
            >
              {action.icon && <action.icon className="wsms-mr-2 wsms-h-4 wsms-w-4" />}
              {action.label}
            </DropdownMenu.Item>
          ))}
        </DropdownMenu.Content>
      </DropdownMenu.Portal>
    </DropdownMenu.Root>
  )
}

// Row actions dropdown
function RowActionsDropdown({ actions, row }) {
  if (!actions || actions.length === 0) return null

  return (
    <DropdownMenu.Root>
      <DropdownMenu.Trigger asChild>
        <Button variant="ghost" size="icon" className="wsms-h-8 wsms-w-8">
          <MoreHorizontal className="wsms-h-4 wsms-w-4" />
          <span className="wsms-sr-only">Open menu</span>
        </Button>
      </DropdownMenu.Trigger>
      <DropdownMenu.Portal>
        <DropdownMenu.Content
          className="wsms-z-50 wsms-min-w-[140px] wsms-overflow-hidden wsms-rounded-md wsms-border wsms-border-border wsms-bg-card wsms-p-1 wsms-shadow-lg"
          sideOffset={5}
          align="end"
        >
          {actions.map((action, index) => (
            <DropdownMenu.Item
              key={index}
              className={cn(
                'wsms-relative wsms-flex wsms-cursor-pointer wsms-select-none wsms-items-center wsms-rounded-sm wsms-px-2.5 wsms-py-1.5 wsms-text-[13px] wsms-outline-none wsms-transition-colors',
                'focus:wsms-bg-accent focus:wsms-text-accent-foreground',
                'data-[disabled]:wsms-pointer-events-none data-[disabled]:wsms-opacity-50',
                action.variant === 'destructive' && 'wsms-text-destructive focus:wsms-bg-destructive/10'
              )}
              onClick={() => action.onClick(row)}
            >
              {action.icon && <action.icon className="wsms-mr-2 wsms-h-4 wsms-w-4" />}
              {action.label}
            </DropdownMenu.Item>
          ))}
        </DropdownMenu.Content>
      </DropdownMenu.Portal>
    </DropdownMenu.Root>
  )
}

// Pagination component
function Pagination({
  currentPage,
  totalPages,
  totalItems,
  perPage,
  onPageChange,
}) {
  const startItem = (currentPage - 1) * perPage + 1
  const endItem = Math.min(currentPage * perPage, totalItems)

  // Generate page numbers to show
  const pages = useMemo(() => {
    const items = []
    const maxVisible = 5
    let start = Math.max(1, currentPage - Math.floor(maxVisible / 2))
    let end = Math.min(totalPages, start + maxVisible - 1)

    if (end - start + 1 < maxVisible) {
      start = Math.max(1, end - maxVisible + 1)
    }

    for (let i = start; i <= end; i++) {
      items.push(i)
    }
    return items
  }, [currentPage, totalPages])

  if (totalPages <= 1) return null

  return (
    <div className="wsms-flex wsms-flex-col sm:wsms-flex-row wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-4 wsms-py-3 wsms-border-t wsms-border-border wsms-bg-muted/20">
      <p className="wsms-text-[12px] wsms-text-muted-foreground">
        Showing <span className="wsms-font-medium wsms-text-foreground">{startItem}</span> to{' '}
        <span className="wsms-font-medium wsms-text-foreground">{endItem}</span> of{' '}
        <span className="wsms-font-medium wsms-text-foreground">{totalItems}</span> results
      </p>

      <div className="wsms-flex wsms-items-center wsms-gap-1">
        <Button
          variant="outline"
          size="icon"
          className="wsms-h-8 wsms-w-8"
          onClick={() => onPageChange(currentPage - 1)}
          disabled={currentPage <= 1}
        >
          <ChevronLeft className="wsms-h-4 wsms-w-4" />
        </Button>

        {pages[0] > 1 && (
          <>
            <Button
              variant={currentPage === 1 ? 'default' : 'ghost'}
              size="icon"
              className="wsms-h-8 wsms-w-8 wsms-text-[12px]"
              onClick={() => onPageChange(1)}
            >
              1
            </Button>
            {pages[0] > 2 && (
              <span className="wsms-px-1 wsms-text-muted-foreground">...</span>
            )}
          </>
        )}

        {pages.map((page) => (
          <Button
            key={page}
            variant={currentPage === page ? 'default' : 'ghost'}
            size="icon"
            className="wsms-h-8 wsms-w-8 wsms-text-[12px]"
            onClick={() => onPageChange(page)}
          >
            {page}
          </Button>
        ))}

        {pages[pages.length - 1] < totalPages && (
          <>
            {pages[pages.length - 1] < totalPages - 1 && (
              <span className="wsms-px-1 wsms-text-muted-foreground">...</span>
            )}
            <Button
              variant={currentPage === totalPages ? 'default' : 'ghost'}
              size="icon"
              className="wsms-h-8 wsms-w-8 wsms-text-[12px]"
              onClick={() => onPageChange(totalPages)}
            >
              {totalPages}
            </Button>
          </>
        )}

        <Button
          variant="outline"
          size="icon"
          className="wsms-h-8 wsms-w-8"
          onClick={() => onPageChange(currentPage + 1)}
          disabled={currentPage >= totalPages}
        >
          <ChevronRight className="wsms-h-4 wsms-w-4" />
        </Button>
      </div>
    </div>
  )
}

/**
 * DataTable - A professional data table component with sorting, selection, and pagination
 */
export function DataTable({
  columns,
  data = [],
  loading = false,
  pagination,
  selection,
  onRowClick,
  onSort,
  onSearch,
  bulkActions,
  rowActions,
  searchPlaceholder = 'Search...',
  emptyMessage = 'No items found',
  emptyIcon,
  className,
  getRowId = (row) => row.id,
}) {
  const [searchValue, setSearchValue] = useState('')
  const [sortConfig, setSortConfig] = useState({ key: null, direction: null })

  const debouncedSearch = useDebounce(searchValue, 300)

  // Trigger search callback when debounced value changes
  useEffect(() => {
    if (onSearch) {
      onSearch(debouncedSearch)
    }
  }, [debouncedSearch, onSearch])

  // Handle column sort
  const handleSort = useCallback(
    (columnKey) => {
      let direction = 'asc'
      if (sortConfig.key === columnKey && sortConfig.direction === 'asc') {
        direction = 'desc'
      } else if (sortConfig.key === columnKey && sortConfig.direction === 'desc') {
        direction = null
      }

      setSortConfig({ key: direction ? columnKey : null, direction })
      if (onSort) {
        onSort(columnKey, direction)
      }
    },
    [sortConfig, onSort]
  )

  // Check if all visible rows are selected
  const allSelected = useMemo(() => {
    if (!selection || !data.length) return false
    return data.every((row) => selection.selected.includes(getRowId(row)))
  }, [selection, data, getRowId])

  // Check if some rows are selected (for indeterminate state)
  const someSelected = useMemo(() => {
    if (!selection || !data.length) return false
    const selectedCount = data.filter((row) => selection.selected.includes(getRowId(row))).length
    return selectedCount > 0 && selectedCount < data.length
  }, [selection, data, getRowId])

  // Handle select all
  const handleSelectAll = useCallback(
    (checked) => {
      if (!selection) return
      if (checked) {
        selection.onSelectAll(data.map(getRowId))
      } else {
        selection.onSelectAll([])
      }
    },
    [selection, data, getRowId]
  )

  // Handle row selection
  const handleSelectRow = useCallback(
    (rowId, checked) => {
      if (!selection) return
      selection.onSelect(rowId, checked)
    },
    [selection]
  )

  // Handle bulk action
  const handleBulkAction = useCallback(
    (action) => {
      if (action.onClick && selection) {
        action.onClick(selection.selected)
      }
    },
    [selection]
  )

  const hasSelection = !!selection
  const selectedCount = selection?.selected?.length || 0

  return (
    <div className={cn('wsms-w-full', className)}>
      {/* Toolbar */}
      {(onSearch || (bulkActions && selectedCount > 0)) && (
        <div className="wsms-flex wsms-flex-col sm:wsms-flex-row wsms-items-start sm:wsms-items-center wsms-justify-between wsms-gap-3 wsms-p-4 wsms-border-b wsms-border-border">
          <div className="wsms-flex wsms-items-center wsms-gap-3 wsms-w-full sm:wsms-w-auto">
            {selectedCount > 0 && bulkActions ? (
              <BulkActionsDropdown
                actions={bulkActions}
                selectedCount={selectedCount}
                onAction={handleBulkAction}
              />
            ) : null}
          </div>

          {onSearch && (
            <div className="wsms-relative wsms-w-full sm:wsms-w-64">
              <Search className="wsms-absolute wsms-left-2.5 wsms-top-1/2 wsms-h-4 wsms-w-4 wsms--translate-y-1/2 wsms-text-muted-foreground" />
              <Input
                type="search"
                placeholder={searchPlaceholder}
                value={searchValue}
                onChange={(e) => setSearchValue(e.target.value)}
                className="wsms-pl-8 wsms-h-9"
              />
            </div>
          )}
        </div>
      )}

      {/* Table */}
      <div className="wsms-overflow-x-auto">
        <table className="wsms-w-full wsms-border-collapse">
          <thead>
            <tr className="wsms-border-b wsms-border-border wsms-bg-muted/30">
              {hasSelection && (
                <th className="wsms-w-12 wsms-p-3 wsms-text-left">
                  <Checkbox
                    checked={allSelected}
                    indeterminate={someSelected}
                    onCheckedChange={handleSelectAll}
                    aria-label="Select all"
                  />
                </th>
              )}
              {columns.map((column) => (
                <th
                  key={column.id || column.accessorKey}
                  className={cn(
                    'wsms-p-3 wsms-text-left wsms-text-[12px] wsms-font-semibold wsms-text-muted-foreground wsms-uppercase wsms-tracking-wide',
                    column.sortable && 'wsms-cursor-pointer wsms-select-none hover:wsms-text-foreground wsms-transition-colors',
                    column.className
                  )}
                  style={{ width: column.width }}
                  onClick={() => column.sortable && handleSort(column.id || column.accessorKey)}
                >
                  <div className="wsms-flex wsms-items-center wsms-gap-1.5">
                    <span>{column.header}</span>
                    {column.sortable && (
                      <SortIndicator
                        direction={
                          sortConfig.key === (column.id || column.accessorKey)
                            ? sortConfig.direction
                            : null
                        }
                      />
                    )}
                  </div>
                </th>
              ))}
              {rowActions && <th className="wsms-w-12 wsms-p-3" />}
            </tr>
          </thead>
          <tbody>
            {loading ? (
              <TableSkeleton
                columns={columns.length + (rowActions ? 1 : 0)}
                rows={pagination?.perPage || 5}
                hasSelection={hasSelection}
              />
            ) : data.length === 0 ? (
              <tr>
                <td colSpan={columns.length + (hasSelection ? 1 : 0) + (rowActions ? 1 : 0)}>
                  <EmptyState icon={emptyIcon} message={emptyMessage} />
                </td>
              </tr>
            ) : (
              data.map((row, rowIndex) => {
                const rowId = getRowId(row)
                const isSelected = selection?.selected.includes(rowId)

                return (
                  <tr
                    key={rowId}
                    className={cn(
                      'wsms-border-b wsms-border-border last:wsms-border-0 wsms-transition-colors',
                      isSelected && 'wsms-bg-primary/5',
                      onRowClick && 'wsms-cursor-pointer',
                      'hover:wsms-bg-muted/40'
                    )}
                    onClick={() => onRowClick && onRowClick(row)}
                  >
                    {hasSelection && (
                      <td className="wsms-p-3" onClick={(e) => e.stopPropagation()}>
                        <Checkbox
                          checked={isSelected}
                          onCheckedChange={(checked) => handleSelectRow(rowId, checked)}
                          aria-label={`Select row ${rowIndex + 1}`}
                        />
                      </td>
                    )}
                    {columns.map((column) => {
                      const cellValue = column.accessorKey
                        ? row[column.accessorKey]
                        : column.accessorFn?.(row)

                      return (
                        <td
                          key={column.id || column.accessorKey}
                          className={cn('wsms-p-3 wsms-text-[13px] wsms-text-foreground', column.cellClassName)}
                        >
                          {column.cell ? column.cell({ row, value: cellValue }) : cellValue}
                        </td>
                      )
                    })}
                    {rowActions && (
                      <td className="wsms-p-3" onClick={(e) => e.stopPropagation()}>
                        <RowActionsDropdown actions={rowActions} row={row} />
                      </td>
                    )}
                  </tr>
                )
              })
            )}
          </tbody>
        </table>
      </div>

      {/* Pagination */}
      {pagination && !loading && data.length > 0 && (
        <Pagination
          currentPage={pagination.page}
          totalPages={pagination.totalPages}
          totalItems={pagination.total}
          perPage={pagination.perPage}
          onPageChange={pagination.onPageChange}
        />
      )}
    </div>
  )
}

DataTable.propTypes = {
  columns: PropTypes.arrayOf(
    PropTypes.shape({
      id: PropTypes.string,
      accessorKey: PropTypes.string,
      accessorFn: PropTypes.func,
      header: PropTypes.string.isRequired,
      cell: PropTypes.func,
      sortable: PropTypes.bool,
      width: PropTypes.oneOfType([PropTypes.string, PropTypes.number]),
      className: PropTypes.string,
      cellClassName: PropTypes.string,
    })
  ).isRequired,
  data: PropTypes.array,
  loading: PropTypes.bool,
  pagination: PropTypes.shape({
    page: PropTypes.number.isRequired,
    perPage: PropTypes.number.isRequired,
    total: PropTypes.number.isRequired,
    totalPages: PropTypes.number.isRequired,
    onPageChange: PropTypes.func.isRequired,
  }),
  selection: PropTypes.shape({
    selected: PropTypes.array.isRequired,
    onSelect: PropTypes.func.isRequired,
    onSelectAll: PropTypes.func.isRequired,
  }),
  onRowClick: PropTypes.func,
  onSort: PropTypes.func,
  onSearch: PropTypes.func,
  bulkActions: PropTypes.arrayOf(
    PropTypes.shape({
      label: PropTypes.string.isRequired,
      onClick: PropTypes.func.isRequired,
      icon: PropTypes.elementType,
      variant: PropTypes.oneOf(['default', 'destructive']),
    })
  ),
  rowActions: PropTypes.arrayOf(
    PropTypes.shape({
      label: PropTypes.string.isRequired,
      onClick: PropTypes.func.isRequired,
      icon: PropTypes.elementType,
      variant: PropTypes.oneOf(['default', 'destructive']),
    })
  ),
  searchPlaceholder: PropTypes.string,
  emptyMessage: PropTypes.string,
  emptyIcon: PropTypes.elementType,
  className: PropTypes.string,
  getRowId: PropTypes.func,
}

export default DataTable
