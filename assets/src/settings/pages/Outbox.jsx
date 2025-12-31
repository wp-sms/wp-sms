import React, { useState, useEffect, useCallback } from 'react'
import {
  Inbox,
  RefreshCw,
  Trash2,
  Send,
  Search,
  Calendar,
  Filter,
  Eye,
  MoreHorizontal,
  CheckCircle,
  XCircle,
  Loader2,
  AlertCircle,
  Download,
  Image,
  MessageSquare,
  Clock,
  TrendingUp,
  TrendingDown,
} from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
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

export default function Outbox() {
  // Data state
  const [messages, setMessages] = useState([])
  const [pagination, setPagination] = useState({
    total: 0,
    total_pages: 1,
    current_page: 1,
    per_page: 20,
  })
  const [stats, setStats] = useState({ total: 0, success: 0, failed: 0 })

  // Filter state
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [dateFrom, setDateFrom] = useState('')
  const [dateTo, setDateTo] = useState('')

  // UI state
  const [isLoading, setIsLoading] = useState(true)
  const [initialLoadDone, setInitialLoadDone] = useState(false)
  const [selectedIds, setSelectedIds] = useState([])
  const [viewMessage, setViewMessage] = useState(null)
  const [notification, setNotification] = useState(null)
  const [actionLoading, setActionLoading] = useState(null)
  const [isExporting, setIsExporting] = useState(false)
  const [quickReplyTo, setQuickReplyTo] = useState(null)
  const [quickReplyMessage, setQuickReplyMessage] = useState('')
  const [isSendingReply, setIsSendingReply] = useState(false)

  // Fetch messages
  const fetchMessages = useCallback(async (page = 1) => {
    setIsLoading(true)
    try {
      const result = await outboxApi.getMessages({
        page,
        per_page: pagination.per_page,
        search: search || undefined,
        status: statusFilter !== 'all' ? statusFilter : undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
      })
      setMessages(result.items)
      setPagination(result.pagination)
      setStats(result.stats)
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    } finally {
      setIsLoading(false)
      setInitialLoadDone(true)
    }
  }, [search, statusFilter, dateFrom, dateTo, pagination.per_page])

  // Initial fetch
  useEffect(() => {
    fetchMessages()
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  // Handle search with debounce
  useEffect(() => {
    const timer = setTimeout(() => {
      fetchMessages(1)
    }, 500)
    return () => clearTimeout(timer)
  }, [search, statusFilter, dateFrom, dateTo]) // eslint-disable-line react-hooks/exhaustive-deps

  // Handle page change
  const handlePageChange = (page) => {
    fetchMessages(page)
  }

  // Handle delete
  const handleDelete = async (id) => {
    setActionLoading(id)
    try {
      await outboxApi.deleteMessage(id)
      setNotification({ type: 'success', message: 'Message deleted successfully' })
      fetchMessages(pagination.current_page)
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    } finally {
      setActionLoading(null)
    }
  }

  // Handle resend
  const handleResend = async (id) => {
    setActionLoading(id)
    try {
      await outboxApi.resendMessage(id)
      setNotification({ type: 'success', message: 'Message resent successfully' })
      fetchMessages(pagination.current_page)
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    } finally {
      setActionLoading(null)
    }
  }

  // Handle bulk actions
  const handleBulkAction = async (action) => {
    if (selectedIds.length === 0) return

    setIsLoading(true)
    try {
      const result = await outboxApi.bulkAction(action, selectedIds)
      setNotification({
        type: 'success',
        message: `${result.affected} message(s) ${action === 'delete' ? 'deleted' : 'resent'} successfully`,
      })
      setSelectedIds([])
      fetchMessages(1)
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    } finally {
      setIsLoading(false)
    }
  }

  // Handle export
  const handleExport = async () => {
    setIsExporting(true)
    try {
      const result = await outboxApi.exportCsv({
        status: statusFilter !== 'all' ? statusFilter : undefined,
        date_from: dateFrom || undefined,
        date_to: dateTo || undefined,
      })
      outboxApi.downloadCsv(result.data, result.filename)
      setNotification({
        type: 'success',
        message: `Exported ${result.count} messages successfully`,
      })
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    } finally {
      setIsExporting(false)
    }
  }

  // Handle quick reply
  const handleQuickReply = async () => {
    if (!quickReplyTo || !quickReplyMessage.trim()) return

    setIsSendingReply(true)
    try {
      await smsApi.send({
        message: quickReplyMessage,
        recipients: { groups: [], roles: [], numbers: [quickReplyTo] },
      })
      setNotification({ type: 'success', message: 'Reply sent successfully' })
      setQuickReplyTo(null)
      setQuickReplyMessage('')
      fetchMessages(pagination.current_page)
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    } finally {
      setIsSendingReply(false)
    }
  }

  // Clear notification
  useEffect(() => {
    if (notification) {
      const timer = setTimeout(() => setNotification(null), 5000)
      return () => clearTimeout(timer)
    }
  }, [notification])

  // Calculate success rate
  const successRate = stats.total > 0 ? Math.round((stats.success / stats.total) * 100) : 0

  // Table columns
  const columns = [
    {
      id: 'date',
      accessorKey: 'date',
      header: 'Date',
      sortable: true,
      cell: ({ row }) => (
        <div className="wsms-flex wsms-items-center wsms-gap-2">
          <Clock className="wsms-h-3.5 wsms-w-3.5 wsms-text-muted-foreground" />
          <span className="wsms-text-[12px] wsms-text-muted-foreground">
            {formatDate(row.date, { hour: '2-digit', minute: '2-digit' })}
          </span>
        </div>
      ),
    },
    {
      id: 'recipient',
      accessorKey: 'recipient',
      header: 'Recipient',
      cell: ({ row }) => (
        <div className="wsms-space-y-0.5">
          <span className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">
            {row.recipient_count > 1 ? `${row.recipient_count} recipients` : row.recipient}
          </span>
          {row.sender && (
            <p className="wsms-text-[11px] wsms-text-muted-foreground">
              From: {row.sender}
            </p>
          )}
        </div>
      ),
    },
    {
      id: 'message',
      accessorKey: 'message',
      header: 'Message',
      cell: ({ row }) => (
        <p className="wsms-text-[12px] wsms-text-foreground wsms-line-clamp-2 wsms-max-w-md">
          {row.message}
        </p>
      ),
    },
    {
      id: 'media',
      accessorKey: 'media',
      header: 'Media',
      cell: ({ row }) => {
        if (!row.media) {
          return <span className="wsms-text-[12px] wsms-text-muted-foreground">—</span>
        }
        const mediaUrls = typeof row.media === 'string' ? row.media.split(',').map(url => url.trim()) : []
        return (
          <div className="wsms-flex wsms-items-center wsms-gap-1">
            {mediaUrls.slice(0, 2).map((url, idx) => (
              <a
                key={idx}
                href={url}
                target="_blank"
                rel="noopener noreferrer"
                className="wsms-flex wsms-items-center wsms-gap-1 wsms-px-2 wsms-py-1 wsms-rounded wsms-bg-muted/50 wsms-text-[11px] wsms-text-primary hover:wsms-bg-muted"
              >
                <Image className="wsms-h-3 wsms-w-3" />
              </a>
            ))}
            {mediaUrls.length > 2 && (
              <span className="wsms-text-[11px] wsms-text-muted-foreground">
                +{mediaUrls.length - 2}
              </span>
            )}
          </div>
        )
      },
    },
    {
      id: 'status',
      accessorKey: 'status',
      header: 'Status',
      cell: ({ row }) => (
        <StatusBadge variant={row.status === 'success' ? 'success' : 'failed'}>
          {row.status === 'success' ? 'Sent' : 'Failed'}
        </StatusBadge>
      ),
    },
  ]

  // Row actions
  const rowActions = [
    {
      label: 'View Details',
      icon: Eye,
      onClick: (row) => setViewMessage(row),
    },
    {
      label: 'Quick Reply',
      icon: MessageSquare,
      onClick: (row) => {
        const recipient = row.recipient?.split(',')[0]?.trim() || row.recipient
        setQuickReplyTo(recipient)
        setQuickReplyMessage('')
      },
    },
    {
      label: 'Resend',
      icon: Send,
      onClick: (row) => handleResend(row.id),
    },
    {
      label: 'Delete',
      icon: Trash2,
      onClick: (row) => handleDelete(row.id),
      variant: 'destructive',
    },
  ]

  // Bulk actions
  const bulkActions = [
    {
      label: 'Delete Selected',
      icon: Trash2,
      onClick: () => handleBulkAction('delete'),
      variant: 'destructive',
    },
    {
      label: 'Resend Selected',
      icon: RefreshCw,
      onClick: () => handleBulkAction('resend'),
    },
  ]

  // Show skeleton during initial load to prevent flash
  if (!initialLoadDone) {
    return (
      <div className="wsms-space-y-6">
        <div className="wsms-h-24 wsms-rounded-lg wsms-bg-muted/30 wsms-animate-pulse" />
        <div className="wsms-h-16 wsms-rounded-lg wsms-bg-muted/30 wsms-animate-pulse" />
        <div className="wsms-h-64 wsms-rounded-lg wsms-bg-muted/30 wsms-animate-pulse" />
      </div>
    )
  }

  // Empty state
  const hasNoMessages = messages.length === 0 && !search && statusFilter === 'all'

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
                When you send SMS messages, they will appear here.
                You can track delivery status, resend failed messages, and export your history.
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
      {notification && (
        <div
          className={cn(
            'wsms-flex wsms-items-center wsms-gap-3 wsms-p-4 wsms-rounded-lg wsms-border',
            'wsms-animate-in wsms-fade-in wsms-slide-in-from-top-2 wsms-duration-300',
            notification.type === 'success'
              ? 'wsms-bg-emerald-500/10 wsms-border-emerald-500/20 wsms-text-emerald-700 dark:wsms-text-emerald-400'
              : 'wsms-bg-red-500/10 wsms-border-red-500/20 wsms-text-red-700 dark:wsms-text-red-400'
          )}
        >
          {notification.type === 'success' ? (
            <CheckCircle className="wsms-h-5 wsms-w-5 wsms-shrink-0" />
          ) : (
            <AlertCircle className="wsms-h-5 wsms-w-5 wsms-shrink-0" />
          )}
          <p className="wsms-text-[13px] wsms-font-medium">{notification.message}</p>
        </div>
      )}

      {/* Stats Header Bar - Full Width */}
      <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-5 wsms-py-4 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
        <div className="wsms-flex wsms-items-center wsms-gap-8">
          {/* Total */}
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-primary/10">
              <Inbox className="wsms-h-5 wsms-w-5 wsms-text-primary" />
            </div>
            <div>
              <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">{stats.total}</p>
              <p className="wsms-text-[11px] wsms-text-muted-foreground">Total Messages</p>
            </div>
          </div>

          <div className="wsms-w-px wsms-h-10 wsms-bg-border" />

          {/* Sent */}
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-success/10">
              <CheckCircle className="wsms-h-5 wsms-w-5 wsms-text-success" />
            </div>
            <div>
              <p className="wsms-text-xl wsms-font-bold wsms-text-success">{stats.success}</p>
              <p className="wsms-text-[11px] wsms-text-muted-foreground">Sent</p>
            </div>
          </div>

          <div className="wsms-w-px wsms-h-10 wsms-bg-border" />

          {/* Failed */}
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-destructive/10">
              <XCircle className="wsms-h-5 wsms-w-5 wsms-text-destructive" />
            </div>
            <div>
              <p className="wsms-text-xl wsms-font-bold wsms-text-destructive">{stats.failed}</p>
              <p className="wsms-text-[11px] wsms-text-muted-foreground">Failed</p>
            </div>
          </div>

          <div className="wsms-w-px wsms-h-10 wsms-bg-border" />

          {/* Success Rate */}
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <div className={cn(
              'wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg',
              successRate >= 90 ? 'wsms-bg-success/10' : successRate >= 70 ? 'wsms-bg-amber-500/10' : 'wsms-bg-destructive/10'
            )}>
              {successRate >= 90 ? (
                <TrendingUp className="wsms-h-5 wsms-w-5 wsms-text-success" />
              ) : successRate >= 70 ? (
                <TrendingUp className="wsms-h-5 wsms-w-5 wsms-text-amber-500" />
              ) : (
                <TrendingDown className="wsms-h-5 wsms-w-5 wsms-text-destructive" />
              )}
            </div>
            <div>
              <p className={cn(
                'wsms-text-xl wsms-font-bold',
                successRate >= 90 ? 'wsms-text-success' : successRate >= 70 ? 'wsms-text-amber-500' : 'wsms-text-destructive'
              )}>
                {successRate}%
              </p>
              <p className="wsms-text-[11px] wsms-text-muted-foreground">Success Rate</p>
            </div>
          </div>
        </div>

        {/* Export Button */}
        <Button variant="outline" onClick={handleExport} disabled={isExporting}>
          {isExporting ? (
            <Loader2 className="wsms-h-4 wsms-w-4 wsms-animate-spin" />
          ) : (
            <>
              <Download className="wsms-h-4 wsms-w-4 wsms-mr-2" />
              Export
            </>
          )}
        </Button>
      </div>

      {/* Filters - Full Width */}
      <Card>
        <CardContent className="wsms-py-4">
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            {/* Search */}
            <div className="wsms-flex-1 wsms-max-w-md">
              <div className="wsms-relative">
                <Search className="wsms-absolute wsms-left-3 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                <Input
                  type="text"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Search messages or recipients..."
                  className="wsms-pl-9"
                />
              </div>
            </div>

            {/* Status Filter */}
            <Select value={statusFilter} onValueChange={setStatusFilter}>
              <SelectTrigger className="wsms-w-[130px]">
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
              <Calendar className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <Input
                type="date"
                value={dateFrom}
                onChange={(e) => setDateFrom(e.target.value)}
                className="wsms-w-[130px]"
              />
              <span className="wsms-text-muted-foreground wsms-text-[12px]">to</span>
              <Input
                type="date"
                value={dateTo}
                onChange={(e) => setDateTo(e.target.value)}
                className="wsms-w-[130px]"
              />
            </div>

            {/* Refresh */}
            <Button variant="outline" size="icon" onClick={() => fetchMessages(1)}>
              <RefreshCw className={cn('wsms-h-4 wsms-w-4', isLoading && 'wsms-animate-spin')} />
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Data Table - Full Width */}
      <Card>
        <CardContent className="wsms-p-0">
          <DataTable
            columns={columns}
            data={messages}
            loading={isLoading}
            pagination={{
              total: pagination.total,
              totalPages: pagination.total_pages,
              page: pagination.current_page,
              perPage: pagination.per_page,
              onPageChange: handlePageChange,
            }}
            selection={{
              selected: selectedIds,
              onSelect: (id) => {
                setSelectedIds((prev) =>
                  prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
                )
              },
              onSelectAll: (checked) => {
                setSelectedIds(checked ? messages.map((m) => m.id) : [])
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
              <MessageSquare className="wsms-h-4 wsms-w-4 wsms-text-primary" />
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
                  <div className="wsms-w-px wsms-h-8 wsms-bg-border" />
                  <div className="wsms-flex-1">
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">Recipients</p>
                    <p className="wsms-text-[13px] wsms-font-medium">{viewMessage.recipient_count}</p>
                  </div>
                  {viewMessage.sender && (
                    <>
                      <div className="wsms-w-px wsms-h-8 wsms-bg-border" />
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
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">Gateway Response</p>
                    <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-font-mono wsms-p-2 wsms-rounded wsms-bg-muted/30">
                      {viewMessage.response}
                    </p>
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
              <Send className="wsms-h-4 wsms-w-4 wsms-mr-2" />
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
              <MessageSquare className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              Quick Reply
            </DialogTitle>
            <DialogDescription>
              Send a quick reply to this recipient
            </DialogDescription>
          </DialogHeader>
          <DialogBody>
            <div className="wsms-space-y-4">
              <div className="wsms-space-y-2">
                <label className="wsms-text-[12px] wsms-font-medium">To</label>
                <Input
                  value={quickReplyTo || ''}
                  readOnly
                  className="wsms-bg-muted/50 wsms-font-mono"
                />
              </div>
              <div className="wsms-space-y-2">
                <label className="wsms-text-[12px] wsms-font-medium">Message</label>
                <textarea
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
            <Button
              onClick={handleQuickReply}
              disabled={isSendingReply || !quickReplyMessage.trim()}
            >
              {isSendingReply ? (
                <>
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" />
                  Sending...
                </>
              ) : (
                <>
                  <Send className="wsms-h-4 wsms-w-4 wsms-mr-2" />
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
