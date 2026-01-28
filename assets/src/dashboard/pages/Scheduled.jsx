import React, { useState, useCallback, useMemo } from 'react'
import {
  CalendarClock,
  RefreshCw,
  Send,
  Search,
  Clock,
  CheckCircle,
  XCircle,
  Loader2,
  AlertCircle,
  MessageSquare,
  X,
  Image,
  Trash2,
  Repeat,
  ExternalLink,
  StopCircle,
} from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { Label } from '@/components/ui/label'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Tabs, TabsList, TabsTrigger, TabsContent } from '@/components/ui/tabs'
import { DataTable } from '@/components/ui/data-table'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { ExportButton } from '@/components/shared/ExportButton'
import { DateRangePicker } from '@/components/shared/DateRangePicker'
import { DeleteConfirmDialog } from '@/components/shared/DeleteConfirmDialog'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogBody,
  DialogFooter,
} from '@/components/ui/dialog'
import { scheduledApi } from '@/api/scheduledApi'
import { repeatingApi } from '@/api/repeatingApi'
import { cn, formatDate, __, downloadCsv, getWpSettings } from '@/lib/utils'
import { useListPage } from '@/hooks/useListPage'
import { useFormDialog } from '@/hooks/useFormDialog'
import { useToast } from '@/components/ui/toaster'
import { useSettings } from '@/context/SettingsContext'
import {
  scheduledSmsColumns,
  getScheduledRowActions,
  getScheduledBulkActions,
  repeatingMessagesColumns,
  getRepeatingRowActions,
  getRepeatingBulkActions,
} from '@/lib/tableColumns'
import { PageLoadingSkeleton } from '@/components/ui/skeleton'

