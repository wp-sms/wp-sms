import React, { useState, useCallback, useEffect } from 'react'
import { Inbox, AlertCircle, ExternalLink, RefreshCw, Eye, MessageSquare, Trash2, Filter, CheckCircle, Clock, MailOpen } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
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
import { __ } from '@/lib/utils'
import { inboxApi } from '@/api/twoWayApi'
import {
  Dialog,
  DialogContent,
  DialogDescription,
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
          current_page: data.current_page || 1,
          per_page: data.per_page || 20,
        },
        stats: data.stats || { total: 0, today: 0, week: 0, unread: 0 },
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

  const stats = table.stats || { total: 0, today: 0, week: 0, unread: 0 }

  // Delete confirmation dialog
  const deleteDialog = useFormDialog({
    saveFn: async (id) => {
      await handleDelete(id)
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
    } catch {
      // handled
    }
  }, [handleBulkDelete])

  // Table columns
  const columns = [
    {
      id: 'sender_number',
      accessorKey: 'sender_number',
      header: __('Sender'),
      cellClassName: 'wsms-font-mono wsms-text-[12px]',
    },
    {
      id: 'text',
      accessorKey: 'text',
      header: __('Message'),
      cell: ({ value }) => (
        <span className="wsms-max-w-xs wsms-truncate wsms-block">
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
        : <span className="wsms-text-muted-foreground">—</span>,
    },
    {
      id: 'action_status',
      accessorKey: 'action_status',
      header: __('Action Status'),
      cell: ({ value }) => {
        if (value === 'successful') return <StatusBadge variant="success">{__('Successful')}</StatusBadge>
        if (value === 'failed') return <StatusBadge variant="failed">{__('Failed')}</StatusBadge>
        if (value === 'plain') return <StatusBadge variant="default">{__('Plain')}</StatusBadge>
        return <span className="wsms-text-muted-foreground">—</span>
      },
    },
    {
      id: 'received_at',
      header: __('Date'),
      cell: ({ row }) => (
        <span className="wsms-text-muted-foreground">
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
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Inbox className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              {__('Two-Way SMS Inbox')}
            </CardTitle>
            <CardDescription>
              {__('Receive and manage incoming SMS messages')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-6 wsms-text-center">
              <AlertCircle className="wsms-mx-auto wsms-h-10 wsms-w-10 wsms-text-muted-foreground wsms-mb-3" />
              <h3 className="wsms-font-medium wsms-mb-2">{__('Two-Way SMS Add-on Required')}</h3>
              <p className="wsms-text-sm wsms-text-muted-foreground wsms-mb-4">
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
      {/* Page Header */}
      <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4">
        <div>
          <h1 className="wsms-text-xl wsms-font-semibold wsms-text-foreground wsms-flex wsms-items-center wsms-gap-2">
            <Inbox className="wsms-h-5 wsms-w-5" />
            {__('Inbox')}
          </h1>
          <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mt-1">
            {__('View and manage incoming SMS messages from your subscribers.')}
          </p>
        </div>
        <div className="wsms-flex wsms-items-center wsms-gap-2">
          <Button variant="outline" size="icon" onClick={() => table.refresh()}>
            <RefreshCw className="wsms-h-4 wsms-w-4" />
          </Button>
          <ExportButton
            onExport={handleExport}
            successMessage={__('Exported %d messages successfully')}
          />
        </div>
      </div>

      {/* Stats Cards */}
      <div className="wsms-grid wsms-grid-cols-2 md:wsms-grid-cols-4 wsms-gap-4">
        <Card>
          <CardContent className="wsms-pt-4">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <MessageSquare className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <span className="wsms-text-sm wsms-text-muted-foreground">{__('Total')}</span>
            </div>
            <p className="wsms-text-2xl wsms-font-bold wsms-mt-1">{stats.total}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="wsms-pt-4">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <Clock className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <span className="wsms-text-sm wsms-text-muted-foreground">{__('Today')}</span>
            </div>
            <p className="wsms-text-2xl wsms-font-bold wsms-mt-1">{stats.today}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="wsms-pt-4">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <CheckCircle className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <span className="wsms-text-sm wsms-text-muted-foreground">{__('This Week')}</span>
            </div>
            <p className="wsms-text-2xl wsms-font-bold wsms-mt-1">{stats.week}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="wsms-pt-4">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <MailOpen className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <span className="wsms-text-sm wsms-text-muted-foreground">{__('Unread')}</span>
            </div>
            <p className="wsms-text-2xl wsms-font-bold wsms-mt-1">{stats.unread}</p>
          </CardContent>
        </Card>
      </div>

      {/* Messages Table */}
      <Card>
        <CardContent className="wsms-p-0">
          {/* Filters */}
          <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-px-6 wsms-py-4">
            <Filter className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
            <Select value={filters.filters.action_status} onValueChange={(v) => filters.setFilter('action_status', v)}>
              <SelectTrigger className="wsms-w-[130px] wsms-h-9">
                <SelectValue placeholder={__('Action Status')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{__('All Actions')}</SelectItem>
                <SelectItem value="successful">{__('Successful')}</SelectItem>
                <SelectItem value="failed">{__('Failed')}</SelectItem>
                <SelectItem value="plain">{__('Plain')}</SelectItem>
              </SelectContent>
            </Select>
            <Select value={filters.filters.command_id} onValueChange={(v) => filters.setFilter('command_id', v)}>
              <SelectTrigger className="wsms-w-[140px] wsms-h-9">
                <SelectValue placeholder={__('Command')} />
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
              <SelectTrigger className="wsms-w-[110px] wsms-h-9">
                <SelectValue placeholder={__('Read')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{__('All')}</SelectItem>
                <SelectItem value="unread">{__('Unread')}</SelectItem>
                <SelectItem value="read">{__('Read')}</SelectItem>
              </SelectContent>
            </Select>
            {filters.hasActiveFilters && (
              <Button variant="ghost" size="sm" className="wsms-h-9" onClick={filters.resetFilters}>
                {__('Clear')}
              </Button>
            )}
          </div>

          <DataTable
            columns={columns}
            data={table.data}
            loading={table.isLoading}
            pagination={{
              page: table.pagination.current_page,
              perPage: table.pagination.per_page,
              total: table.pagination.total,
              totalPages: table.pagination.total_pages,
              onPageChange: table.handlePageChange,
            }}
            selection={{
              selected: table.selectedIds,
              onSelect: (id) => table.toggleSelection(id),
              onSelectAll: (ids) => ids.length === 0 ? table.clearSelection() : table.setSelectedIds(ids),
            }}
            rowActions={rowActions}
            bulkActions={bulkActions}
            onSearch={(v) => filters.setFilter('search', v)}
            searchPlaceholder={__('Search messages...')}
            emptyMessage={__('No messages found')}
            emptyIcon={Inbox}
          />
        </CardContent>
      </Card>

      {/* View Message Dialog */}
      <Dialog open={!!viewingMessage} onOpenChange={() => setViewingMessage(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{__('Message from %s').replace('%s', viewingMessage?.sender_number)}</DialogTitle>
            <DialogDescription>
              {__('Received')}: {viewingMessage?.received_at_formatted || viewingMessage?.received_at}
            </DialogDescription>
          </DialogHeader>
          <div className="wsms-px-6 wsms-pb-2">
            <div className="wsms-p-4 wsms-bg-muted wsms-rounded-lg">
              <p className="wsms-whitespace-pre-wrap">{viewingMessage?.text}</p>
            </div>
            {viewingMessage?.command_name && (
              <div className="wsms-mt-4">
                <span className="wsms-text-sm wsms-text-muted-foreground">{__('Matched Command')}: </span>
                <Badge variant="outline">{viewingMessage.command_name}</Badge>
              </div>
            )}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setViewingMessage(null)}>
              {__('Close')}
            </Button>
            <Button onClick={() => {
              setReplyingTo(viewingMessage)
              setViewingMessage(null)
            }}>
              {__('Reply')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Reply Dialog */}
      <Dialog open={!!replyingTo} onOpenChange={() => { setReplyingTo(null); setReplyMessage('') }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>{__('Reply to %s').replace('%s', replyingTo?.sender_number)}</DialogTitle>
            <DialogDescription>
              {__('Send an SMS reply to this number')}
            </DialogDescription>
          </DialogHeader>
          <div className="wsms-px-6 wsms-pb-2 wsms-space-y-4">
            <div className="wsms-p-3 wsms-bg-muted wsms-rounded-lg wsms-text-sm">
              <p className="wsms-text-muted-foreground wsms-mb-1">{__('Original message')}:</p>
              <p>{replyingTo?.text?.substring(0, 100)}{replyingTo?.text?.length > 100 ? '...' : ''}</p>
            </div>
            <Textarea
              placeholder={__('Type your reply...')}
              value={replyMessage}
              onChange={(e) => setReplyMessage(e.target.value)}
              rows={4}
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => { setReplyingTo(null); setReplyMessage('') }}>
              {__('Cancel')}
            </Button>
            <Button onClick={handleReply} disabled={!replyMessage.trim() || isReplying}>
              {isReplying ? __('Sending...') : __('Send Reply')}
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
        title={__('Delete Message?')}
        description={__('Are you sure you want to delete this message from %s? This action cannot be undone.').replace('%s', deleteDialog.item?.sender_number || '')}
      />

      {/* Bulk Delete Confirmation */}
      <DeleteConfirmDialog
        isOpen={showBulkDeleteConfirm}
        onClose={() => setShowBulkDeleteConfirm(false)}
        onConfirm={handleBulkDeleteConfirm}
        title={__('Delete %d Messages?').replace('%d', table.selectedIds.length)}
        description={__('Are you sure you want to delete %d selected messages? This action cannot be undone.').replace('%d', table.selectedIds.length)}
        confirmLabel={__('Delete All')}
      />
    </div>
  )
}
