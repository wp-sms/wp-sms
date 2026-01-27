import React, { useState, useCallback, useMemo, useEffect, useRef } from 'react'
import {
  Inbox,
  RefreshCw,
  Send,
  Search,
  CheckCircle,
  XCircle,
  Loader2,
  AlertCircle,
  MessageSquare,
  TrendingUp,
  TrendingDown,
  X,
  Image,
  Trash2,
} from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { DataTable } from '@/components/ui/data-table'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { ExportButton } from '@/components/shared/ExportButton'
import { DateRangePicker } from '@/components/shared/DateRangePicker'
import { DeleteConfirmDialog } from '@/components/shared/DeleteConfirmDialog'
import { Tip } from '@/components/ui/ux-helpers'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogBody,
  DialogFooter,
} from '@/components/ui/dialog'
import { outboxApi } from '@/api/outboxApi'
import { smsApi } from '@/api/smsApi'
import { cn, formatDate, __, downloadCsv } from '@/lib/utils'
import { useListPage } from '@/hooks/useListPage'
import { useFormDialog } from '@/hooks/useFormDialog'
import { useToast } from '@/components/ui/toaster'
import { useSettings } from '@/context/SettingsContext'
import { outboxColumns, getOutboxRowActions, getOutboxBulkActions } from '@/lib/tableColumns'
import { PageLoadingSkeleton } from '@/components/ui/skeleton'