// ============================================
// Scheduled SMS Tab Component
// ============================================
function ScheduledSmsTab() {
  const { toast } = useToast()

  // Use useListPage for combined filter + table management
  const { filters, table } = useListPage({
    fetchFn: scheduledApi.getMessages,
    deleteFn: scheduledApi.deleteMessage,
    bulkActionFn: scheduledApi.bulkAction,
    initialFilters: { search: '', status: 'all', date_from: '', date_to: '' },
    messages: {
      deleteSuccess: __('Scheduled message deleted successfully'),
      bulkSuccess: __('Action completed successfully'),
    },
  })

  // Delete confirmation dialog using useFormDialog
  const deleteDialog = useFormDialog({
    saveFn: async (id) => {
      await scheduledApi.deleteMessage(id)
      table.removeItems([id])
    },
    successMessage: __('Scheduled message deleted successfully'),
  })

  // Edit dialog using useFormDialog
  const editDialog = useFormDialog({
    saveFn: async (id, data) => {
      await scheduledApi.updateMessage(id, {
        date: data.date,
        sender: data.sender,
        message: data.message,
      })
    },
    initialData: { date: '', sender: '', message: '' },
    onSuccess: () => table.refresh(),
    successMessage: __('Scheduled message updated successfully'),
  })

  // Handle edit click
  const handleEditClick = useCallback((row) => {
    editDialog.open({
      id: row.id,
      date: row.date ? row.date.replace(' ', 'T').slice(0, 16) : '',
      sender: row.sender || '',
      message: row.message || '',
    })
  }, [editDialog])

  // Dialog states
  const [viewMessage, setViewMessage] = useState(null)
  const [actionLoading, setActionLoading] = useState(null)
  const [bulkActionLoading, setBulkActionLoading] = useState(null)
  const [showBulkDeleteConfirm, setShowBulkDeleteConfirm] = useState(false)

  // Handle sort
  const handleSort = useCallback((columnKey, direction) => {
    table.fetch({
      page: 1,
      orderby: direction ? columnKey : undefined,
      order: direction || undefined,
    })
  }, [table])

  // Handle delete click - opens confirmation dialog
  const handleDeleteClick = useCallback((message) => {
    deleteDialog.open(message)
  }, [deleteDialog])

  // Handle delete confirm
  const handleDeleteConfirm = async () => {
    if (!deleteDialog.item) return
    try {
      await deleteDialog.save()
    } catch {
      // Error already handled by useFormDialog
    }
  }

  // Handle Send Now action
  const handleSendNow = useCallback(
    async (id) => {
      setActionLoading(id)
      try {
        await scheduledApi.sendNow(id)
        toast({ title: __('Message sent successfully'), variant: 'success' })
        table.refresh()
      } catch (error) {
        toast({ title: error.message || __('Failed to send message'), variant: 'destructive' })
      } finally {
        setActionLoading(null)
      }
    },
    [toast, table]
  )

  // Handle bulk action
  const handleBulkAction = useCallback(
    async (action, label) => {
      if (table.selectedIds.length === 0) return

      setBulkActionLoading(label)
      try {
        const result = await scheduledApi.bulkAction(action, table.selectedIds)
        toast({
          title: __('%d message(s) processed successfully').replace('%d', result.affected),
          variant: 'success',
        })
        table.clearSelection()
        table.fetch({ page: 1 })
      } catch (error) {
        toast({ title: error.message || __('Bulk action failed'), variant: 'destructive' })
      } finally {
        setBulkActionLoading(null)
      }
    },
    [toast, table]
  )

  // Handle bulk delete confirm
  const handleBulkDeleteConfirm = useCallback(async () => {
    setShowBulkDeleteConfirm(false)
    await handleBulkAction('delete', __('Delete Selected'))
  }, [handleBulkAction])

  // Handle export
  const handleExport = useCallback(async () => {
    const result = await scheduledApi.exportCsv({
      status: filters.debouncedFilters.status !== 'all' ? filters.debouncedFilters.status : undefined,
      date_from: filters.debouncedFilters.date_from || undefined,
      date_to: filters.debouncedFilters.date_to || undefined,
    })
    downloadCsv(result.data, result.filename)
    return { count: result.count }
  }, [filters.debouncedFilters])

  // Memoized row and bulk actions
  const rowActions = useMemo(
    () =>
      getScheduledRowActions({
        onView: (row) => setViewMessage(row),
        onEdit: handleEditClick,
        onSendNow: (row) => handleSendNow(row.id),
        onDelete: handleDeleteClick,
      }),
    [handleEditClick, handleSendNow, handleDeleteClick]
  )

  const bulkActions = useMemo(
    () =>
      getScheduledBulkActions({
        onDelete: () => setShowBulkDeleteConfirm(true),
        onSendAll: () => handleBulkAction('send', __('Send Selected Now')),
      }),
    [handleBulkAction]
  )

  // Stats
  const stats = table.stats || { total: 0, pending: 0, sent: 0, failed: 0 }

  // Loading skeleton
  if (!table.initialLoadDone) {
    return <PageLoadingSkeleton />
  }

  // Error state
  if (table.error) {
    return (
      <div className="wsms-space-y-6">
        <Card className="wsms-border-destructive">
          <CardContent className="wsms-py-8">
            <div className="wsms-flex wsms-flex-col wsms-items-center wsms-text-center">
              <AlertCircle className="wsms-h-12 wsms-w-12 wsms-text-destructive wsms-mb-4" />
              <h3 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-2">
                {__('Failed to load scheduled messages')}
              </h3>
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-4">
                {table.error}
              </p>
              <Button onClick={() => table.fetch({ page: 1 })}>
                <RefreshCw className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                {__('Try Again')}
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  // Empty state
  const hasNoMessages =
    table.data.length === 0 &&
    !filters.filters.search &&
    filters.filters.status === 'all' &&
    !filters.filters.date_from &&
    !filters.filters.date_to

  if (hasNoMessages) {
    return (
      <div className="wsms-space-y-6 wsms-stagger-children">
        <Card className="wsms-border-dashed">
          <CardContent className="wsms-py-16">
            <div className="wsms-flex wsms-flex-col wsms-items-center wsms-text-center wsms-max-w-md wsms-mx-auto">
              <div className="wsms-flex wsms-h-16 wsms-w-16 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-primary/10 wsms-mb-6">
                <CalendarClock className="wsms-h-8 wsms-w-8 wsms-text-primary" strokeWidth={1.5} />
              </div>
              <h3 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-2">
                {__('No scheduled messages')}
              </h3>
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-6">
                {__('Schedule SMS messages to be sent at a specific date and time. They will appear here until they are sent.')}
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="wsms-space-y-6 wsms-stagger-children">
      {/* Stats Header Bar */}
      <div className="wsms-px-4 xl:wsms-px-5 wsms-py-4 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
        <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4 xl:wsms-flex xl:wsms-items-center xl:wsms-justify-between xl:wsms-gap-4">
          <div className="wsms-contents xl:wsms-flex xl:wsms-items-center xl:wsms-gap-8">
            {/* Total */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-primary/10">
                <CalendarClock className="wsms-h-5 wsms-w-5 wsms-text-primary" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">{stats.total}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Total')}</p>
              </div>
            </div>

            <div className="wsms-hidden xl:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* Pending */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-amber-500/10">
                <Clock className="wsms-h-5 wsms-w-5 wsms-text-amber-600 dark:wsms-text-amber-400" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-amber-600 dark:wsms-text-amber-400">{stats.pending}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Pending')}</p>
              </div>
            </div>

            <div className="wsms-hidden xl:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* Sent */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-success/10">
                <CheckCircle className="wsms-h-5 wsms-w-5 wsms-text-success" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-success">{stats.sent}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Sent')}</p>
              </div>
            </div>

            {stats.failed > 0 && (
              <>
                <div className="wsms-hidden xl:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

                {/* Failed */}
                <div className="wsms-flex wsms-items-center wsms-gap-3">
                  <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-destructive/10">
                    <XCircle className="wsms-h-5 wsms-w-5 wsms-text-destructive" aria-hidden="true" />
                  </div>
                  <div>
                    <p className="wsms-text-xl wsms-font-bold wsms-text-destructive">{stats.failed}</p>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Failed')}</p>
                  </div>
                </div>
              </>
            )}
          </div>

          {/* Export Button */}
          <div className="wsms-col-span-2 xl:wsms-col-span-1 wsms-flex wsms-items-center wsms-justify-end wsms-gap-2 wsms-mt-2 xl:wsms-mt-0">
            <ExportButton
              onExport={handleExport}
              successMessage={__('Exported %d scheduled messages successfully')}
            />
          </div>
        </div>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="wsms-p-3">
          <div className="wsms-flex wsms-flex-col wsms-gap-3 xl:wsms-flex-row xl:wsms-items-center xl:wsms-gap-3">
            {/* Search */}
            <div className="wsms-relative wsms-w-full xl:wsms-w-[220px] xl:wsms-shrink-0">
              <Search
                className="wsms-absolute wsms-left-2.5 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-pointer-events-none"
                aria-hidden="true"
              />
              <Input
                type="text"
                value={filters.filters.search}
                onChange={(e) => filters.setFilter('search', e.target.value)}
                placeholder={__('Search messages...')}
                className="wsms-pl-8 wsms-h-9"
                aria-label={__('Search scheduled messages')}
              />
            </div>

            {/* Status Filter */}
            <Select
              value={filters.filters.status}
              onValueChange={(value) => filters.setFilter('status', value)}
            >
              <SelectTrigger className="wsms-h-9 wsms-w-full xl:wsms-w-[120px] wsms-text-[12px]" aria-label={__('Filter by status')}>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{__('All Status')}</SelectItem>
                <SelectItem value="pending">{__('Pending')}</SelectItem>
                <SelectItem value="sent">{__('Sent')}</SelectItem>
                <SelectItem value="failed">{__('Failed')}</SelectItem>
              </SelectContent>
            </Select>

            {/* Date Range */}
            <DateRangePicker
              from={filters.filters.date_from}
              to={filters.filters.date_to}
              onFromChange={(value) => filters.setFilter('date_from', value)}
              onToChange={(value) => filters.setFilter('date_to', value)}
            />

            {/* Actions */}
            <div className="wsms-flex wsms-items-center wsms-gap-2 xl:wsms-ml-auto">
              {/* Clear Filters */}
              {(filters.filters.search || filters.filters.status !== 'all' || filters.filters.date_from || filters.filters.date_to) && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => filters.resetFilters()}
                  className="wsms-h-9 wsms-px-2.5 wsms-text-muted-foreground hover:wsms-text-foreground"
                  aria-label={__('Clear all filters')}
                >
                  <X className="wsms-h-4 wsms-w-4" aria-hidden="true" />
                </Button>
              )}
              {/* Refresh */}
              <Button
                variant="outline"
                size="sm"
                onClick={() => table.fetch({ page: 1 })}
                className="wsms-h-9 wsms-px-2.5"
                aria-label={__('Refresh messages')}
              >
                <RefreshCw
                  className={cn('wsms-h-4 wsms-w-4', table.isLoading && 'wsms-animate-spin')}
                  aria-hidden="true"
                />
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Data Table */}
      <Card>
        <CardContent className="wsms-p-0">
          <DataTable
            columns={scheduledSmsColumns}
            data={table.data}
            loading={table.isLoading}
            pagination={{
              total: table.pagination.total,
              totalPages: table.pagination.total_pages,
              page: table.pagination.current_page,
              perPage: table.pagination.per_page,
              onPageChange: table.handlePageChange,
            }}
            selection={{
              selected: table.selectedIds,
              onSelect: table.toggleSelection,
              onSelectAll: (checked) => {
                if (checked) {
                  table.toggleSelectAll()
                } else {
                  table.clearSelection()
                }
              },
            }}
            onSort={handleSort}
            rowActions={rowActions}
            bulkActions={bulkActions}
            bulkActionLoading={bulkActionLoading}
            emptyMessage={__('No scheduled messages match your filters')}
            emptyIcon={CalendarClock}
          />
        </CardContent>
      </Card>

      {/* View Message Dialog */}
      <Dialog open={!!viewMessage} onOpenChange={() => setViewMessage(null)}>
        <DialogContent size="lg">
          <DialogHeader>
            <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <CalendarClock className="wsms-h-4 wsms-w-4 wsms-text-primary" aria-hidden="true" />
              {__('Scheduled Message Details')}
            </DialogTitle>
            <DialogDescription>
              {__('Scheduled for')} {viewMessage && formatDate(viewMessage.date, { hour: '2-digit', minute: '2-digit' })}
            </DialogDescription>
          </DialogHeader>
          <DialogBody>
            {viewMessage && (
              <div className="wsms-space-y-4">
                {/* Status and Info Row */}
                <div className="wsms-flex wsms-items-center wsms-gap-4 wsms-p-4 wsms-rounded-lg wsms-bg-muted/30">
                  <div className="wsms-flex-1">
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">{__('Status')}</p>
                    <StatusBadge variant={viewMessage.status === 'sent' ? 'success' : viewMessage.status === 'failed' ? 'failed' : 'warning'}>
                      {viewMessage.status === 'sent' ? __('Sent') : viewMessage.status === 'failed' ? __('Failed') : __('Pending')}
                    </StatusBadge>
                  </div>
                  <div className="wsms-w-px wsms-h-8 wsms-bg-border" aria-hidden="true" />
                  <div className="wsms-flex-1">
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">{__('Recipients')}</p>
                    <p className="wsms-text-[13px] wsms-font-medium">{viewMessage.recipient_count || 1}</p>
                  </div>
                  {viewMessage.sender && (
                    <>
                      <div className="wsms-w-px wsms-h-8 wsms-bg-border" aria-hidden="true" />
                      <div className="wsms-flex-1">
                        <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">{__('Sender')}</p>
                        <p className="wsms-text-[13px] wsms-font-medium">{viewMessage.sender}</p>
                      </div>
                    </>
                  )}
                </div>

                {/* Recipient(s) */}
                <div>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">{__('Recipient(s)')}</p>
                  <p className="wsms-text-[13px] wsms-break-all wsms-font-mono wsms-p-2 wsms-rounded wsms-bg-muted/30">
                    {viewMessage.recipient}
                  </p>
                </div>

                {/* Message */}
                <div>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">{__('Message')}</p>
                  <div className="wsms-p-4 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
                    <p className="wsms-text-[13px] wsms-whitespace-pre-wrap">{viewMessage.message}</p>
                  </div>
                </div>

                {/* Media (MMS) */}
                {viewMessage.media && Array.isArray(viewMessage.media) && viewMessage.media.length > 0 && (
                  <div>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1 wsms-flex wsms-items-center wsms-gap-1">
                      <Image className="wsms-h-3 wsms-w-3" aria-hidden="true" />
                      {__('Media')}
                    </p>
                    <div className="wsms-flex wsms-flex-wrap wsms-gap-2">
                      {viewMessage.media.map((url, idx) => (
                        <a
                          key={idx}
                          href={url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="wsms-block wsms-rounded-lg wsms-overflow-hidden wsms-border wsms-border-border hover:wsms-border-primary wsms-transition-colors"
                        >
                          <img
                            src={url}
                            alt={`Media ${idx + 1}`}
                            className="wsms-max-w-[150px] wsms-max-h-[100px] wsms-object-cover"
                          />
                        </a>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )}
          </DialogBody>
          <DialogFooter>
            <Button variant="outline" onClick={() => setViewMessage(null)}>
              {__('Close')}
            </Button>
            {viewMessage?.status === 'pending' && (
              <Button onClick={() => { handleSendNow(viewMessage?.id); setViewMessage(null) }}>
                <Send className="wsms-h-4 wsms-w-4 wsms-mr-2" aria-hidden="true" />
                {__('Send Now')}
              </Button>
            )}
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <DeleteConfirmDialog
        isOpen={deleteDialog.isOpen}
        onClose={deleteDialog.close}
        onConfirm={handleDeleteConfirm}
        isSaving={deleteDialog.isSaving}
        title={__('Delete Scheduled Message')}
        description={__('Are you sure you want to delete this scheduled message?')}
      >
        <div className="wsms-p-4 wsms-rounded-md wsms-bg-muted/50 wsms-border wsms-border-border wsms-overflow-hidden">
          <div className="wsms-space-y-2">
            <div className="wsms-flex wsms-items-start wsms-gap-2">
              <span className="wsms-text-[12px] wsms-text-muted-foreground wsms-shrink-0">{__('To')}:</span>
              <span className="wsms-text-[13px] wsms-font-mono wsms-text-foreground wsms-break-all wsms-line-clamp-2">
                {deleteDialog.item?.recipient}
              </span>
            </div>
            {deleteDialog.item?.message && (
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-line-clamp-2">
                {deleteDialog.item.message}
              </p>
            )}
          </div>
        </div>
      </DeleteConfirmDialog>

      {/* Bulk Delete Confirmation Dialog */}
      <DeleteConfirmDialog
        isOpen={showBulkDeleteConfirm}
        onClose={() => setShowBulkDeleteConfirm(false)}
        onConfirm={handleBulkDeleteConfirm}
        isSaving={bulkActionLoading === __('Delete Selected')}
        title={__('Delete Scheduled Messages')}
        description={__('Are you sure you want to delete the selected scheduled messages?')}
      >
        <div className="wsms-p-4 wsms-rounded-md wsms-bg-muted/50 wsms-border wsms-border-border">
          <p className="wsms-text-[13px] wsms-text-foreground">
            {__('%d message(s) will be permanently deleted.').replace('%d', table.selectedIds.length)}
          </p>
        </div>
      </DeleteConfirmDialog>

      {/* Edit Scheduled Message Dialog */}
      <Dialog open={editDialog.isOpen} onOpenChange={(open) => !open && editDialog.close()}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <CalendarClock className="wsms-h-4 wsms-w-4 wsms-text-primary" aria-hidden="true" />
              {__('Edit Scheduled Message')}
            </DialogTitle>
          </DialogHeader>
          <DialogBody>
            <div className="wsms-space-y-4">
              <div>
                <Label htmlFor="edit-scheduled-date">{__('Scheduled Date')}</Label>
                <Input
                  id="edit-scheduled-date"
                  type="datetime-local"
                  value={editDialog.formData.date || ''}
                  onChange={(e) => editDialog.updateField('date', e.target.value)}
                  className="wsms-mt-1.5"
                />
                <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mt-1.5">
                  {__("Site's time zone")}: {window.wpSmsSettings?.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone}
                </p>
              </div>
              <div>
                <Label htmlFor="edit-scheduled-sender">{__('Sender')}</Label>
                <Input
                  id="edit-scheduled-sender"
                  type="text"
                  value={editDialog.formData.sender || ''}
                  onChange={(e) => editDialog.updateField('sender', e.target.value)}
                  className="wsms-mt-1.5"
                />
              </div>
              <div>
                <Label htmlFor="edit-scheduled-message">{__('Message')}</Label>
                <Textarea
                  id="edit-scheduled-message"
                  value={editDialog.formData.message || ''}
                  onChange={(e) => editDialog.updateField('message', e.target.value)}
                  rows={4}
                  className="wsms-mt-1.5"
                />
              </div>
            </div>
          </DialogBody>
          <DialogFooter>
            <Button variant="outline" onClick={editDialog.close} disabled={editDialog.isSaving}>
              {__('Cancel')}
            </Button>
            <Button onClick={editDialog.save} disabled={editDialog.isSaving}>
              {editDialog.isSaving ? (
                <>
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" aria-hidden="true" />
                  {__('Saving...')}
                </>
              ) : (
                __('Save Changes')
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

// ============================================
// Repeating SMS Tab Component
// ============================================
function RepeatingSmsTab() {
  const { toast } = useToast()

  // Use useListPage for combined filter + table management
  const { filters, table } = useListPage({
    fetchFn: repeatingApi.getMessages,
    deleteFn: repeatingApi.deleteMessage,
    bulkActionFn: repeatingApi.bulkAction,
    initialFilters: { search: '', status: 'all' },
    messages: {
      deleteSuccess: __('Repeating message deleted successfully'),
      bulkSuccess: __('Action completed successfully'),
    },
  })

  // Delete confirmation dialog
  const deleteDialog = useFormDialog({
    saveFn: async (id) => {
      await repeatingApi.deleteMessage(id)
      table.removeItems([id])
    },
    successMessage: __('Repeating message deleted successfully'),
  })

  // Edit dialog
  const editDialog = useFormDialog({
    saveFn: async (id, data) => {
      const payload = {
        sender: data.sender,
        message: data.message,
      }
      if (data.repeat_forever) {
        payload.ends_at = null
      } else if (data.ends_at) {
        payload.ends_at = data.ends_at.replace('T', ' ') + ':00'
      }
      await repeatingApi.updateMessage(id, payload)
    },
    initialData: { sender: '', message: '', ends_at: '', repeat_forever: false },
    onSuccess: () => table.refresh(),
    successMessage: __('Repeating message updated successfully'),
  })

  // Handle edit click
  const handleEditClick = useCallback((row) => {
    const hasEndDate = row.ends_at && row.ends_at_date
    editDialog.open({
      id: row.id,
      sender: row.sender || '',
      message: row.message || '',
      ends_at: hasEndDate ? row.ends_at_date.replace(' ', 'T').slice(0, 16) : '',
      repeat_forever: !hasEndDate,
    })
  }, [editDialog])

  // Dialog states
  const [viewMessage, setViewMessage] = useState(null)
  const [bulkActionLoading, setBulkActionLoading] = useState(null)
  const [showBulkDeleteConfirm, setShowBulkDeleteConfirm] = useState(false)

  // Handle sort
  const handleSort = useCallback((columnKey, direction) => {
    table.fetch({
      page: 1,
      orderby: direction ? columnKey : undefined,
      order: direction || undefined,
    })
  }, [table])

  // Handle delete click
  const handleDeleteClick = useCallback((message) => {
    deleteDialog.open(message)
  }, [deleteDialog])

  // Handle delete confirm
  const handleDeleteConfirm = async () => {
    if (!deleteDialog.item) return
    try {
      await deleteDialog.save()
    } catch {
      // Error already handled
    }
  }

  // Handle bulk delete confirm
  const handleBulkDeleteConfirm = useCallback(async () => {
    setShowBulkDeleteConfirm(false)
    if (table.selectedIds.length === 0) return

    setBulkActionLoading(__('Delete Selected'))
    try {
      const result = await repeatingApi.bulkAction('delete', table.selectedIds)
      toast({
        title: __('%d message(s) deleted successfully').replace('%d', result.affected),
        variant: 'success',
      })
      table.clearSelection()
      table.fetch({ page: 1 })
    } catch (error) {
      toast({ title: error.message || __('Bulk action failed'), variant: 'destructive' })
    } finally {
      setBulkActionLoading(null)
    }
  }, [toast, table])

  // Handle export
  const handleExport = useCallback(async () => {
    const result = await repeatingApi.exportCsv({
      status: filters.debouncedFilters.status !== 'all' ? filters.debouncedFilters.status : undefined,
    })
    downloadCsv(result.data, result.filename)
    return { count: result.count }
  }, [filters.debouncedFilters])

  // Memoized row and bulk actions
  const rowActions = useMemo(
    () =>
      getRepeatingRowActions({
        onView: (row) => setViewMessage(row),
        onEdit: handleEditClick,
        onDelete: handleDeleteClick,
      }),
    [handleEditClick, handleDeleteClick]
  )

  const bulkActions = useMemo(
    () =>
      getRepeatingBulkActions({
        onDelete: () => setShowBulkDeleteConfirm(true),
      }),
    []
  )

  // Stats
  const stats = table.stats || { total: 0, active: 0, ended: 0 }

  // Loading skeleton
  if (!table.initialLoadDone) {
    return <PageLoadingSkeleton />
  }

  // Error state
  if (table.error) {
    return (
      <div className="wsms-space-y-6">
        <Card className="wsms-border-destructive">
          <CardContent className="wsms-py-8">
            <div className="wsms-flex wsms-flex-col wsms-items-center wsms-text-center">
              <AlertCircle className="wsms-h-12 wsms-w-12 wsms-text-destructive wsms-mb-4" />
              <h3 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-2">
                {__('Failed to load repeating messages')}
              </h3>
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-4">
                {table.error}
              </p>
              <Button onClick={() => table.fetch({ page: 1 })}>
                <RefreshCw className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                {__('Try Again')}
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  // Empty state
  const hasNoMessages =
    table.data.length === 0 &&
    !filters.filters.search &&
    filters.filters.status === 'all'

  if (hasNoMessages) {
    return (
      <div className="wsms-space-y-6 wsms-stagger-children">
        <Card className="wsms-border-dashed">
          <CardContent className="wsms-py-16">
            <div className="wsms-flex wsms-flex-col wsms-items-center wsms-text-center wsms-max-w-md wsms-mx-auto">
              <div className="wsms-flex wsms-h-16 wsms-w-16 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-primary/10 wsms-mb-6">
                <Repeat className="wsms-h-8 wsms-w-8 wsms-text-primary" strokeWidth={1.5} />
              </div>
              <h3 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-2">
                {__('No repeating messages')}
              </h3>
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-6">
                {__('Create recurring SMS messages that are sent automatically at regular intervals. Perfect for reminders, notifications, and scheduled updates.')}
              </p>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="wsms-space-y-6 wsms-stagger-children">
      {/* Stats Header Bar */}
      <div className="wsms-px-4 xl:wsms-px-5 wsms-py-4 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
        <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4 xl:wsms-flex xl:wsms-items-center xl:wsms-justify-between xl:wsms-gap-4">
          <div className="wsms-contents xl:wsms-flex xl:wsms-items-center xl:wsms-gap-8">
            {/* Total */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-primary/10">
                <Repeat className="wsms-h-5 wsms-w-5 wsms-text-primary" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">{stats.total}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Total')}</p>
              </div>
            </div>

            <div className="wsms-hidden xl:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* Active */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-success/10">
                <CheckCircle className="wsms-h-5 wsms-w-5 wsms-text-success" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-success">{stats.active}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Active')}</p>
              </div>
            </div>

            <div className="wsms-hidden xl:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* Ended */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-muted">
                <StopCircle className="wsms-h-5 wsms-w-5 wsms-text-muted-foreground" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-muted-foreground">{stats.ended}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Ended')}</p>
              </div>
            </div>
          </div>

          {/* Export Button */}
          <div className="wsms-col-span-2 xl:wsms-col-span-1 wsms-flex wsms-items-center wsms-justify-end wsms-gap-2 wsms-mt-2 xl:wsms-mt-0">
            <ExportButton
              onExport={handleExport}
              successMessage={__('Exported %d repeating messages successfully')}
            />
          </div>
        </div>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="wsms-p-3">
          <div className="wsms-flex wsms-flex-col wsms-gap-3 xl:wsms-flex-row xl:wsms-items-center xl:wsms-gap-3">
            {/* Search */}
            <div className="wsms-relative wsms-w-full xl:wsms-w-[220px] xl:wsms-shrink-0">
              <Search
                className="wsms-absolute wsms-left-2.5 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-pointer-events-none"
                aria-hidden="true"
              />
              <Input
                type="text"
                value={filters.filters.search}
                onChange={(e) => filters.setFilter('search', e.target.value)}
                placeholder={__('Search messages...')}
                className="wsms-pl-8 wsms-h-9"
                aria-label={__('Search repeating messages')}
              />
            </div>

            {/* Status Filter */}
            <Select
              value={filters.filters.status}
              onValueChange={(value) => filters.setFilter('status', value)}
            >
              <SelectTrigger className="wsms-h-9 wsms-w-full xl:wsms-w-[120px] wsms-text-[12px]" aria-label={__('Filter by status')}>
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{__('All Status')}</SelectItem>
                <SelectItem value="active">{__('Active')}</SelectItem>
                <SelectItem value="ended">{__('Ended')}</SelectItem>
              </SelectContent>
            </Select>

            {/* Actions */}
            <div className="wsms-flex wsms-items-center wsms-gap-2 xl:wsms-ml-auto">
              {/* Clear Filters */}
              {(filters.filters.search || filters.filters.status !== 'all') && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={() => filters.resetFilters()}
                  className="wsms-h-9 wsms-px-2.5 wsms-text-muted-foreground hover:wsms-text-foreground"
                  aria-label={__('Clear all filters')}
                >
                  <X className="wsms-h-4 wsms-w-4" aria-hidden="true" />
                </Button>
              )}
              {/* Refresh */}
              <Button
                variant="outline"
                size="sm"
                onClick={() => table.fetch({ page: 1 })}
                className="wsms-h-9 wsms-px-2.5"
                aria-label={__('Refresh messages')}
              >
                <RefreshCw
                  className={cn('wsms-h-4 wsms-w-4', table.isLoading && 'wsms-animate-spin')}
                  aria-hidden="true"
                />
              </Button>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Data Table */}
      <Card>
        <CardContent className="wsms-p-0">
          <DataTable
            columns={repeatingMessagesColumns}
            data={table.data}
            loading={table.isLoading}
            pagination={{
              total: table.pagination.total,
              totalPages: table.pagination.total_pages,
              page: table.pagination.current_page,
              perPage: table.pagination.per_page,
              onPageChange: table.handlePageChange,
            }}
            selection={{
              selected: table.selectedIds,
              onSelect: table.toggleSelection,
              onSelectAll: (checked) => {
                if (checked) {
                  table.toggleSelectAll()
                } else {
                  table.clearSelection()
                }
              },
            }}
            onSort={handleSort}
            rowActions={rowActions}
            bulkActions={bulkActions}
            bulkActionLoading={bulkActionLoading}
            emptyMessage={__('No repeating messages match your filters')}
            emptyIcon={Repeat}
          />
        </CardContent>
      </Card>

      {/* View Message Dialog */}
      <Dialog open={!!viewMessage} onOpenChange={() => setViewMessage(null)}>
        <DialogContent size="lg">
          <DialogHeader>
            <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Repeat className="wsms-h-4 wsms-w-4 wsms-text-primary" aria-hidden="true" />
              {__('Repeating Message Details')}
            </DialogTitle>
            <DialogDescription>
              {viewMessage && repeatingApi.formatInterval(viewMessage.interval, viewMessage.interval_unit)}
            </DialogDescription>
          </DialogHeader>
          <DialogBody>
            {viewMessage && (
              <div className="wsms-space-y-4">
                {/* Status and Info Row */}
                <div className="wsms-flex wsms-items-center wsms-gap-4 wsms-p-4 wsms-rounded-lg wsms-bg-muted/30">
                  <div className="wsms-flex-1">
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">{__('Status')}</p>
                    <StatusBadge variant={viewMessage.status === 'active' ? 'success' : 'default'}>
                      {viewMessage.status === 'active' ? __('Active') : __('Ended')}
                    </StatusBadge>
                  </div>
                  <div className="wsms-w-px wsms-h-8 wsms-bg-border" aria-hidden="true" />
                  <div className="wsms-flex-1">
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">{__('Sent')}</p>
                    <p className="wsms-text-[13px] wsms-font-medium">
                      {viewMessage.occurrences_sent || 0}
                      {viewMessage.max_occurrences ? ` / ${viewMessage.max_occurrences}` : ''}
                    </p>
                  </div>
                  {viewMessage.next_occurrence && (
                    <>
                      <div className="wsms-w-px wsms-h-8 wsms-bg-border" aria-hidden="true" />
                      <div className="wsms-flex-1">
                        <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">{__('Next')}</p>
                        <p className="wsms-text-[13px] wsms-font-medium">
                          {formatDate(viewMessage.next_occurrence, { hour: '2-digit', minute: '2-digit' })}
                        </p>
                      </div>
                    </>
                  )}
                </div>

                {/* Recipient(s) */}
                <div>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">{__('Recipient(s)')}</p>
                  <p className="wsms-text-[13px] wsms-break-all wsms-font-mono wsms-p-2 wsms-rounded wsms-bg-muted/30">
                    {viewMessage.recipient}
                  </p>
                </div>

                {/* Message */}
                <div>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">{__('Message')}</p>
                  <div className="wsms-p-4 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
                    <p className="wsms-text-[13px] wsms-whitespace-pre-wrap">{viewMessage.message}</p>
                  </div>
                </div>

                {/* Media (MMS) */}
                {viewMessage.media && Array.isArray(viewMessage.media) && viewMessage.media.length > 0 && (
                  <div>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1 wsms-flex wsms-items-center wsms-gap-1">
                      <Image className="wsms-h-3 wsms-w-3" aria-hidden="true" />
                      {__('Media')}
                    </p>
                    <div className="wsms-flex wsms-flex-wrap wsms-gap-2">
                      {viewMessage.media.map((url, idx) => (
                        <a
                          key={idx}
                          href={url}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="wsms-block wsms-rounded-lg wsms-overflow-hidden wsms-border wsms-border-border hover:wsms-border-primary wsms-transition-colors"
                        >
                          <img
                            src={url}
                            alt={`Media ${idx + 1}`}
                            className="wsms-max-w-[150px] wsms-max-h-[100px] wsms-object-cover"
                          />
                        </a>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )}
          </DialogBody>
          <DialogFooter>
            <Button variant="outline" onClick={() => setViewMessage(null)}>
              {__('Close')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation Dialog */}
      <DeleteConfirmDialog
        isOpen={deleteDialog.isOpen}
        onClose={deleteDialog.close}
        onConfirm={handleDeleteConfirm}
        isSaving={deleteDialog.isSaving}
        title={__('Delete Repeating Message')}
        description={__('Are you sure you want to delete this repeating message? This will stop all future occurrences.')}
      >
        <div className="wsms-p-4 wsms-rounded-md wsms-bg-muted/50 wsms-border wsms-border-border wsms-overflow-hidden">
          <div className="wsms-space-y-2">
            <div className="wsms-flex wsms-items-start wsms-gap-2">
              <span className="wsms-text-[12px] wsms-text-muted-foreground wsms-shrink-0">{__('To')}:</span>
              <span className="wsms-text-[13px] wsms-font-mono wsms-text-foreground wsms-break-all wsms-line-clamp-2">
                {deleteDialog.item?.recipient}
              </span>
            </div>
            {deleteDialog.item?.message && (
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-line-clamp-2">
                {deleteDialog.item.message}
              </p>
            )}
          </div>
        </div>
      </DeleteConfirmDialog>

      {/* Bulk Delete Confirmation Dialog */}
      <DeleteConfirmDialog
        isOpen={showBulkDeleteConfirm}
        onClose={() => setShowBulkDeleteConfirm(false)}
        onConfirm={handleBulkDeleteConfirm}
        isSaving={bulkActionLoading === __('Delete Selected')}
        title={__('Delete Repeating Messages')}
        description={__('Are you sure you want to delete the selected repeating messages?')}
      >
        <div className="wsms-p-4 wsms-rounded-md wsms-bg-muted/50 wsms-border wsms-border-border">
          <p className="wsms-text-[13px] wsms-text-foreground">
            {__('%d message(s) will be permanently deleted.').replace('%d', table.selectedIds.length)}
          </p>
        </div>
      </DeleteConfirmDialog>

      {/* Edit Repeating Message Dialog */}
      <Dialog open={editDialog.isOpen} onOpenChange={(open) => !open && editDialog.close()}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Repeat className="wsms-h-4 wsms-w-4 wsms-text-primary" aria-hidden="true" />
              {__('Edit Repeating Message')}
            </DialogTitle>
          </DialogHeader>
          <DialogBody>
            <div className="wsms-space-y-4">
              <div>
                <Label htmlFor="edit-repeating-sender">{__('Sender')}</Label>
                <Input
                  id="edit-repeating-sender"
                  type="text"
                  value={editDialog.formData.sender || ''}
                  onChange={(e) => editDialog.updateField('sender', e.target.value)}
                  className="wsms-mt-1.5"
                />
              </div>
              <div>
                <Label htmlFor="edit-repeating-message">{__('Message')}</Label>
                <Textarea
                  id="edit-repeating-message"
                  value={editDialog.formData.message || ''}
                  onChange={(e) => editDialog.updateField('message', e.target.value)}
                  rows={4}
                  className="wsms-mt-1.5"
                />
              </div>
              <div>
                <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-mb-1.5">
                  <input
                    type="checkbox"
                    id="edit-repeating-forever"
                    checked={editDialog.formData.repeat_forever || false}
                    onChange={(e) => {
                      editDialog.updateField('repeat_forever', e.target.checked)
                      if (e.target.checked) {
                        editDialog.updateField('ends_at', '')
                      }
                    }}
                    className="wsms-rounded wsms-border-border"
                  />
                  <Label htmlFor="edit-repeating-forever" className="wsms-mb-0">{__('Repeat Forever')}</Label>
                </div>
                {!editDialog.formData.repeat_forever && (
                  <div>
                    <Label htmlFor="edit-repeating-ends-at">{__('End on')}</Label>
                    <Input
                      id="edit-repeating-ends-at"
                      type="datetime-local"
                      value={editDialog.formData.ends_at || ''}
                      onChange={(e) => editDialog.updateField('ends_at', e.target.value)}
                      className="wsms-mt-1.5"
                    />
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mt-1.5">
                      {__("Site's time zone")}: {window.wpSmsSettings?.timezone || Intl.DateTimeFormat().resolvedOptions().timeZone}
                    </p>
                  </div>
                )}
              </div>
            </div>
          </DialogBody>
          <DialogFooter>
            <Button variant="outline" onClick={editDialog.close} disabled={editDialog.isSaving}>
              {__('Cancel')}
            </Button>
            <Button onClick={editDialog.save} disabled={editDialog.isSaving}>
              {editDialog.isSaving ? (
                <>
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" aria-hidden="true" />
                  {__('Saving...')}
                </>
              ) : (
                __('Save Changes')
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}

// ============================================
// Main Scheduled Page Component
// ============================================
export default function Scheduled() {
  const { hasProAddon } = getWpSettings()

  // State for active tab
  const [activeTab, setActiveTab] = useState('scheduled')

  // If Pro addon is not active, show placeholder
  if (!hasProAddon) {
    return (
      <div className="wsms-space-y-6">
        <Card className="wsms-border-dashed">
          <CardContent className="wsms-py-16">
            <div className="wsms-flex wsms-flex-col wsms-items-center wsms-text-center wsms-max-w-md wsms-mx-auto">
              <div className="wsms-flex wsms-h-16 wsms-w-16 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-primary/10 wsms-mb-6">
                <CalendarClock className="wsms-h-8 wsms-w-8 wsms-text-primary" strokeWidth={1.5} />
              </div>
              <h3 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-2">
                {__('WP SMS Pro Required')}
              </h3>
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-6">
                {__('Schedule SMS messages for later delivery or create recurring messages with WP SMS Pro. Perfect for reminders, notifications, and automated campaigns.')}
              </p>
              <Button variant="outline" asChild>
                <a href="https://wp-sms-pro.com/buy/" target="_blank" rel="noopener noreferrer">
                  {__('Learn More')}
                  <ExternalLink className="wsms-ml-2 wsms-h-4 wsms-w-4" />
                </a>
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="wsms-space-y-6">
      <Tabs value={activeTab} onValueChange={setActiveTab} className="wsms-w-full">
        <TabsList className="wsms-grid wsms-w-full wsms-grid-cols-2 wsms-max-w-[400px]">
          <TabsTrigger value="scheduled" className="wsms-flex wsms-items-center wsms-gap-2">
            <CalendarClock className="wsms-h-4 wsms-w-4" />
            {__('Scheduled SMS')}
          </TabsTrigger>
          <TabsTrigger value="repeating" className="wsms-flex wsms-items-center wsms-gap-2">
            <Repeat className="wsms-h-4 wsms-w-4" />
            {__('Repeating SMS')}
          </TabsTrigger>
        </TabsList>

        {/* Keep both tabs mounted to preserve state when switching */}
        <TabsContent value="scheduled" className="wsms-mt-6" forceMount>
          <div className={activeTab !== 'scheduled' ? 'wsms-hidden' : ''}>
            <ScheduledSmsTab />
          </div>
        </TabsContent>

        <TabsContent value="repeating" className="wsms-mt-6" forceMount>
          <div className={activeTab !== 'repeating' ? 'wsms-hidden' : ''}>
            <RepeatingSmsTab />
          </div>
        </TabsContent>
      </Tabs>
    </div>
  )
}
