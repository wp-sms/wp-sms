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
} from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { DataTable } from '@/components/ui/data-table'
import { StatusBadge } from '@/components/shared/StatusBadge'
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

  // Table columns
  const columns = [
    {
      key: 'date',
      label: 'Date',
      sortable: true,
      render: (row) => (
        <span className="wsms-text-[12px] wsms-text-muted-foreground">
          {formatDate(row.date, { hour: '2-digit', minute: '2-digit' })}
        </span>
      ),
    },
    {
      key: 'recipient',
      label: 'Recipient',
      render: (row) => (
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
      key: 'message',
      label: 'Message',
      render: (row) => (
        <p className="wsms-text-[12px] wsms-text-foreground wsms-line-clamp-2 wsms-max-w-xs">
          {row.message}
        </p>
      ),
    },
    {
      key: 'media',
      label: 'Media',
      render: (row) => {
        if (!row.media) {
          return <span className="wsms-text-[12px] wsms-text-muted-foreground">—</span>
        }
        // Handle media as URL or comma-separated URLs
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
      key: 'status',
      label: 'Status',
      render: (row) => (
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
        // Extract first number from recipient if multiple
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

  return (
    <div className="wsms-space-y-6">
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

      {/* Stats Cards */}
      <div className="wsms-grid wsms-grid-cols-3 wsms-gap-4">
        <Card className="wsms-py-4">
          <CardContent className="wsms-py-0 wsms-text-center">
            <p className="wsms-text-2xl wsms-font-bold wsms-text-foreground">{stats.total}</p>
            <p className="wsms-text-[11px] wsms-text-muted-foreground">Total Messages</p>
          </CardContent>
        </Card>
        <Card className="wsms-py-4">
          <CardContent className="wsms-py-0 wsms-text-center">
            <p className="wsms-text-2xl wsms-font-bold wsms-text-emerald-600">{stats.success}</p>
            <p className="wsms-text-[11px] wsms-text-muted-foreground">Sent</p>
          </CardContent>
        </Card>
        <Card className="wsms-py-4">
          <CardContent className="wsms-py-0 wsms-text-center">
            <p className="wsms-text-2xl wsms-font-bold wsms-text-red-600">{stats.failed}</p>
            <p className="wsms-text-[11px] wsms-text-muted-foreground">Failed</p>
          </CardContent>
        </Card>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="wsms-py-4">
          <div className="wsms-flex wsms-flex-wrap wsms-gap-3">
            <div className="wsms-flex-1 wsms-min-w-[200px]">
              <div className="wsms-relative">
                <Search className="wsms-absolute wsms-left-3 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                <Input
                  type="text"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Search messages..."
                  className="wsms-pl-9"
                />
              </div>
            </div>
            <Select value={statusFilter} onValueChange={setStatusFilter}>
              <SelectTrigger className="wsms-w-[140px]">
                <Filter className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">All Status</SelectItem>
                <SelectItem value="success">Sent</SelectItem>
                <SelectItem value="failed">Failed</SelectItem>
              </SelectContent>
            </Select>
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <Calendar className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <Input
                type="date"
                value={dateFrom}
                onChange={(e) => setDateFrom(e.target.value)}
                className="wsms-w-[140px]"
              />
              <span className="wsms-text-muted-foreground">to</span>
              <Input
                type="date"
                value={dateTo}
                onChange={(e) => setDateTo(e.target.value)}
                className="wsms-w-[140px]"
              />
            </div>
            <Button variant="outline" onClick={() => fetchMessages(1)}>
              <RefreshCw className="wsms-h-4 wsms-w-4" />
            </Button>
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
        </CardContent>
      </Card>

      {/* Data Table */}
      <Card>
        <CardContent className="wsms-p-0">
          <DataTable
            columns={columns}
            data={messages}
            loading={isLoading}
            pagination={{
              total: pagination.total,
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
            emptyMessage="No messages found"
            emptyIcon={Inbox}
          />
        </CardContent>
      </Card>

      {/* View Message Dialog */}
      <Dialog open={!!viewMessage} onOpenChange={() => setViewMessage(null)}>
        <DialogContent size="lg">
          <DialogHeader>
            <DialogTitle>Message Details</DialogTitle>
            <DialogDescription>
              Sent on {viewMessage && formatDate(viewMessage.date, { hour: '2-digit', minute: '2-digit' })}
            </DialogDescription>
          </DialogHeader>
          <DialogBody>
            {viewMessage && (
              <div className="wsms-space-y-4">
                <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4">
                  <div>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">Status</p>
                    <StatusBadge variant={viewMessage.status === 'success' ? 'success' : 'failed'}>
                      {viewMessage.status === 'success' ? 'Sent' : 'Failed'}
                    </StatusBadge>
                  </div>
                  <div>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">Recipients</p>
                    <p className="wsms-text-[13px] wsms-font-medium">{viewMessage.recipient_count}</p>
                  </div>
                </div>
                <div>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">Recipient(s)</p>
                  <p className="wsms-text-[13px] wsms-break-all">{viewMessage.recipient}</p>
                </div>
                {viewMessage.sender && (
                  <div>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">Sender</p>
                    <p className="wsms-text-[13px]">{viewMessage.sender}</p>
                  </div>
                )}
                <div>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">Message</p>
                  <div className="wsms-p-3 wsms-rounded-md wsms-bg-muted/50 wsms-border wsms-border-border">
                    <p className="wsms-text-[13px] wsms-whitespace-pre-wrap">{viewMessage.message}</p>
                  </div>
                </div>
                {viewMessage.response && (
                  <div>
                    <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">Gateway Response</p>
                    <p className="wsms-text-[12px] wsms-text-muted-foreground">{viewMessage.response}</p>
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
                  className="wsms-bg-muted/50"
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
