import React, { useState, useCallback, useMemo } from 'react'
import {
  Inbox,
  RefreshCw,
  Trash2,
  Send,
  Search,
  Calendar,
  Eye,
  CheckCircle,
  XCircle,
  Loader2,
  AlertCircle,
  Download,
  MessageSquare,
  TrendingUp,
  TrendingDown,
} from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { DataTable } from '@/components/ui/data-table'
import { StatusBadge } from '@/components/shared/StatusBadge'
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
import { cn, formatDate } from '@/lib/utils'
import { useDataTable } from '@/hooks/useDataTable'
import { useNotificationToast } from '@/hooks/useNotificationToast'
import { useFilters } from '@/hooks/useFilters'
import { outboxColumns, getOutboxRowActions, getOutboxBulkActions } from '@/lib/tableColumns'

export default function Outbox() {
  // Use custom hooks for state management
  const notification = useNotificationToast()

  const filters = useFilters(
    { search: '', status: 'all', date_from: '', date_to: '' },
    { debounceMs: 500 }
  )

  // Fetch function for the data table
  const fetchMessages = useCallback(
    async (params) => {
      const result = await outboxApi.getMessages({
        ...params,
        search: filters.debouncedFilters.search || undefined,
        status: filters.debouncedFilters.status !== 'all' ? filters.debouncedFilters.status : undefined,
        date_from: filters.debouncedFilters.date_from || undefined,
        date_to: filters.debouncedFilters.date_to || undefined,
      })
      return result
    },
    [filters.debouncedFilters]
  )

  const table = useDataTable({
    fetchFn: fetchMessages,
    initialPerPage: 20,
  })

  // Re-fetch when filters change
  React.useEffect(() => {
    if (table.initialLoadDone) {
      table.fetch({ page: 1 })
    }
  }, [filters.debouncedFilters]) // eslint-disable-line react-hooks/exhaustive-deps

  // Dialog states
  const [viewMessage, setViewMessage] = useState(null)
  const [quickReplyTo, setQuickReplyTo] = useState(null)
  const [quickReplyMessage, setQuickReplyMessage] = useState('')
  const [isSendingReply, setIsSendingReply] = useState(false)
  const [actionLoading, setActionLoading] = useState(null)
  const [isExporting, setIsExporting] = useState(false)

  // Handlers
  const handleDelete = useCallback(
    async (id) => {
      setActionLoading(id)
      try {
        await outboxApi.deleteMessage(id)
        notification.showSuccess('Message deleted successfully')
        table.refresh()
      } catch (error) {
        notification.showFromError(error)
      } finally {
        setActionLoading(null)
      }
    },
    [notification, table]
  )

  const handleResend = useCallback(
    async (id) => {
      setActionLoading(id)
      try {
        await outboxApi.resendMessage(id)
        notification.showSuccess('Message resent successfully')
        table.refresh()
      } catch (error) {
        notification.showFromError(error)
      } finally {
        setActionLoading(null)
      }
    },
    [notification, table]
  )

  const handleBulkAction = useCallback(
    async (action) => {
      if (table.selectedIds.length === 0) return

      try {
        const result = await outboxApi.bulkAction(action, table.selectedIds)
        notification.showSuccess(
          `${result.affected} message(s) ${action === 'delete' ? 'deleted' : 'resent'} successfully`
        )
        table.clearSelection()
        table.fetch({ page: 1 })
      } catch (error) {
        notification.showFromError(error)
      }
    },
    [notification, table]
  )

  const handleExport = useCallback(async () => {
    setIsExporting(true)
    try {
      const result = await outboxApi.exportCsv({
        status: filters.debouncedFilters.status !== 'all' ? filters.debouncedFilters.status : undefined,
        date_from: filters.debouncedFilters.date_from || undefined,
        date_to: filters.debouncedFilters.date_to || undefined,
      })
      outboxApi.downloadCsv(result.data, result.filename)
      notification.showSuccess(`Exported ${result.count} messages successfully`)
    } catch (error) {
      notification.showFromError(error)
    } finally {
      setIsExporting(false)
    }
  }, [notification, filters.debouncedFilters])

  const handleQuickReply = useCallback(async () => {
    if (!quickReplyTo || !quickReplyMessage.trim()) return

    setIsSendingReply(true)
    try {
      await smsApi.send({
        message: quickReplyMessage,
        recipients: { groups: [], roles: [], numbers: [quickReplyTo] },
      })
      notification.showSuccess('Reply sent successfully')
      setQuickReplyTo(null)
      setQuickReplyMessage('')
      table.refresh()
    } catch (error) {
      notification.showFromError(error)
    } finally {
      setIsSendingReply(false)
    }
  }, [quickReplyTo, quickReplyMessage, notification, table])

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
        onDelete: (row) => handleDelete(row.id),
      }),
    [handleResend, handleDelete]
  )

  const bulkActions = useMemo(
    () =>
      getOutboxBulkActions({
        onDelete: () => handleBulkAction('delete'),
        onResend: () => handleBulkAction('resend'),
      }),
    [handleBulkAction]
  )

  // Calculate success rate
  const stats = table.stats || { total: 0, success: 0, failed: 0 }
  const successRate = stats.total > 0 ? Math.round((stats.success / stats.total) * 100) : 0

  // Loading skeleton
  if (!table.initialLoadDone) {
    return (
      <div className="wsms-space-y-6">
        <div className="wsms-h-24 wsms-rounded-lg wsms-bg-muted/30 wsms-animate-pulse" />
        <div className="wsms-h-16 wsms-rounded-lg wsms-bg-muted/30 wsms-animate-pulse" />
        <div className="wsms-h-64 wsms-rounded-lg wsms-bg-muted/30 wsms-animate-pulse" />
      </div>
    )
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

  // Empty state
  const hasNoMessages =
    table.data.length === 0 && !filters.filters.search && filters.filters.status === 'all'

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
                Go to <strong>Send SMS</strong> to send your first message!
              </Tip>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  return (
    <div className="wsms-space-y-6 wsms-stagger-children">
      {/* Notification */}
      {notification.hasNotification && (
        <div
          className={cn(
            'wsms-flex wsms-items-center wsms-gap-3 wsms-p-4 wsms-rounded-lg wsms-border',
            'wsms-animate-in wsms-fade-in wsms-slide-in-from-top-2 wsms-duration-300',
            notification.isSuccess
              ? 'wsms-bg-emerald-50 wsms-border-emerald-200 wsms-text-emerald-800 dark:wsms-bg-emerald-900/30 dark:wsms-border-emerald-800 dark:wsms-text-emerald-200'
              : 'wsms-bg-red-50 wsms-border-red-200 wsms-text-red-800 dark:wsms-bg-red-900/30 dark:wsms-border-red-800 dark:wsms-text-red-200'
          )}
          role="alert"
        >
          {notification.isSuccess ? (
            <CheckCircle className="wsms-h-5 wsms-w-5 wsms-shrink-0" aria-hidden="true" />
          ) : (
            <AlertCircle className="wsms-h-5 wsms-w-5 wsms-shrink-0" aria-hidden="true" />
          )}
          <p className="wsms-text-[13px] wsms-font-medium">{notification.notification?.message}</p>
        </div>
      )}

      {/* Stats Header Bar */}
      <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-5 wsms-py-4 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
        <div className="wsms-flex wsms-items-center wsms-gap-8">
          {/* Total */}
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-primary/10">
              <Inbox className="wsms-h-5 wsms-w-5 wsms-text-primary" aria-hidden="true" />
            </div>
            <div>
              <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">{stats.total}</p>
              <p className="wsms-text-[11px] wsms-text-muted-foreground">Total Messages</p>
            </div>
          </div>

          <div className="wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

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

          <div className="wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

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

          <div className="wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

          {/* Success Rate */}
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <div
              className={cn(
                'wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg',
                successRate >= 90
                  ? 'wsms-bg-success/10'
                  : successRate >= 70
                    ? 'wsms-bg-amber-100 dark:wsms-bg-amber-900/30'
                    : 'wsms-bg-destructive/10'
              )}
            >
              {successRate >= 70 ? (
                <TrendingUp
                  className={cn(
                    'wsms-h-5 wsms-w-5',
                    successRate >= 90
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
                  successRate >= 90
                    ? 'wsms-text-success'
                    : successRate >= 70
                      ? 'wsms-text-amber-600 dark:wsms-text-amber-400'
                      : 'wsms-text-destructive'
                )}
              >
                {successRate}%
              </p>
              <p className="wsms-text-[11px] wsms-text-muted-foreground">Success Rate</p>
            </div>
          </div>
        </div>

        {/* Export Button */}
        <Button variant="outline" onClick={handleExport} disabled={isExporting}>
          {isExporting ? (
            <Loader2 className="wsms-h-4 wsms-w-4 wsms-animate-spin" aria-hidden="true" />
          ) : (
            <>
              <Download className="wsms-h-4 wsms-w-4 wsms-mr-2" aria-hidden="true" />
              Export
            </>
          )}
        </Button>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="wsms-py-4">
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            {/* Search */}
            <div className="wsms-flex-1 wsms-max-w-md">
              <div className="wsms-relative">
                <Search
                  className="wsms-absolute wsms-left-3 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-4 wsms-w-4 wsms-text-muted-foreground"
                  aria-hidden="true"
                />
                <Input
                  type="text"
                  value={filters.filters.search}
                  onChange={(e) => filters.setFilter('search', e.target.value)}
                  placeholder="Search messages or recipients..."
                  className="wsms-pl-9"
                  aria-label="Search messages"
                />
              </div>
            </div>

            {/* Status Filter */}
            <Select
              value={filters.filters.status}
              onValueChange={(value) => filters.setFilter('status', value)}
            >
              <SelectTrigger className="wsms-w-[130px]" aria-label="Filter by status">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Status</SelectItem>
                <SelectItem value="success">Sent</SelectItem>
                <SelectItem value="failed">Failed</SelectItem>
              </SelectContent>
            </Select>

            {/* Date Range */}
            <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-ml-auto">
              <Calendar className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" aria-hidden="true" />
              <Input
                type="date"
                value={filters.filters.date_from}
                onChange={(e) => filters.setFilter('date_from', e.target.value)}
                className="wsms-w-[130px]"
                aria-label="From date"
              />
              <span className="wsms-text-muted-foreground wsms-text-[12px]">to</span>
              <Input
                type="date"
                value={filters.filters.date_to}
                onChange={(e) => filters.setFilter('date_to', e.target.value)}
                className="wsms-w-[130px]"
                aria-label="To date"
              />
            </div>

            {/* Refresh */}
            <Button
              variant="outline"
              size="icon"
              onClick={() => table.fetch({ page: 1 })}
              aria-label="Refresh messages"
            >
              <RefreshCw
                className={cn('wsms-h-4 wsms-w-4', table.isLoading && 'wsms-animate-spin')}
                aria-hidden="true"
              />
            </Button>
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
            rowActions={rowActions}
            bulkActions={bulkActions}
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
                      {viewMessage.status === 'success' ? 'Sent' : 'Failed'}
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
    </div>
  )
}
