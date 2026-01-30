import React, { useState, useCallback, useEffect } from 'react'
import {
  Inbox,
  AlertCircle,
  ExternalLink,
  RefreshCw,
  Eye,
  MessageSquare,
  Trash2,
  Calendar,
  Clock,
  Loader2,
  MailOpen,
  Search,
  X,
} from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { DataTable } from '@/components/ui/data-table'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { ExportButton } from '@/components/shared/ExportButton'
import { DeleteConfirmDialog } from '@/components/shared/DeleteConfirmDialog'
import { PageLoadingSkeleton } from '@/components/ui/skeleton'
import { useListPage } from '@/hooks/useListPage'
import { useFormDialog } from '@/hooks/useFormDialog'
import { useSettings } from '@/context/SettingsContext'
import { useToast } from '@/components/ui/toaster'
import { cn, __ } from '@/lib/utils'
import { inboxApi } from '@/api/twoWayApi'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogBody,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from '@/components/ui/dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'

export default function TwoWayInbox() {
  const { isAddonActive } = useSettings()
  const { toast } = useToast()
  const hasTwoWay = isAddonActive('two-way')

  // Commands for filter dropdown
  const [commands, setCommands] = useState([])

  // Dialog states
  const [viewingMessage, setViewingMessage] = useState(null)
  const [replyingTo, setReplyingTo] = useState(null)
  const [replyMessage, setReplyMessage] = useState('')
  const [isReplying, setIsReplying] = useState(false)
  const [showBulkDeleteConfirm, setShowBulkDeleteConfirm] = useState(false)

  // Fetch commands for filter
  useEffect(() => {
    if (hasTwoWay) {
      inboxApi.getCommands().then((response) => {
        if (response.success) {
          setCommands(Array.isArray(response.data) ? response.data : [])
        }
      }).catch(() => {})
    }
  }, [hasTwoWay])

  // Stats (fetched separately — the messages endpoint does not include stats)
  const [stats, setStats] = useState({ total: 0, today: 0, week: 0, unread: 0 })

  const fetchStats = useCallback(() => {
    inboxApi.getStats().then((res) => {
      if (res.success && res.data) {
        setStats({
          total: res.data.total || 0,
          today: res.data.today || 0,
          week: res.data.this_week || 0,
          unread: res.data.unread || 0,
        })
      }
    }).catch(() => {})
  }, [])

  useEffect(() => {
    if (hasTwoWay) fetchStats()
  }, [hasTwoWay, fetchStats])

  // useListPage for table + filters
  const { filters, table, handleDelete, handleBulkDelete } = useListPage({
    fetchFn: async (params) => {
      const response = await inboxApi.getMessages(params)
      if (!response.success) throw new Error(__('Failed to load messages'))
      const data = response.data || {}
      return {
        items: Array.isArray(data.messages) ? data.messages : [],
        pagination: {
          total: data.total || 0,
          total_pages: data.total_pages || 1,
          current_page: data.page || 1,
          per_page: data.per_page || 20,
        },
      }
    },
    deleteFn: (id) => inboxApi.deleteMessage(id),
    bulkActionFn: (action, ids) => inboxApi.bulkDelete(ids),
    initialFilters: { search: '', status: 'all', action_status: 'all', command_id: 'all' },
    messages: {
      deleteSuccess: __('Message deleted'),
      bulkSuccess: __('Messages deleted'),
    },
  })

  // Delete confirmation dialog
  const deleteDialog = useFormDialog({
    saveFn: async (id) => {
      await handleDelete(id)
      fetchStats()
    },
    successMessage: __('Message deleted'),
  })

  const handleDeleteClick = useCallback((message) => {
    deleteDialog.open(message)
  }, [deleteDialog])

  const handleDeleteConfirm = async () => {
    if (!deleteDialog.item) return
    try {
      await deleteDialog.save()
    } catch {
      // handled by useFormDialog
    }
  }

  // Reply handler
  const handleReply = async () => {
    if (!replyingTo || !replyMessage.trim()) return
    try {
      setIsReplying(true)
      const response = await inboxApi.replyToMessage(replyingTo.id, replyMessage.trim())
      if (response.success) {
        toast({ title: __('Reply sent successfully'), variant: 'success' })
        setReplyingTo(null)
        setReplyMessage('')
      }
    } catch (error) {
      toast({ title: error.message || __('Failed to send reply'), variant: 'destructive' })
    } finally {
      setIsReplying(false)
    }
  }

  // Mark as read
  const handleMarkAsRead = async (id) => {
    try {
      await inboxApi.markAsRead(id)
      table.refresh()
      fetchStats()
    } catch {
      // silent
    }
  }

  // Export
  const handleExport = async () => {
    const params = {}
    const f = filters.debouncedFilters
    if (f.search) params.search = f.search
    if (f.status && f.status !== 'all') params.status = f.status
    if (f.action_status && f.action_status !== 'all') params.action_status = f.action_status
    if (f.command_id && f.command_id !== 'all') params.command_id = f.command_id

    const response = await inboxApi.exportMessages(params)
    if (response.success && response.data?.csv) {
      const blob = new Blob(['\ufeff' + response.data.csv], { type: 'text/csv;charset=utf-8;' })
      const link = document.createElement('a')
      const url = URL.createObjectURL(blob)
      link.setAttribute('href', url)
      link.setAttribute('download', response.data.filename || 'inbox-export.csv')
      link.style.visibility = 'hidden'
      document.body.appendChild(link)
      link.click()
      document.body.removeChild(link)
      URL.revokeObjectURL(url)
      return { count: response.data.total }
    }
    throw new Error(__('Failed to export messages'))
  }

  // Bulk delete with confirmation
  const handleBulkDeleteConfirm = useCallback(async () => {
    setShowBulkDeleteConfirm(false)
    try {
      await handleBulkDelete()
      fetchStats()
    } catch {
      // handled
    }
  }, [handleBulkDelete, fetchStats])

  // Check if any filters are active
  const hasActiveFilters = filters.filters.search ||
    filters.filters.action_status !== 'all' ||
    filters.filters.command_id !== 'all' ||
    filters.filters.status !== 'all'

  // Table columns
  const columns = [
    {
      id: 'sender_number',
      accessorKey: 'sender_number',
      header: __('Sender'),
      cell: ({ row }) => (
        <span className="wsms-text-[13px] wsms-font-mono wsms-text-foreground">
          {row.sender_number}
        </span>
      ),
    },
    {
      id: 'text',
      accessorKey: 'text',
      header: __('Message'),
      cell: ({ value }) => (
        <span className="wsms-text-[13px] wsms-max-w-xs wsms-truncate wsms-block">
          {value?.substring(0, 50)}{value?.length > 50 ? '...' : ''}
        </span>
      ),
    },
    {
      id: 'command_name',
      accessorKey: 'command_name',
      header: __('Command'),
      cell: ({ value }) => value
        ? <Badge variant="outline">{value}</Badge>
        : <span className="wsms-text-[12px] wsms-text-muted-foreground">—</span>,
    },
    {
      id: 'action_status',
      accessorKey: 'action_status',
      header: __('Action Status'),
      cell: ({ value }) => {
        if (value === 'successful') return <StatusBadge variant="success">{__('Successful')}</StatusBadge>
        if (value === 'failed') return <StatusBadge variant="failed">{__('Failed')}</StatusBadge>
        if (value === 'plain') return <StatusBadge variant="default">{__('Plain')}</StatusBadge>
        return <span className="wsms-text-[12px] wsms-text-muted-foreground">—</span>
      },
    },
    {
      id: 'received_at',
      header: __('Date'),
      cell: ({ row }) => (
        <span className="wsms-text-[12px] wsms-text-muted-foreground">
          {row.received_at_formatted || row.received_at}
        </span>
      ),
    },
    {
      id: 'is_read',
      accessorKey: 'is_read',
      header: __('Read'),
      cell: ({ value }) => value
        ? <StatusBadge variant="default" showIcon={false}>{__('Read')}</StatusBadge>
        : <StatusBadge variant="active">{__('New')}</StatusBadge>,
    },
  ]

  // Row actions
  const rowActions = [
    {
      label: __('View'),
      icon: Eye,
      onClick: (row) => {
        setViewingMessage(row)
        if (!row.is_read) handleMarkAsRead(row.id)
      },
    },
    {
      label: __('Reply'),
      icon: MessageSquare,
      onClick: (row) => setReplyingTo(row),
    },
    {
      label: __('Delete'),
      icon: Trash2,
      variant: 'destructive',
      onClick: handleDeleteClick,
    },
  ]

  // Bulk actions
  const bulkActions = [
    {
      label: __('Delete Selected'),
      icon: Trash2,
      variant: 'destructive',
      onClick: () => setShowBulkDeleteConfirm(true),
    },
  ]

  // Addon not active placeholder
  if (!hasTwoWay) {
    return (
      <div className="wsms-space-y-6">
        <Card className="wsms-border-dashed">
          <CardContent className="wsms-py-16">
            <div className="wsms-flex wsms-flex-col wsms-items-center wsms-text-center wsms-max-w-md wsms-mx-auto">
              <div className="wsms-flex wsms-h-16 wsms-w-16 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-primary/10 wsms-mb-6">
                <Inbox className="wsms-h-8 wsms-w-8 wsms-text-primary" strokeWidth={1.5} />
              </div>
              <h3 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-2">
                {__('Two-Way SMS Add-on Required')}
              </h3>
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-6">
                {__('Install and activate the WP SMS Two-Way add-on to receive incoming messages.')}
              </p>
              <Button variant="outline" asChild>
                <a href="https://wp-sms-pro.com/product/wp-sms-two-way/" target="_blank" rel="noopener noreferrer">
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

  // Initial loading
  if (!table.initialLoadDone) {
    return <PageLoadingSkeleton />
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
                <MessageSquare className="wsms-h-5 wsms-w-5 wsms-text-primary" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">{stats.total}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Total')}</p>
              </div>
            </div>

            <div className="wsms-hidden xl:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* Today */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-success/10">
                <Clock className="wsms-h-5 wsms-w-5 wsms-text-success" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-success">{stats.today}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Today')}</p>
              </div>
            </div>

            <div className="wsms-hidden xl:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* This Week */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-blue-100 dark:wsms-bg-blue-900/30">
                <Calendar className="wsms-h-5 wsms-w-5 wsms-text-blue-600 dark:wsms-text-blue-400" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">{stats.week}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('This Week')}</p>
              </div>
            </div>

            <div className="wsms-hidden xl:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* Unread */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className={cn(
                'wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg',
                stats.unread > 0 ? 'wsms-bg-destructive/10' : 'wsms-bg-slate-200 dark:wsms-bg-slate-700'
              )}>
                <MailOpen className={cn(
                  'wsms-h-5 wsms-w-5',
                  stats.unread > 0 ? 'wsms-text-destructive' : 'wsms-text-slate-500 dark:wsms-text-slate-400'
                )} aria-hidden="true" />
              </div>
              <div>
                <p className={cn(
                  'wsms-text-xl wsms-font-bold',
                  stats.unread > 0 ? 'wsms-text-destructive' : 'wsms-text-muted-foreground'
                )}>{stats.unread}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Unread')}</p>
              </div>
            </div>
          </div>

          {/* Export */}
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
          <div className="wsms-flex wsms-flex-col wsms-gap-3 xl:wsms-flex-row xl:wsms-items-center xl:wsms-gap-2">
            {/* Search */}
            <div className="wsms-relative wsms-w-full xl:wsms-w-[220px] xl:wsms-shrink-0">
              <Search className="wsms-absolute wsms-left-2.5 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-pointer-events-none" aria-hidden="true" />
              <Input
                type="text"
                value={filters.filters.search}
                onChange={(e) => filters.setFilter('search', e.target.value)}
                placeholder={__('Search messages...')}
                className="wsms-pl-8 wsms-h-9"
                aria-label={__('Search messages')}
              />
            </div>

            {/* Filters */}
            <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 xl:wsms-flex xl:wsms-items-center xl:wsms-gap-2">
              <Select value={filters.filters.action_status} onValueChange={(v) => filters.setFilter('action_status', v)}>
                <SelectTrigger className="wsms-h-9 wsms-w-full xl:wsms-w-[120px] wsms-text-[12px]" aria-label={__('Filter by action status')}>
                  <SelectValue placeholder={__('All Actions')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{__('All Actions')}</SelectItem>
                  <SelectItem value="successful">{__('Successful')}</SelectItem>
                  <SelectItem value="failed">{__('Failed')}</SelectItem>
                  <SelectItem value="plain">{__('Plain')}</SelectItem>
                </SelectContent>
              </Select>

              <Select value={filters.filters.command_id} onValueChange={(v) => filters.setFilter('command_id', v)}>
                <SelectTrigger className="wsms-h-9 wsms-w-full xl:wsms-w-[130px] wsms-text-[12px]" aria-label={__('Filter by command')}>
                  <SelectValue placeholder={__('All Commands')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{__('All Commands')}</SelectItem>
                  {commands.map((cmd) => (
                    <SelectItem key={cmd.id} value={String(cmd.id)}>
                      {cmd.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              <Select value={filters.filters.status} onValueChange={(v) => filters.setFilter('status', v)}>
                <SelectTrigger className="wsms-h-9 wsms-w-full xl:wsms-w-[100px] wsms-text-[12px]" aria-label={__('Filter by read status')}>
                  <SelectValue placeholder={__('All')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{__('All')}</SelectItem>
                  <SelectItem value="unread">{__('Unread')}</SelectItem>
                  <SelectItem value="read">{__('Read')}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {/* Actions */}
            <div className="wsms-flex wsms-items-center wsms-gap-2 xl:wsms-ml-auto">
              {hasActiveFilters && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={filters.resetFilters}
                  className="wsms-h-9 wsms-px-2.5 wsms-text-muted-foreground hover:wsms-text-foreground"
                  aria-label={__('Clear all filters')}
                >
                  <X className="wsms-h-4 wsms-w-4" aria-hidden="true" />
                </Button>
              )}
              <Button
                variant="outline"
                size="sm"
                onClick={() => { table.fetch({ page: 1 }); fetchStats() }}
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
            columns={columns}
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
              onSelectAll: table.toggleSelectAll,
            }}
            rowActions={rowActions}
            bulkActions={bulkActions}
            emptyMessage={__('No messages match your filters')}
            emptyIcon={Inbox}
          />
        </CardContent>
      </Card>

      {/* View Message Dialog */}
      <Dialog open={!!viewingMessage} onOpenChange={() => setViewingMessage(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <MessageSquare className="wsms-h-4 wsms-w-4 wsms-text-primary" aria-hidden="true" />
              {__('Message Details')}
            </DialogTitle>
            <DialogDescription>
              {__('Received')}: {viewingMessage?.received_at_formatted || viewingMessage?.received_at}
            </DialogDescription>
          </DialogHeader>
          <DialogBody>
            <div className="wsms-space-y-4">
              {/* Sender & Status Row */}
              <div className="wsms-flex wsms-items-center wsms-gap-4 wsms-p-4 wsms-rounded-lg wsms-bg-muted/30">
                <div className="wsms-flex-1">
                  <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">{__('Sender')}</p>
                  <p className="wsms-text-[13px] wsms-font-mono wsms-font-medium">{viewingMessage?.sender_number}</p>
                </div>
                <div className="wsms-w-px wsms-h-8 wsms-bg-border" aria-hidden="true" />
                <div className="wsms-flex-1">
                  <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">{__('Status')}</p>
                  {viewingMessage?.action_status === 'successful'
                    ? <StatusBadge variant="success">{__('Successful')}</StatusBadge>
                    : viewingMessage?.action_status === 'failed'
                      ? <StatusBadge variant="failed">{__('Failed')}</StatusBadge>
                      : <StatusBadge variant="default">{__('Plain')}</StatusBadge>
                  }
                </div>
                {viewingMessage?.command_name && (
                  <>
                    <div className="wsms-w-px wsms-h-8 wsms-bg-border" aria-hidden="true" />
                    <div className="wsms-flex-1">
                      <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">{__('Command')}</p>
                      <Badge variant="outline">{viewingMessage.command_name}</Badge>
                    </div>
                  </>
                )}
              </div>

              {/* Message */}
              <div>
                <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mb-1">{__('Message')}</p>
                <div className="wsms-p-4 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
                  <p className="wsms-text-[13px] wsms-whitespace-pre-wrap">{viewingMessage?.text}</p>
                </div>
              </div>
            </div>
          </DialogBody>
          <DialogFooter>
            <Button variant="outline" onClick={() => setViewingMessage(null)}>
              {__('Close')}
            </Button>
            <Button onClick={() => {
              setReplyingTo(viewingMessage)
              setViewingMessage(null)
            }}>
              <MessageSquare className="wsms-h-4 wsms-w-4 wsms-mr-2" aria-hidden="true" />
              {__('Reply')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Reply Dialog */}
      <Dialog open={!!replyingTo} onOpenChange={() => { setReplyingTo(null); setReplyMessage('') }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <MessageSquare className="wsms-h-4 wsms-w-4 wsms-text-primary" aria-hidden="true" />
              {__('Quick Reply')}
            </DialogTitle>
            <DialogDescription>
              {__('Send an SMS reply to this number')}
            </DialogDescription>
          </DialogHeader>
          <DialogBody>
            <div className="wsms-space-y-4">
              <div className="wsms-p-3 wsms-rounded-lg wsms-bg-muted/50 wsms-border wsms-border-border">
                <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mb-1">{__('Recipient')}</p>
                <p className="wsms-text-[13px] wsms-font-mono wsms-text-foreground">
                  {replyingTo?.sender_number}
                </p>
              </div>
              <div className="wsms-p-3 wsms-rounded-lg wsms-bg-muted/30 wsms-text-[13px]">
                <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mb-1">{__('Original message')}</p>
                <p>{replyingTo?.text?.substring(0, 100)}{replyingTo?.text?.length > 100 ? '...' : ''}</p>
              </div>
              <div className="wsms-space-y-2">
                <label className="wsms-text-[12px] wsms-font-medium">{__('Message')}</label>
                <Textarea
                  placeholder={__('Type your reply...')}
                  value={replyMessage}
                  onChange={(e) => setReplyMessage(e.target.value)}
                  rows={4}
                />
                <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-text-right">
                  {replyMessage.length} {__('characters')}
                </p>
              </div>
            </div>
          </DialogBody>
          <DialogFooter>
            <Button variant="outline" onClick={() => { setReplyingTo(null); setReplyMessage('') }}>
              {__('Cancel')}
            </Button>
            <Button onClick={handleReply} disabled={!replyMessage.trim() || isReplying}>
              {isReplying ? (
                <>
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" aria-hidden="true" />
                  {__('Sending...')}
                </>
              ) : __('Send Reply')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation */}
      <DeleteConfirmDialog
        isOpen={deleteDialog.isOpen}
        onClose={deleteDialog.close}
        onConfirm={handleDeleteConfirm}
        isSaving={deleteDialog.isSaving}
        title={__('Delete Message')}
        description={__('Are you sure you want to delete this message?')}
      >
        <div className="wsms-p-4 wsms-rounded-md wsms-bg-muted/50 wsms-border wsms-border-border">
          <div className="wsms-space-y-1">
            <p className="wsms-text-[13px] wsms-font-mono wsms-text-muted-foreground">
              {deleteDialog.item?.sender_number}
            </p>
            <p className="wsms-text-[13px] wsms-text-foreground">
              {deleteDialog.item?.text?.substring(0, 80)}{deleteDialog.item?.text?.length > 80 ? '...' : ''}
            </p>
          </div>
        </div>
      </DeleteConfirmDialog>

      {/* Bulk Delete Confirmation */}
      <DeleteConfirmDialog
        isOpen={showBulkDeleteConfirm}
        onClose={() => setShowBulkDeleteConfirm(false)}
        onConfirm={handleBulkDeleteConfirm}
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
