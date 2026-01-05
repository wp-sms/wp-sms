import React, { useState, useEffect, useCallback } from 'react'
import { Inbox, AlertCircle, ExternalLink, Search, Trash2, Eye, MessageSquare, RefreshCw, CheckCircle, Clock, MailOpen, Filter, Download, XCircle, MinusCircle } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { useSettings } from '@/context/SettingsContext'
import { useToast } from '@/components/ui/toaster'
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
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
} from '@/components/ui/alert-dialog'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { Checkbox } from '@/components/ui/checkbox'

export default function TwoWayInbox() {
  const { isAddonActive } = useSettings()
  const { toast } = useToast()

  // Check if Two-Way add-on is active
  const hasTwoWay = isAddonActive('two-way')

  // State
  const [messages, setMessages] = useState([])
  const [stats, setStats] = useState({ total: 0, today: 0, week: 0, unread: 0 })
  const [isLoading, setIsLoading] = useState(true)
  const [search, setSearch] = useState('')
  const [statusFilter, setStatusFilter] = useState('all')
  const [actionStatusFilter, setActionStatusFilter] = useState('all')
  const [commandFilter, setCommandFilter] = useState('all')
  const [commands, setCommands] = useState([])
  const [page, setPage] = useState(1)
  const [totalPages, setTotalPages] = useState(1)
  const [selectedMessages, setSelectedMessages] = useState([])
  const [viewingMessage, setViewingMessage] = useState(null)
  const [replyingTo, setReplyingTo] = useState(null)
  const [replyMessage, setReplyMessage] = useState('')
  const [isReplying, setIsReplying] = useState(false)
  const [deleteConfirm, setDeleteConfirm] = useState(null)
  const [bulkDeleteConfirm, setBulkDeleteConfirm] = useState(false)
  const [isExporting, setIsExporting] = useState(false)

  // Fetch commands for filter
  const fetchCommands = useCallback(async () => {
    try {
      const response = await inboxApi.getCommands()
      if (response.success) {
        setCommands(Array.isArray(response.data) ? response.data : [])
      }
    } catch (error) {
      console.error('Failed to fetch commands:', error)
    }
  }, [])

  // Fetch messages
  const fetchMessages = useCallback(async () => {
    try {
      setIsLoading(true)
      const params = {
        page,
        per_page: 20,
      }

      if (search) params.search = search
      if (statusFilter && statusFilter !== 'all') params.status = statusFilter
      if (actionStatusFilter && actionStatusFilter !== 'all') params.action_status = actionStatusFilter
      if (commandFilter && commandFilter !== 'all') params.command_id = commandFilter

      const response = await inboxApi.getMessages(params)

      if (response.success) {
        setMessages(Array.isArray(response.data?.messages) ? response.data.messages : [])
        setTotalPages(response.data?.total_pages || 1)
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: error.message || 'Failed to load messages',
        variant: 'destructive',
      })
    } finally {
      setIsLoading(false)
    }
  }, [page, search, statusFilter, actionStatusFilter, commandFilter, toast])

  // Fetch stats
  const fetchStats = useCallback(async () => {
    try {
      const response = await inboxApi.getStats()
      if (response.success) {
        setStats(response.data || { total: 0, today: 0, week: 0, unread: 0 })
      }
    } catch (error) {
      console.error('Failed to fetch stats:', error)
    }
  }, [])

  // Load data on mount and when filters change
  useEffect(() => {
    if (hasTwoWay) {
      fetchMessages()
      fetchStats()
      fetchCommands()
    }
  }, [hasTwoWay, fetchMessages, fetchStats, fetchCommands])

  // Reset page when filters change
  useEffect(() => {
    setPage(1)
  }, [statusFilter, actionStatusFilter, commandFilter])

  // Handle search
  const handleSearch = (e) => {
    e.preventDefault()
    setPage(1)
    fetchMessages()
  }

  // Handle reply
  const handleReply = async () => {
    if (!replyingTo || !replyMessage.trim()) return

    try {
      setIsReplying(true)
      const response = await inboxApi.replyToMessage(replyingTo.id, replyMessage.trim())

      if (response.success) {
        toast({
          title: 'Success',
          description: 'Reply sent successfully',
        })
        setReplyingTo(null)
        setReplyMessage('')
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: error.message || 'Failed to send reply',
        variant: 'destructive',
      })
    } finally {
      setIsReplying(false)
    }
  }

  // Handle delete
  const handleDelete = async (id) => {
    try {
      const response = await inboxApi.deleteMessage(id)
      if (response.success) {
        toast({
          title: 'Success',
          description: 'Message deleted',
        })
        fetchMessages()
        fetchStats()
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: error.message || 'Failed to delete message',
        variant: 'destructive',
      })
    }
    setDeleteConfirm(null)
  }

  // Handle bulk delete
  const handleBulkDelete = async () => {
    if (selectedMessages.length === 0) return

    try {
      const response = await inboxApi.bulkDelete(selectedMessages)
      if (response.success) {
        toast({
          title: 'Success',
          description: `${selectedMessages.length} messages deleted`,
        })
        setSelectedMessages([])
        fetchMessages()
        fetchStats()
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: error.message || 'Failed to delete messages',
        variant: 'destructive',
      })
    }
    setBulkDeleteConfirm(false)
  }

  // Handle export
  const handleExport = async () => {
    try {
      setIsExporting(true)
      const params = {}

      if (search) params.search = search
      if (statusFilter && statusFilter !== 'all') params.status = statusFilter
      if (actionStatusFilter && actionStatusFilter !== 'all') params.action_status = actionStatusFilter
      if (commandFilter && commandFilter !== 'all') params.command_id = commandFilter

      const response = await inboxApi.exportMessages(params)

      if (response.success && response.data?.csv) {
        // Create a Blob and download
        const blob = new Blob([response.data.csv], { type: 'text/csv;charset=utf-8;' })
        const link = document.createElement('a')
        const url = URL.createObjectURL(blob)
        link.setAttribute('href', url)
        link.setAttribute('download', response.data.filename || 'inbox-export.csv')
        link.style.visibility = 'hidden'
        document.body.appendChild(link)
        link.click()
        document.body.removeChild(link)

        toast({
          title: 'Success',
          description: `Exported ${response.data.total} messages`,
        })
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: error.message || 'Failed to export messages',
        variant: 'destructive',
      })
    } finally {
      setIsExporting(false)
    }
  }

  // Handle mark as read
  const handleMarkAsRead = async (id) => {
    try {
      await inboxApi.markAsRead(id)
      fetchMessages()
      fetchStats()
    } catch (error) {
      console.error('Failed to mark as read:', error)
    }
  }

  // Toggle message selection
  const toggleMessageSelection = (id) => {
    setSelectedMessages((prev) =>
      prev.includes(id) ? prev.filter((i) => i !== id) : [...prev, id]
    )
  }

  // Toggle all selection
  const toggleAllSelection = () => {
    if (selectedMessages.length === messages.length) {
      setSelectedMessages([])
    } else {
      setSelectedMessages(messages.map((m) => m.id))
    }
  }

  // Show placeholder if Two-Way add-on is not active
  if (!hasTwoWay) {
    return (
      <div className="wsms-space-y-6">
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Inbox className="wsms-h-5 wsms-w-5" />
              Two-Way SMS Inbox
            </CardTitle>
            <CardDescription>
              Receive and manage incoming SMS messages
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-6 wsms-text-center">
              <AlertCircle className="wsms-mx-auto wsms-h-10 wsms-w-10 wsms-text-muted-foreground wsms-mb-3" />
              <h3 className="wsms-font-medium wsms-mb-2">Two-Way SMS Add-on Required</h3>
              <p className="wsms-text-sm wsms-text-muted-foreground wsms-mb-4">
                Install and activate the WP SMS Two-Way add-on to receive incoming messages.
              </p>
              <Button variant="outline" asChild>
                <a
                  href="https://wp-sms-pro.com/product/wp-sms-two-way/"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Learn More
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
      {/* Page Header */}
      <div className="wsms-mb-6">
        <h1 className="wsms-text-2xl wsms-font-bold wsms-flex wsms-items-center wsms-gap-2">
          <Inbox className="wsms-h-6 wsms-w-6" />
          Inbox
        </h1>
        <p className="wsms-text-muted-foreground wsms-mt-1">
          View and manage incoming SMS messages from your subscribers.
        </p>
      </div>

      {/* Stats Cards */}
      <div className="wsms-grid wsms-grid-cols-2 md:wsms-grid-cols-4 wsms-gap-4">
        <Card>
          <CardContent className="wsms-pt-4">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <MessageSquare className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <span className="wsms-text-sm wsms-text-muted-foreground">Total</span>
            </div>
            <p className="wsms-text-2xl wsms-font-bold wsms-mt-1">{stats.total}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="wsms-pt-4">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <Clock className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <span className="wsms-text-sm wsms-text-muted-foreground">Today</span>
            </div>
            <p className="wsms-text-2xl wsms-font-bold wsms-mt-1">{stats.today}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="wsms-pt-4">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <CheckCircle className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <span className="wsms-text-sm wsms-text-muted-foreground">This Week</span>
            </div>
            <p className="wsms-text-2xl wsms-font-bold wsms-mt-1">{stats.week}</p>
          </CardContent>
        </Card>
        <Card>
          <CardContent className="wsms-pt-4">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <MailOpen className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <span className="wsms-text-sm wsms-text-muted-foreground">Unread</span>
            </div>
            <p className="wsms-text-2xl wsms-font-bold wsms-mt-1">{stats.unread}</p>
          </CardContent>
        </Card>
      </div>

      {/* Messages Table */}
      <Card>
        <CardHeader>
          <div className="wsms-flex wsms-flex-col wsms-gap-4">
            <div className="wsms-flex wsms-flex-col sm:wsms-flex-row wsms-items-start sm:wsms-items-center wsms-justify-between wsms-gap-4">
              <CardTitle>Messages</CardTitle>
              <div className="wsms-flex wsms-items-center wsms-gap-2">
                <form onSubmit={handleSearch} className="wsms-flex wsms-items-center wsms-gap-2">
                  <Input
                    placeholder="Search messages..."
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    className="wsms-w-48"
                  />
                  <Button type="submit" variant="outline" size="icon">
                    <Search className="wsms-h-4 wsms-w-4" />
                  </Button>
                </form>
                <Button
                  variant="outline"
                  size="icon"
                  onClick={() => {
                    fetchMessages()
                    fetchStats()
                  }}
                >
                  <RefreshCw className="wsms-h-4 wsms-w-4" />
                </Button>
                <Button
                  variant="outline"
                  onClick={handleExport}
                  disabled={isExporting}
                >
                  <Download className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                  {isExporting ? 'Exporting...' : 'Export'}
                </Button>
              </div>
            </div>

            {/* Filters */}
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <Filter className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
              <Select value={actionStatusFilter} onValueChange={setActionStatusFilter}>
                <SelectTrigger className="wsms-w-[130px] wsms-h-9">
                  <SelectValue placeholder="Action Status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Actions</SelectItem>
                  <SelectItem value="successful">Successful</SelectItem>
                  <SelectItem value="failed">Failed</SelectItem>
                  <SelectItem value="plain">Plain</SelectItem>
                </SelectContent>
              </Select>
              <Select value={commandFilter} onValueChange={setCommandFilter}>
                <SelectTrigger className="wsms-w-[140px] wsms-h-9">
                  <SelectValue placeholder="Command" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Commands</SelectItem>
                  {commands.map((cmd) => (
                    <SelectItem key={cmd.id} value={String(cmd.id)}>
                      {cmd.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <Select value={statusFilter} onValueChange={setStatusFilter}>
                <SelectTrigger className="wsms-w-[110px] wsms-h-9">
                  <SelectValue placeholder="Read" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All</SelectItem>
                  <SelectItem value="unread">Unread</SelectItem>
                  <SelectItem value="read">Read</SelectItem>
                </SelectContent>
              </Select>
              {(statusFilter !== 'all' || actionStatusFilter !== 'all' || commandFilter !== 'all') && (
                <Button
                  variant="ghost"
                  size="sm"
                  className="wsms-h-9"
                  onClick={() => {
                    setStatusFilter('all')
                    setActionStatusFilter('all')
                    setCommandFilter('all')
                  }}
                >
                  Clear
                </Button>
              )}
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {selectedMessages.length > 0 && (
            <div className="wsms-mb-4 wsms-flex wsms-items-center wsms-gap-2">
              <span className="wsms-text-sm wsms-text-muted-foreground">
                {selectedMessages.length} selected
              </span>
              <Button
                variant="destructive"
                size="sm"
                onClick={() => setBulkDeleteConfirm(true)}
              >
                <Trash2 className="wsms-h-4 wsms-w-4 wsms-mr-1" />
                Delete Selected
              </Button>
            </div>
          )}

          {isLoading ? (
            <div className="wsms-text-center wsms-py-8 wsms-text-muted-foreground">
              Loading messages...
            </div>
          ) : messages.length === 0 ? (
            <div className="wsms-text-center wsms-py-8 wsms-text-muted-foreground">
              No messages found
            </div>
          ) : (
            <>
              <div className="wsms-overflow-x-auto">
                <table className="wsms-w-full">
                  <thead>
                    <tr className="wsms-border-b">
                      <th className="wsms-p-2 wsms-text-left wsms-w-8">
                        <Checkbox
                          checked={selectedMessages.length === messages.length}
                          onCheckedChange={toggleAllSelection}
                        />
                      </th>
                      <th className="wsms-p-2 wsms-text-left wsms-font-medium">Sender</th>
                      <th className="wsms-p-2 wsms-text-left wsms-font-medium">Message</th>
                      <th className="wsms-p-2 wsms-text-left wsms-font-medium">Command</th>
                      <th className="wsms-p-2 wsms-text-left wsms-font-medium">Action Status</th>
                      <th className="wsms-p-2 wsms-text-left wsms-font-medium">Date</th>
                      <th className="wsms-p-2 wsms-text-left wsms-font-medium">Read</th>
                      <th className="wsms-p-2 wsms-text-right wsms-font-medium">Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    {messages.map((message) => (
                      <tr
                        key={message.id}
                        className={`wsms-border-b hover:wsms-bg-muted/50 ${!message.is_read ? 'wsms-bg-primary/5' : ''}`}
                      >
                        <td className="wsms-p-2">
                          <Checkbox
                            checked={selectedMessages.includes(message.id)}
                            onCheckedChange={() => toggleMessageSelection(message.id)}
                          />
                        </td>
                        <td className="wsms-p-2 wsms-font-mono wsms-text-sm">
                          {message.sender_number}
                        </td>
                        <td className="wsms-p-2 wsms-max-w-xs wsms-truncate">
                          {message.text?.substring(0, 50)}{message.text?.length > 50 ? '...' : ''}
                        </td>
                        <td className="wsms-p-2 wsms-text-sm">
                          {message.command_name ? (
                            <Badge variant="outline">{message.command_name}</Badge>
                          ) : (
                            <span className="wsms-text-muted-foreground">—</span>
                          )}
                        </td>
                        <td className="wsms-p-2">
                          {message.action_status === 'successful' && (
                            <Badge variant="outline" className="wsms-border-green-500 wsms-text-green-600">
                              <CheckCircle className="wsms-h-3 wsms-w-3 wsms-mr-1" />
                              Successful
                            </Badge>
                          )}
                          {message.action_status === 'failed' && (
                            <Badge variant="outline" className="wsms-border-red-500 wsms-text-red-600">
                              <XCircle className="wsms-h-3 wsms-w-3 wsms-mr-1" />
                              Failed
                            </Badge>
                          )}
                          {message.action_status === 'plain' && (
                            <Badge variant="outline" className="wsms-border-gray-400 wsms-text-gray-500">
                              <MinusCircle className="wsms-h-3 wsms-w-3 wsms-mr-1" />
                              Plain
                            </Badge>
                          )}
                          {!message.action_status && (
                            <span className="wsms-text-muted-foreground">—</span>
                          )}
                        </td>
                        <td className="wsms-p-2 wsms-text-sm wsms-text-muted-foreground">
                          {message.received_at_formatted || message.received_at}
                        </td>
                        <td className="wsms-p-2">
                          {message.is_read ? (
                            <Badge variant="outline">Read</Badge>
                          ) : (
                            <Badge>New</Badge>
                          )}
                        </td>
                        <td className="wsms-p-2 wsms-text-right">
                          <div className="wsms-flex wsms-items-center wsms-justify-end wsms-gap-1">
                            <Button
                              variant="ghost"
                              size="icon"
                              onClick={() => {
                                setViewingMessage(message)
                                if (!message.is_read) {
                                  handleMarkAsRead(message.id)
                                }
                              }}
                              title="View"
                            >
                              <Eye className="wsms-h-4 wsms-w-4" />
                            </Button>
                            <Button
                              variant="ghost"
                              size="icon"
                              onClick={() => setReplyingTo(message)}
                              title="Reply"
                            >
                              <MessageSquare className="wsms-h-4 wsms-w-4" />
                            </Button>
                            <Button
                              variant="ghost"
                              size="icon"
                              onClick={() => setDeleteConfirm(message)}
                              title="Delete"
                            >
                              <Trash2 className="wsms-h-4 wsms-w-4" />
                            </Button>
                          </div>
                        </td>
                      </tr>
                    ))}
                  </tbody>
                </table>
              </div>

              {/* Pagination */}
              {totalPages > 1 && (
                <div className="wsms-flex wsms-items-center wsms-justify-center wsms-gap-2 wsms-mt-4">
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={page <= 1}
                    onClick={() => setPage((p) => p - 1)}
                  >
                    Previous
                  </Button>
                  <span className="wsms-text-sm wsms-text-muted-foreground">
                    Page {page} of {totalPages}
                  </span>
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={page >= totalPages}
                    onClick={() => setPage((p) => p + 1)}
                  >
                    Next
                  </Button>
                </div>
              )}
            </>
          )}
        </CardContent>
      </Card>

      {/* View Message Dialog */}
      <Dialog open={!!viewingMessage} onOpenChange={() => setViewingMessage(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Message from {viewingMessage?.sender_number}</DialogTitle>
            <DialogDescription>
              Received: {viewingMessage?.received_at_formatted || viewingMessage?.received_at}
            </DialogDescription>
          </DialogHeader>
          <div className="wsms-px-6 wsms-pb-2">
            <div className="wsms-p-4 wsms-bg-muted wsms-rounded-lg">
              <p className="wsms-whitespace-pre-wrap">{viewingMessage?.text}</p>
            </div>
            {viewingMessage?.command_name && (
              <div className="wsms-mt-4">
                <span className="wsms-text-sm wsms-text-muted-foreground">Matched Command: </span>
                <Badge variant="outline">{viewingMessage.command_name}</Badge>
              </div>
            )}
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => setViewingMessage(null)}>
              Close
            </Button>
            <Button onClick={() => {
              setReplyingTo(viewingMessage)
              setViewingMessage(null)
            }}>
              Reply
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Reply Dialog */}
      <Dialog open={!!replyingTo} onOpenChange={() => {
        setReplyingTo(null)
        setReplyMessage('')
      }}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Reply to {replyingTo?.sender_number}</DialogTitle>
            <DialogDescription>
              Send an SMS reply to this number
            </DialogDescription>
          </DialogHeader>
          <div className="wsms-px-6 wsms-pb-2 wsms-space-y-4">
            <div className="wsms-p-3 wsms-bg-muted wsms-rounded-lg wsms-text-sm">
              <p className="wsms-text-muted-foreground wsms-mb-1">Original message:</p>
              <p>{replyingTo?.text?.substring(0, 100)}{replyingTo?.text?.length > 100 ? '...' : ''}</p>
            </div>
            <Textarea
              placeholder="Type your reply..."
              value={replyMessage}
              onChange={(e) => setReplyMessage(e.target.value)}
              rows={4}
            />
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => {
              setReplyingTo(null)
              setReplyMessage('')
            }}>
              Cancel
            </Button>
            <Button
              onClick={handleReply}
              disabled={!replyMessage.trim() || isReplying}
            >
              {isReplying ? 'Sending...' : 'Send Reply'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation */}
      <AlertDialog open={!!deleteConfirm} onOpenChange={() => setDeleteConfirm(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Message?</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete this message from {deleteConfirm?.sender_number}? This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={() => handleDelete(deleteConfirm?.id)}>
              Delete
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>

      {/* Bulk Delete Confirmation */}
      <AlertDialog open={bulkDeleteConfirm} onOpenChange={setBulkDeleteConfirm}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete {selectedMessages.length} Messages?</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete {selectedMessages.length} selected messages? This action cannot be undone.
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>Cancel</AlertDialogCancel>
            <AlertDialogAction onClick={handleBulkDelete}>
              Delete All
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
