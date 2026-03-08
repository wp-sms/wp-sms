import React from 'react'
import PropTypes from 'prop-types'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { DataTable } from '@/components/ui/data-table'
import { PageLoadingSkeleton } from '@/components/ui/skeleton'
import { cn, __ } from '@/lib/utils'

/**
 * ListPageLayout - Standardized layout for list/table pages
 *
 * Provides a consistent structure for pages that display data tables with:
 * - Header with title, description, and action buttons
 * - Optional filter bar
 * - DataTable with pagination and row actions
 * - Slot for dialogs and modals
 *
 * Works seamlessly with useListPage hook for minimal boilerplate.
 *
 * @example
 * // With useListPage hook
 * const { filters, table, handleDelete, handleBulkAction } = useListPage({
 *   fetchFn: subscribersApi.getSubscribers,
 *   deleteFn: subscribersApi.deleteSubscriber,
 *   bulkActionFn: subscribersApi.bulkAction,
 *   initialFilters: { search: '', status: 'all' },
 * })
 *
 * return (
 *   <ListPageLayout
 *     title={__('Subscribers')}
 *     description={__('Manage your SMS subscribers')}
 *     actions={<Button onClick={() => editDialog.open()}>Add New</Button>}
 *     filters={<SubscriberFilters {...filters} />}
 *     table={table}
 *     columns={columns}
 *     rowActions={rowActions}
 *     bulkActions={bulkActions}
 *     emptyState={{
 *       icon: Users,
 *       message: __('No subscribers yet'),
 *       action: <Button onClick={() => editDialog.open()}>Add First Subscriber</Button>
 *     }}
 *   >
 *     <EditSubscriberDialog {...editDialog} />
 *     <DeleteConfirmDialog {...deleteDialog} />
 *   </ListPageLayout>
 * )
 */