export default function Outbox() {
  const { toast } = useToast()
  const { currentPage, setCurrentPage } = useSettings()

  // Use useListPage for combined filter + table management
  const { filters, table, handleDelete, handleBulkAction } = useListPage({
    fetchFn: outboxApi.getMessages,
    deleteFn: outboxApi.deleteMessage,
    bulkActionFn: outboxApi.bulkAction,
    initialFilters: { search: '', status: 'all', date_from: '', date_to: '' },
    messages: {
      deleteSuccess: __('Message deleted successfully'),
      bulkSuccess: __('Action completed successfully'),
    },
  })

  // Delete confirmation dialog using useFormDialog
  const deleteDialog = useFormDialog({
    saveFn: async (id) => {
      await outboxApi.deleteMessage(id)
      table.removeItems([id])
    },
    successMessage: __('Message deleted successfully'),
  })

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

  // Track refresh state for showing loading indicator after SMS sent
  const needsRefresh = useRef(false)
  const isRefreshingAfterSms = useRef(false)

  // Refresh data when SMS is sent (listen for custom event from SendSms page)
  useEffect(() => {
    const handleSmsSent = () => {
      // If we're on outbox, refresh immediately; otherwise flag for later
      if (currentPage === 'outbox') {
        isRefreshingAfterSms.current = true
        table.refresh()
      } else {
        needsRefresh.current = true
      }
    }
    window.addEventListener('wpsms:sms-sent', handleSmsSent)
    return () => window.removeEventListener('wpsms:sms-sent', handleSmsSent)
  }, [currentPage, table])

  // Refresh when returning to outbox if SMS was sent while away
  useEffect(() => {
    if (currentPage === 'outbox' && needsRefresh.current && table.initialLoadDone) {
      isRefreshingAfterSms.current = true
      table.refresh()
      needsRefresh.current = false
    }
  }, [currentPage, table.initialLoadDone]) // eslint-disable-line react-hooks/exhaustive-deps

  // Reset the refresh flag when loading completes
  useEffect(() => {
    if (!table.isLoading && isRefreshingAfterSms.current) {
      isRefreshingAfterSms.current = false
    }
  }, [table.isLoading])

  // Dialog states for view and quick reply
  const [viewMessage, setViewMessage] = useState(null)
  const [quickReplyTo, setQuickReplyTo] = useState(null)
  const [quickReplyMessage, setQuickReplyMessage] = useState('')
  const [isSendingReply, setIsSendingReply] = useState(false)
  const [actionLoading, setActionLoading] = useState(null)
  const [bulkActionLoading, setBulkActionLoading] = useState(null)
  const [showBulkDeleteConfirm, setShowBulkDeleteConfirm] = useState(false)

  // Sorting state
  const [sortConfig, setSortConfig] = useState({ key: null, direction: null })

  // Handle sort
  const handleSort = useCallback((columnKey, direction) => {
    setSortConfig({ key: direction ? columnKey : null, direction })
    table.fetch({
      page: 1,
      orderby: direction ? columnKey : undefined,
      order: direction || undefined,
    })
  }, [table])

  // Custom handler for resend (not part of useListPage)
  const handleResend = useCallback(
    async (id) => {
      setActionLoading(id)
      try {
        await outboxApi.resendMessage(id)
        toast({ title: __('Message resent successfully'), variant: 'success' })
        table.refresh()
      } catch (error) {
        toast({ title: error.message || __('Failed to resend message'), variant: 'destructive' })
      } finally {
        setActionLoading(null)
      }
    },
    [toast, table]
  )

  // Custom bulk handler to show affected count in message
  const handleOutboxBulkAction = useCallback(
    async (action, label) => {
      if (table.selectedIds.length === 0) return

      setBulkActionLoading(label)
      try {
        const result = await outboxApi.bulkAction(action, table.selectedIds)
        toast({
          title: __(`${result.affected} message(s) ${action === 'delete' ? 'deleted' : 'resent'} successfully`),
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

  const handleExport = useCallback(async () => {
    const result = await outboxApi.exportCsv({
      status: filters.debouncedFilters.status !== 'all' ? filters.debouncedFilters.status : undefined,
      date_from: filters.debouncedFilters.date_from || undefined,
      date_to: filters.debouncedFilters.date_to || undefined,
    })
    downloadCsv(result.data, result.filename)
    return { count: result.count }
  }, [filters.debouncedFilters])

  const handleQuickReply = useCallback(async () => {
    if (!quickReplyTo || !quickReplyMessage.trim()) return

    setIsSendingReply(true)
    try {
      await smsApi.send({
        message: quickReplyMessage,
        recipients: { groups: [], roles: [], numbers: [quickReplyTo] },
      })
      toast({ title: __('Reply sent successfully'), variant: 'success' })
      setQuickReplyTo(null)
      setQuickReplyMessage('')
      table.refresh()
    } catch (error) {
      toast({ title: error.message || __('Failed to send reply'), variant: 'destructive' })
    } finally {
      setIsSendingReply(false)
    }
  }, [quickReplyTo, quickReplyMessage, toast, table])

  // Memoized row and bulk actions
  const rowActions = useMemo(
    () =>
      getOutboxRowActions({
        onView: (row) => setViewMessage(row),
        onQuickReply: (row) => {
          const recipient = row.recipient?.split(',')[0]?.trim() || row.recipient
          setQuickReplyTo(recipient)
          setQuickReplyMessage('')
        },
        onResend: (row) => handleResend(row.id),
        onDelete: handleDeleteClick,
      }),
    [handleResend, handleDeleteClick]
  )

  // Handle bulk delete with confirmation
  const handleBulkDeleteConfirm = useCallback(async () => {
    setShowBulkDeleteConfirm(false)
    await handleOutboxBulkAction('delete', __('Delete Selected'))
  }, [handleOutboxBulkAction])

  const bulkActions = useMemo(
    () =>
      getOutboxBulkActions({
        onDelete: () => setShowBulkDeleteConfirm(true),
        onResend: () => handleOutboxBulkAction('resend', __('Resend Selected')),
      }),
    [handleOutboxBulkAction]
  )

  // Calculate success rate based on completed messages (sent + failed), not total
  const stats = table.stats || { total: 0, success: 0, failed: 0 }
  const completedMessages = stats.success + stats.failed
  const successRate = completedMessages > 0 ? Math.round((stats.success / completedMessages) * 100) : 0
  // Consider it a good rate if there are no failures or rate is high
  const isGoodRate = stats.failed === 0 || successRate >= 90

  // Loading skeleton - show until initial API fetch completes or when refreshing after SMS sent
  if (!table.initialLoadDone || (isRefreshingAfterSms.current && table.isLoading)) {
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
                Failed to load messages
              </h3>
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-4">
                {table.error}
              </p>
              <Button onClick={() => table.fetch({ page: 1 })}>
                <RefreshCw className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                Try Again
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  // Empty state - only show when truly no messages (not when filters return no results)
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
                <Inbox className="wsms-h-8 wsms-w-8 wsms-text-primary" strokeWidth={1.5} />
              </div>
              <h3 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-2">
                No messages yet
              </h3>
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-6">
                When you send SMS messages, they will appear here. You can track delivery status,
                resend failed messages, and export your history.
              </p>
              <Tip variant="info">
                Go to{' '}
                <button
                  onClick={() => setCurrentPage('send-sms')}
                  className="wsms-underline wsms-font-semibold hover:wsms-text-primary wsms-transition-colors"
                >
                  {__('Send SMS')}
                </button>{' '}
                to send your first message!
              </Tip>
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
        {/* Mobile/Tablet: Grid layout, Desktop: Flex layout */}
        <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4 xl:wsms-flex xl:wsms-items-center xl:wsms-justify-between xl:wsms-gap-4">
          <div className="wsms-contents xl:wsms-flex xl:wsms-items-center xl:wsms-gap-8">
            {/* Total */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-primary/10">
                <Inbox className="wsms-h-5 wsms-w-5 wsms-text-primary" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">{stats.total}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-hidden xl:wsms-block">Total Messages</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground xl:wsms-hidden">Total</p>
              </div>
            </div>

            <div className="wsms-hidden xl:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* Sent */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-success/10">
                <CheckCircle className="wsms-h-5 wsms-w-5 wsms-text-success" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-success">{stats.success}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">Sent</p>
              </div>
            </div>

            <div className="wsms-hidden xl:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* Failed */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-destructive/10">
                <XCircle className="wsms-h-5 wsms-w-5 wsms-text-destructive" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-destructive">{stats.failed}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">Failed</p>
              </div>
            </div>

            <div className="wsms-hidden xl:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* Success Rate */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div
                className={cn(
                  'wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg',
                  isGoodRate
                    ? 'wsms-bg-success/10'
                    : successRate >= 70
                      ? 'wsms-bg-amber-100 dark:wsms-bg-amber-900/30'
                      : 'wsms-bg-destructive/10'
                )}
              >
                {isGoodRate || successRate >= 70 ? (
                  <TrendingUp
                    className={cn(
                      'wsms-h-5 wsms-w-5',
                      isGoodRate
                        ? 'wsms-text-success'
                        : 'wsms-text-amber-600 dark:wsms-text-amber-400'
                    )}
                    aria-hidden="true"
                  />
                ) : (
                  <TrendingDown className="wsms-h-5 wsms-w-5 wsms-text-destructive" aria-hidden="true" />
                )}
              </div>
              <div>
                <p
                  className={cn(
                    'wsms-text-xl wsms-font-bold',
                    isGoodRate
                      ? 'wsms-text-success'
                      : successRate >= 70
                        ? 'wsms-text-amber-600 dark:wsms-text-amber-400'
                        : 'wsms-text-destructive'
                  )}
                >
                  {successRate}%
                </p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-hidden xl:wsms-block">Success Rate</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground xl:wsms-hidden">Rate</p>
              </div>
            </div>
          </div>

          {/* Export Button */}
          <div className="wsms-col-span-2 xl:wsms-col-span-1 wsms-flex wsms-items-center wsms-justify-end wsms-gap-2 wsms-mt-2 xl:wsms-mt-0">
            <ExportButton
              onExport={handleExport}
              successMessage={__('Exported %d messages successfully')}
            />
          </div>
        </div>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="wsms-p-3">
          {/* Mobile/Tablet: Stacked layout, Desktop: Single row */}
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
                placeholder="Search messages..."
                className="wsms-pl-8 wsms-h-9"
                aria-label="Search messages"
              />
            </div>

            {/* Status Filter */}
            <Select
              value={filters.filters.status}
              onValueChange={(value) => filters.setFilter('status', value)}
            >
              <SelectTrigger className="wsms-h-9 wsms-w-full xl:wsms-w-[100px] wsms-text-[12px]" aria-label="Filter by status">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Status</SelectItem>
                <SelectItem value="success">Sent</SelectItem>
                <SelectItem value="failed">Failed</SelectItem>
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
                  aria-label="Clear all filters"
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
                aria-label="Refresh messages"
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
            columns={outboxColumns}
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
            emptyMessage="No messages match your filters"
            emptyIcon={Inbox}
          />
        </CardContent>
      </Card>

      {/* View Message Dialog */}
      <Dialog open={!!viewMessage} onOpenChange={() => setViewMessage(null)}>
        <DialogContent size="lg">
          <DialogHeader>
            <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <MessageSquare className="wsms-h-4 wsms-w-4 wsms-text-primary" aria-hidden="true" />
              Message Details
            </DialogTitle>
            <DialogDescription>
              Sent on {viewMessage && formatDate(viewMessage.date, { hour: '2-digit', minute: '2-digit' })}
            </DialogDescription>
          </DialogHeader>
          <DialogBody>
            {viewMessage && (
              <div className="wsms-space-y-4">
                {/* Status and Recipients Row */}
                <div className="wsms-flex wsms-items-center wsms-gap-4 wsms-p-4 wsms-rounded-lg wsms-bg-muted/30">
                  <div className="wsms-flex-1">
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">Status</p>
                    <StatusBadge variant={viewMessage.status === 'success' ? 'success' : 'failed'}>
                      {viewMessage.status === 'success' ? __('Sent') : __('Failed')}
                    </StatusBadge>
                  </div>
                  <div className="wsms-w-px wsms-h-8 wsms-bg-border" aria-hidden="true" />
                  <div className="wsms-flex-1">
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">Recipients</p>
                    <p className="wsms-text-[13px] wsms-font-medium">{viewMessage.recipient_count}</p>
                  </div>
                  {viewMessage.sender && (
                    <>
                      <div className="wsms-w-px wsms-h-8 wsms-bg-border" aria-hidden="true" />
                      <div className="wsms-flex-1">
                        <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">Sender</p>
                        <p className="wsms-text-[13px] wsms-font-medium">{viewMessage.sender}</p>
                      </div>
                    </>
                  )}
                </div>

                {/* Recipient(s) */}
                <div>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">Recipient(s)</p>
                  <p className="wsms-text-[13px] wsms-break-all wsms-font-mono wsms-p-2 wsms-rounded wsms-bg-muted/30">
                    {viewMessage.recipient}
                  </p>
                </div>

                {/* Message */}
                <div>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">Message</p>
                  <div className="wsms-p-4 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
                    <p className="wsms-text-[13px] wsms-whitespace-pre-wrap">{viewMessage.message}</p>
                  </div>
                </div>

                {/* Media (MMS) */}
                {viewMessage.media && Array.isArray(viewMessage.media) && viewMessage.media.length > 0 && (
                  <div>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1 wsms-flex wsms-items-center wsms-gap-1">
                      <Image className="wsms-h-3 wsms-w-3" aria-hidden="true" />
                      Media
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

                {/* Gateway Response */}
                {viewMessage.response && (
                  <div>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">
                      Gateway Response
                    </p>
                    <pre className="wsms-text-[11px] wsms-text-muted-foreground wsms-font-mono wsms-p-3 wsms-rounded-md wsms-bg-muted/50 wsms-border wsms-border-border wsms-overflow-x-auto wsms-whitespace-pre-wrap wsms-break-words wsms-max-h-[200px] wsms-overflow-y-auto">
                      {viewMessage.response}
                    </pre>
                  </div>
                )}
              </div>
            )}
          </DialogBody>
          <DialogFooter>
            <Button variant="outline" onClick={() => setViewMessage(null)}>
              Close
            </Button>
            <Button onClick={() => handleResend(viewMessage?.id)}>
              <Send className="wsms-h-4 wsms-w-4 wsms-mr-2" aria-hidden="true" />
              Resend
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Quick Reply Dialog */}
      <Dialog open={!!quickReplyTo} onOpenChange={() => setQuickReplyTo(null)}>
        <DialogContent size="sm">
          <DialogHeader>
            <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <MessageSquare className="wsms-h-4 wsms-w-4 wsms-text-primary" aria-hidden="true" />
              Quick Reply
            </DialogTitle>
            <DialogDescription>Send a quick reply to this recipient</DialogDescription>
          </DialogHeader>
          <DialogBody>
            <div className="wsms-space-y-4">
              <div className="wsms-space-y-2">
                <label htmlFor="quick-reply-to" className="wsms-text-[12px] wsms-font-medium">
                  To
                </label>
                <Input
                  id="quick-reply-to"
                  value={quickReplyTo || ''}
                  readOnly
                  className="wsms-bg-muted/50 wsms-font-mono"
                />
              </div>
              <div className="wsms-space-y-2">
                <label htmlFor="quick-reply-message" className="wsms-text-[12px] wsms-font-medium">
                  Message
                </label>
                <textarea
                  id="quick-reply-message"
                  value={quickReplyMessage}
                  onChange={(e) => setQuickReplyMessage(e.target.value)}
                  placeholder="Type your reply message..."
                  rows={4}
                  className="wsms-flex wsms-w-full wsms-rounded-md wsms-border wsms-border-input wsms-bg-background wsms-px-3 wsms-py-2 wsms-text-sm wsms-ring-offset-background placeholder:wsms-text-muted-foreground focus-visible:wsms-outline-none focus-visible:wsms-ring-2 focus-visible:wsms-ring-ring focus-visible:wsms-ring-offset-2"
                />
              </div>
            </div>
          </DialogBody>
          <DialogFooter>
            <Button variant="outline" onClick={() => setQuickReplyTo(null)}>
              Cancel
            </Button>
            <Button onClick={handleQuickReply} disabled={isSendingReply || !quickReplyMessage.trim()}>
              {isSendingReply ? (
                <>
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" aria-hidden="true" />
                  Sending...
                </>
              ) : (
                <>
                  <Send className="wsms-h-4 wsms-w-4 wsms-mr-2" aria-hidden="true" />
                  Send Reply
                </>
              )}
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
        title={__('Delete Message')}
        description={__('Are you sure you want to delete this message?')}
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
        title={__('Delete Messages')}
        description={__('Are you sure you want to delete the selected messages?')}
      >
        <div className="wsms-p-4 wsms-rounded-md wsms-bg-muted/50 wsms-border wsms-border-border">
          <p className="wsms-text-[13px] wsms-text-foreground">
            {__('%d message(s) will be permanently deleted.').replace('%d', table.selectedIds.length)}
          </p>
        </div>
      </DeleteConfirmDialog>
    </div>
  )
}