export function ListPageLayout({
  // Header
  title,
  description,
  actions,

  // Filters (rendered above table inside card)
  filters,

  // Table data - can be useDataTable or useListPage result
  table,

  // Table configuration
  columns,
  rowActions,
  bulkActions,
  getRowId,
  onRowClick,
  onSort,

  // Empty state
  emptyState,
  emptyMessage,
  emptyIcon,

  // Custom content
  headerContent,
  beforeTable,
  afterTable,
  children,

  // Style
  className,
  cardClassName,
  tableClassName,

  // Options
  showCardWrapper = true,
  showHeaderInCard = false,
  loadingRows = 5,
}) {
  // Handle both useDataTable and useListPage return shapes
  const tableData = table?.table || table
  const {
    data = [],
    pagination = {},
    isLoading = false,
    initialLoadDone = true,
    selectedIds = [],
    toggleSelection,
    handlePageChange,
  } = tableData || {}

  // Show skeleton during initial load
  if (!initialLoadDone) {
    return <PageLoadingSkeleton />
  }

  // Build selection prop for DataTable
  const selection = toggleSelection
    ? {
        selected: selectedIds,
        onSelect: (id, checked) => {
          toggleSelection(id)
        },
        onSelectAll: (ids) => {
          if (ids.length === 0) {
            tableData.clearSelection?.()
          } else {
            tableData.setSelectedIds?.(ids)
          }
        },
      }
    : undefined

  // Build pagination prop for DataTable
  const paginationProp = pagination
    ? {
        page: pagination.current_page,
        perPage: pagination.per_page,
        total: pagination.total,
        totalPages: pagination.total_pages,
        onPageChange: handlePageChange,
      }
    : undefined

  // Empty state configuration
  const emptyConfig = emptyState || {}
  const showEmptyState = !isLoading && data.length === 0 && emptyConfig.customRender

  // Custom empty state render
  if (showEmptyState) {
    return (
      <div className={cn('wsms-space-y-6 wsms-stagger-children', className)}>
        {/* Header (if provided and not in card) */}
        {title && !showHeaderInCard && (
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4">
            <div>
              <h1 className="wsms-text-xl wsms-font-semibold wsms-text-foreground">{title}</h1>
              {description && (
                <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mt-1">{description}</p>
              )}
            </div>
            {actions && <div className="wsms-flex wsms-gap-2">{actions}</div>}
          </div>
        )}
        {emptyConfig.customRender}
        {children}
      </div>
    )
  }

  // Table content
  const tableContent = (
    <>
      {filters && <div className="wsms-mb-4">{filters}</div>}
      {beforeTable}
      <DataTable
        columns={columns}
        data={data}
        loading={isLoading}
        pagination={paginationProp}
        selection={selection}
        rowActions={rowActions}
        bulkActions={bulkActions}
        getRowId={getRowId}
        onRowClick={onRowClick}
        onSort={onSort}
        emptyMessage={emptyConfig.message || emptyMessage || __('No items found')}
        emptyIcon={emptyConfig.icon || emptyIcon}
        className={tableClassName}
      />
      {afterTable}
    </>
  )

  return (
    <div className={cn('wsms-space-y-6 wsms-stagger-children', className)}>
      {/* Header (outside card) */}
      {title && !showHeaderInCard && (
        <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4">
          <div>
            <h1 className="wsms-text-xl wsms-font-semibold wsms-text-foreground">{title}</h1>
            {description && (
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mt-1">{description}</p>
            )}
          </div>
          {actions && <div className="wsms-flex wsms-gap-2">{actions}</div>}
        </div>
      )}

      {/* Optional custom header content */}
      {headerContent}

      {/* Main content */}
      {showCardWrapper ? (
        <Card className={cardClassName}>
          {/* Header (inside card) */}
          {title && showHeaderInCard && (
            <CardHeader className="wsms-flex wsms-flex-row wsms-items-center wsms-justify-between wsms-space-y-0 wsms-pb-4">
              <div>
                <CardTitle>{title}</CardTitle>
                {description && <CardDescription className="wsms-mt-1.5">{description}</CardDescription>}
              </div>
              {actions && <div className="wsms-flex wsms-gap-2">{actions}</div>}
            </CardHeader>
          )}
          <CardContent className={cn(!showHeaderInCard && !title && 'wsms-pt-6', 'wsms-p-0')}>
            <div className="wsms-px-6 wsms-py-4">{filters}</div>
            {beforeTable && <div className="wsms-px-6">{beforeTable}</div>}
            <DataTable
              columns={columns}
              data={data}
              loading={isLoading}
              pagination={paginationProp}
              selection={selection}
              rowActions={rowActions}
              bulkActions={bulkActions}
              getRowId={getRowId}
              onRowClick={onRowClick}
              onSort={onSort}
              emptyMessage={emptyConfig.message || emptyMessage || __('No items found')}
              emptyIcon={emptyConfig.icon || emptyIcon}
              className={tableClassName}
            />
            {afterTable && <div className="wsms-px-6 wsms-pb-4">{afterTable}</div>}
          </CardContent>
        </Card>
      ) : (
        tableContent
      )}

      {/* Dialogs, modals, and other content */}
      {children}
    </div>
  )
}

ListPageLayout.propTypes = {
  // Header
  title: PropTypes.string,
  description: PropTypes.string,
  actions: PropTypes.node,

  // Filters
  filters: PropTypes.node,

  // Table data from useDataTable or useListPage
  table: PropTypes.shape({
    data: PropTypes.array,
    pagination: PropTypes.object,
    isLoading: PropTypes.bool,
    initialLoadDone: PropTypes.bool,
    selectedIds: PropTypes.array,
    toggleSelection: PropTypes.func,
    handlePageChange: PropTypes.func,
    clearSelection: PropTypes.func,
    setSelectedIds: PropTypes.func,
  }),

  // Table configuration
  columns: PropTypes.array.isRequired,
  rowActions: PropTypes.array,
  bulkActions: PropTypes.array,
  getRowId: PropTypes.func,
  onRowClick: PropTypes.func,
  onSort: PropTypes.func,

  // Empty state
  emptyState: PropTypes.shape({
    icon: PropTypes.elementType,
    message: PropTypes.string,
    action: PropTypes.node,
    customRender: PropTypes.node,
  }),
  emptyMessage: PropTypes.string,
  emptyIcon: PropTypes.elementType,

  // Custom content slots
  headerContent: PropTypes.node,
  beforeTable: PropTypes.node,
  afterTable: PropTypes.node,
  children: PropTypes.node,

  // Style
  className: PropTypes.string,
  cardClassName: PropTypes.string,
  tableClassName: PropTypes.string,

  // Options
  showCardWrapper: PropTypes.bool,
  showHeaderInCard: PropTypes.bool,
  loadingRows: PropTypes.number,
}

export default ListPageLayout
