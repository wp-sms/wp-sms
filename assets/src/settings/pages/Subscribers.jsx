import React, { useState, useEffect, useCallback } from 'react'
import {
  Users,
  UserPlus,
  Trash2,
  Edit,
  Search,
  Download,
  Upload,
  CheckCircle,
  XCircle,
  AlertCircle,
  Loader2,
  MessageSquare,
  FolderOpen,
  Globe,
  Send,
  UserCheck,
  UserX,
} from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { DataTable } from '@/components/ui/data-table'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { QuickAddForm } from '@/components/shared/QuickAddForm'
import { ImportExportDialog } from '@/components/shared/ImportExportDialog'
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
import { subscribersApi } from '@/api/subscribersApi'
import { groupsApi } from '@/api/groupsApi'
import { smsApi } from '@/api/smsApi'
import { cn, formatDate, getWpSettings } from '@/lib/utils'

export default function Subscribers() {
  // Data state
  const [subscribers, setSubscribers] = useState([])
  const [groups, setGroups] = useState([])
  const [pagination, setPagination] = useState({
    total: 0,
    total_pages: 1,
    current_page: 1,
    per_page: 20,
  })
  const [stats, setStats] = useState({ total: 0, active: 0, inactive: 0 })

  // Get countries from settings
  const { countries = [] } = getWpSettings()
  const showActivateCode = window.wpSmsSettings?.newsletter_form_verify || false

  // Filter state
  const [search, setSearch] = useState('')
  const [groupFilter, setGroupFilter] = useState('all')
  const [statusFilter, setStatusFilter] = useState('all')
  const [countryFilter, setCountryFilter] = useState('all')

  // UI state
  const [isLoading, setIsLoading] = useState(true)
  const [initialLoadDone, setInitialLoadDone] = useState(false)
  const [selectedIds, setSelectedIds] = useState([])
  const [editSubscriber, setEditSubscriber] = useState(null)
  const [showImportDialog, setShowImportDialog] = useState(false)
  const [showExportDialog, setShowExportDialog] = useState(false)
  const [notification, setNotification] = useState(null)
  const [isAddingQuick, setIsAddingQuick] = useState(false)

  // Form state for edit dialog
  const [formData, setFormData] = useState({ name: '', mobile: '', group_id: '', status: '1' })
  const [isSaving, setIsSaving] = useState(false)

  // Quick reply state
  const [quickReplyTo, setQuickReplyTo] = useState(null)
  const [quickReplyMessage, setQuickReplyMessage] = useState('')
  const [isSendingReply, setIsSendingReply] = useState(false)

  // Move to group state
  const [showMoveToGroup, setShowMoveToGroup] = useState(false)
  const [moveToGroupId, setMoveToGroupId] = useState('')

  // Fetch groups
  useEffect(() => {
    const fetchGroups = async () => {
      try {
        const result = await groupsApi.getGroupsList()
        setGroups(result)
      } catch (error) {
        console.error('Failed to fetch groups:', error)
      }
    }
    fetchGroups()
  }, [])

  // Fetch subscribers
  const fetchSubscribers = useCallback(async (page = 1) => {
    setIsLoading(true)
    try {
      const result = await subscribersApi.getSubscribers({
        page,
        per_page: pagination.per_page,
        search: search || undefined,
        group_id: groupFilter !== 'all' ? groupFilter : undefined,
        status: statusFilter !== 'all' ? statusFilter : undefined,
        country_code: countryFilter !== 'all' ? countryFilter : undefined,
      })
      setSubscribers(result.items)
      setPagination(result.pagination)
      setStats(result.stats)
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    } finally {
      setIsLoading(false)
      setInitialLoadDone(true)
    }
  }, [search, groupFilter, statusFilter, countryFilter, pagination.per_page])

  // Initial fetch
  useEffect(() => {
    fetchSubscribers()
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  // Handle search with debounce
  useEffect(() => {
    const timer = setTimeout(() => {
      fetchSubscribers(1)
    }, 500)
    return () => clearTimeout(timer)
  }, [search, groupFilter, statusFilter, countryFilter]) // eslint-disable-line react-hooks/exhaustive-deps

  // Handle page change
  const handlePageChange = (page) => {
    fetchSubscribers(page)
  }

  // Handle quick add
  const handleQuickAdd = async (mobile) => {
    setIsAddingQuick(true)
    try {
      await subscribersApi.createSubscriber({
        name: '',
        mobile,
        group_id: groupFilter !== 'all' ? parseInt(groupFilter) : undefined,
        status: '1',
      })
      setNotification({ type: 'success', message: 'Subscriber added successfully' })
      fetchSubscribers(1)
    } catch (error) {
      throw error
    } finally {
      setIsAddingQuick(false)
    }
  }

  // Handle edit
  const handleEdit = (subscriber) => {
    setFormData({
      name: subscriber.name || '',
      mobile: subscriber.mobile || '',
      group_id: subscriber.group_id?.toString() || '',
      status: subscriber.status || '1',
    })
    setEditSubscriber(subscriber)
  }

  // Handle save
  const handleSave = async () => {
    setIsSaving(true)
    try {
      if (editSubscriber) {
        await subscribersApi.updateSubscriber(editSubscriber.id, {
          name: formData.name,
          mobile: formData.mobile,
          group_id: formData.group_id ? parseInt(formData.group_id) : null,
          status: formData.status,
        })
        setNotification({ type: 'success', message: 'Subscriber updated successfully' })
      }
      setEditSubscriber(null)
      fetchSubscribers(pagination.current_page)
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    } finally {
      setIsSaving(false)
    }
  }

  // Handle delete
  const handleDelete = async (id) => {
    try {
      await subscribersApi.deleteSubscriber(id)
      setNotification({ type: 'success', message: 'Subscriber deleted successfully' })
      fetchSubscribers(pagination.current_page)
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    }
  }

  // Handle bulk actions
  const handleBulkAction = async (action, params = {}) => {
    if (selectedIds.length === 0) return

    setIsLoading(true)
    try {
      const result = await subscribersApi.bulkAction(action, selectedIds, params)
      setNotification({
        type: 'success',
        message: `${result.affected} subscriber(s) updated successfully`,
      })
      setSelectedIds([])
      fetchSubscribers(1)
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    } finally {
      setIsLoading(false)
    }
  }

  // Handle import
  const handleImport = async (file) => {
    const result = await subscribersApi.importCsv(file, {
      group_id: groupFilter !== 'all' ? parseInt(groupFilter) : undefined,
      skip_duplicates: true,
    })
    setNotification({
      type: 'success',
      message: `Imported ${result.imported} subscribers, skipped ${result.skipped}`,
    })
    fetchSubscribers(1)
  }

  // Handle export
  const handleExport = async () => {
    const result = await subscribersApi.exportCsv({
      group_id: groupFilter !== 'all' ? groupFilter : undefined,
      status: statusFilter !== 'all' ? statusFilter : undefined,
    })

    const csvContent = result.data.map((row) => row.join(',')).join('\n')
    const blob = new Blob([csvContent], { type: 'text/csv' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = result.filename
    document.body.appendChild(a)
    a.click()
    document.body.removeChild(a)
    URL.revokeObjectURL(url)
  }

  // Handle quick reply
  const handleQuickReply = async () => {
    if (!quickReplyTo || !quickReplyMessage.trim()) return

    setIsSendingReply(true)
    try {
      await smsApi.send({
        message: quickReplyMessage,
        recipients: { groups: [], roles: [], numbers: [quickReplyTo.mobile] },
      })
      setNotification({ type: 'success', message: `Message sent to ${quickReplyTo.mobile}` })
      setQuickReplyTo(null)
      setQuickReplyMessage('')
    } catch (error) {
      setNotification({ type: 'error', message: error.message || 'Failed to send message' })
    } finally {
      setIsSendingReply(false)
    }
  }

  // Handle move to group
  const handleMoveToGroup = async () => {
    if (selectedIds.length === 0 || !moveToGroupId) return

    setIsLoading(true)
    try {
      const result = await subscribersApi.bulkAction('move_to_group', selectedIds, {
        group_id: parseInt(moveToGroupId),
      })
      setNotification({
        type: 'success',
        message: `${result.affected} subscriber(s) moved to group`,
      })
      setSelectedIds([])
      setShowMoveToGroup(false)
      setMoveToGroupId('')
      fetchSubscribers(1)
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    } finally {
      setIsLoading(false)
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
      id: 'name',
      accessorKey: 'name',
      header: 'Name',
      sortable: true,
      cell: ({ row }) => (
        <span className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">
          {row.name || '—'}
        </span>
      ),
    },
    {
      id: 'mobile',
      accessorKey: 'mobile',
      header: 'Mobile',
      sortable: true,
      cell: ({ row }) => (
        <span className="wsms-text-[13px] wsms-font-mono wsms-text-foreground">
          {row.mobile}
        </span>
      ),
    },
    {
      id: 'group',
      accessorKey: 'group_name',
      header: 'Group',
      cell: ({ row }) => (
        <span className="wsms-text-[12px] wsms-text-muted-foreground">
          {row.group_name || '—'}
        </span>
      ),
    },
    {
      id: 'custom_fields',
      accessorKey: 'custom_fields',
      header: 'Custom Fields',
      cell: ({ row }) => {
        if (!row.custom_fields || Object.keys(row.custom_fields).length === 0) {
          return <span className="wsms-text-[12px] wsms-text-muted-foreground">—</span>
        }
        const entries = Object.entries(row.custom_fields)
        return (
          <div className="wsms-flex wsms-flex-wrap wsms-gap-1">
            {entries.slice(0, 2).map(([key, value]) => (
              <span
                key={key}
                className="wsms-inline-flex wsms-items-center wsms-px-2 wsms-py-0.5 wsms-rounded wsms-text-[10px] wsms-bg-muted wsms-text-muted-foreground"
                title={`${key}: ${value}`}
              >
                {key}: {String(value).substring(0, 15)}{String(value).length > 15 ? '...' : ''}
              </span>
            ))}
            {entries.length > 2 && (
              <span className="wsms-text-[10px] wsms-text-muted-foreground">
                +{entries.length - 2} more
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
        <StatusBadge variant={row.status === '1' ? 'active' : 'inactive'}>
          {row.status === '1' ? 'Active' : 'Inactive'}
        </StatusBadge>
      ),
    },
    ...(showActivateCode
      ? [
          {
            id: 'activate_key',
            accessorKey: 'activate_key',
            header: 'Activate Code',
            cell: ({ row }) => (
              <span className="wsms-text-[12px] wsms-font-mono wsms-text-muted-foreground">
                {row.activate_key || '—'}
              </span>
            ),
          },
        ]
      : []),
    {
      id: 'date',
      accessorKey: 'date',
      header: 'Subscribed',
      sortable: true,
      cell: ({ row }) => (
        <span className="wsms-text-[12px] wsms-text-muted-foreground">
          {formatDate(row.date)}
        </span>
      ),
    },
  ]

  // Row actions
  const rowActions = [
    {
      label: 'Quick Reply',
      icon: MessageSquare,
      onClick: (row) => setQuickReplyTo(row),
    },
    {
      label: 'Edit',
      icon: Edit,
      onClick: handleEdit,
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
      label: 'Move to Group',
      icon: FolderOpen,
      onClick: () => setShowMoveToGroup(true),
    },
    {
      label: 'Activate Selected',
      icon: CheckCircle,
      onClick: () => handleBulkAction('activate'),
    },
    {
      label: 'Deactivate Selected',
      icon: XCircle,
      onClick: () => handleBulkAction('deactivate'),
    },
    {
      label: 'Delete Selected',
      icon: Trash2,
      onClick: () => handleBulkAction('delete'),
      variant: 'destructive',
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
  const hasNoSubscribers = subscribers.length === 0 && !search && groupFilter === 'all' && statusFilter === 'all'

  if (hasNoSubscribers) {
    return (
      <div className="wsms-space-y-6 wsms-stagger-children">
        <Card className="wsms-border-dashed">
          <CardContent className="wsms-py-16">
            <div className="wsms-flex wsms-flex-col wsms-items-center wsms-text-center wsms-max-w-md wsms-mx-auto">
              <div className="wsms-flex wsms-h-16 wsms-w-16 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-primary/10 wsms-mb-6">
                <Users className="wsms-h-8 wsms-w-8 wsms-text-primary" strokeWidth={1.5} />
              </div>
              <h3 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-2">
                No subscribers yet
              </h3>
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-6">
                Start building your SMS audience. Add subscribers manually, import from CSV, or let users subscribe through your website forms.
              </p>

              {/* Quick Add */}
              <div className="wsms-w-full wsms-max-w-sm wsms-mb-6">
                <QuickAddForm
                  placeholder="Enter phone number..."
                  buttonLabel="Add Subscriber"
                  onSubmit={handleQuickAdd}
                  isLoading={isAddingQuick}
                />
              </div>

              {/* Import option */}
              <Button variant="outline" onClick={() => setShowImportDialog(true)}>
                <Upload className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                Import from CSV
              </Button>
            </div>
          </CardContent>
        </Card>

        {/* Import Dialog */}
        <ImportExportDialog
          open={showImportDialog}
          onOpenChange={setShowImportDialog}
          mode="import"
          title="Import Subscribers"
          description="Upload a CSV file with subscriber data"
          onImport={handleImport}
          importFields={[
            { name: 'name', label: 'Name', required: false },
            { name: 'mobile', label: 'Mobile', required: true },
            { name: 'group_id', label: 'Group ID', required: false },
          ]}
        />
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

      {/* Stats & Actions Header */}
      <div className="wsms-flex wsms-flex-wrap wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-5 wsms-py-4 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
        <div className="wsms-flex wsms-items-center wsms-gap-6 lg:wsms-gap-8">
          {/* Total */}
          <div className="wsms-flex wsms-items-center wsms-gap-2.5">
            <div className="wsms-flex wsms-h-9 wsms-w-9 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-primary/10">
              <Users className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            </div>
            <div>
              <p className="wsms-text-lg wsms-font-bold wsms-text-foreground wsms-leading-none">{stats.total}</p>
              <p className="wsms-text-[10px] wsms-text-muted-foreground wsms-mt-0.5">Total</p>
            </div>
          </div>

          <div className="wsms-w-px wsms-h-8 wsms-bg-border wsms-hidden sm:wsms-block" />

          {/* Active */}
          <div className="wsms-flex wsms-items-center wsms-gap-2.5">
            <div className="wsms-flex wsms-h-9 wsms-w-9 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-success/10">
              <UserCheck className="wsms-h-4 wsms-w-4 wsms-text-success" />
            </div>
            <div>
              <p className="wsms-text-lg wsms-font-bold wsms-text-success wsms-leading-none">{stats.active}</p>
              <p className="wsms-text-[10px] wsms-text-muted-foreground wsms-mt-0.5">Active</p>
            </div>
          </div>

          <div className="wsms-w-px wsms-h-8 wsms-bg-border wsms-hidden sm:wsms-block" />

          {/* Inactive */}
          <div className="wsms-flex wsms-items-center wsms-gap-2.5">
            <div className="wsms-flex wsms-h-9 wsms-w-9 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-muted">
              <UserX className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
            </div>
            <div>
              <p className="wsms-text-lg wsms-font-bold wsms-text-muted-foreground wsms-leading-none">{stats.inactive}</p>
              <p className="wsms-text-[10px] wsms-text-muted-foreground wsms-mt-0.5">Inactive</p>
            </div>
          </div>
        </div>

        {/* Import/Export */}
        <div className="wsms-flex wsms-items-center wsms-gap-2">
          <Button variant="outline" size="sm" onClick={() => setShowImportDialog(true)}>
            <Upload className="wsms-h-4 wsms-w-4 wsms-mr-2" />
            Import
          </Button>
          <Button variant="outline" size="sm" onClick={handleExport}>
            <Download className="wsms-h-4 wsms-w-4 wsms-mr-2" />
            Export
          </Button>
        </div>
      </div>

      {/* Toolbar: Search + Filters + Add */}
      <Card>
        <CardContent className="wsms-py-3">
          <div className="wsms-flex wsms-flex-col wsms-gap-3">
            {/* Main toolbar row */}
            <div className="wsms-flex wsms-flex-wrap wsms-items-center wsms-gap-3">
              {/* Search */}
              <div className="wsms-relative wsms-flex-1 wsms-min-w-[200px]">
                <Search className="wsms-absolute wsms-left-3 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
                <Input
                  type="text"
                  value={search}
                  onChange={(e) => setSearch(e.target.value)}
                  placeholder="Search subscribers..."
                  className="wsms-pl-9 wsms-w-full"
                />
              </div>

              {/* Filters */}
              <Select value={groupFilter} onValueChange={setGroupFilter}>
                <SelectTrigger className="wsms-w-[140px]">
                  <SelectValue placeholder="All Groups" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Groups</SelectItem>
                  {groups.map((group) => (
                    <SelectItem key={group.id} value={group.id.toString()}>
                      {group.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              <Select value={statusFilter} onValueChange={setStatusFilter}>
                <SelectTrigger className="wsms-w-[120px]">
                  <SelectValue placeholder="Status" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">All Status</SelectItem>
                  <SelectItem value="active">Active</SelectItem>
                  <SelectItem value="inactive">Inactive</SelectItem>
                </SelectContent>
              </Select>

              {countries.length > 0 && (
                <Select value={countryFilter} onValueChange={setCountryFilter}>
                  <SelectTrigger className="wsms-w-[150px]">
                    <Globe className="wsms-h-4 wsms-w-4 wsms-mr-1.5 wsms-text-muted-foreground wsms-shrink-0" />
                    <SelectValue placeholder="Country" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">All Countries</SelectItem>
                    {countries
                      .filter((country, index, self) =>
                        index === self.findIndex((c) => c.code === country.code)
                      )
                      .map((country) => (
                        <SelectItem key={country.code} value={country.code}>
                          {country.name}
                        </SelectItem>
                      ))}
                  </SelectContent>
                </Select>
              )}

              {/* Divider */}
              <div className="wsms-w-px wsms-h-6 wsms-bg-border wsms-hidden lg:wsms-block" />

              {/* Quick Add inline */}
              <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-flex-1 lg:wsms-flex-none lg:wsms-w-auto">
                <Input
                  type="text"
                  placeholder="+1234567890"
                  className="wsms-w-full lg:wsms-w-[160px] wsms-font-mono wsms-text-[13px]"
                  id="quick-add-input"
                  onKeyDown={(e) => {
                    if (e.key === 'Enter' && e.target.value.trim()) {
                      handleQuickAdd(e.target.value.trim())
                      e.target.value = ''
                    }
                  }}
                />
                <Button
                  size="sm"
                  disabled={isAddingQuick}
                  onClick={() => {
                    const input = document.getElementById('quick-add-input')
                    if (input?.value?.trim()) {
                      handleQuickAdd(input.value.trim())
                      input.value = ''
                    }
                  }}
                  className="wsms-shrink-0"
                >
                  {isAddingQuick ? (
                    <Loader2 className="wsms-h-4 wsms-w-4 wsms-animate-spin" />
                  ) : (
                    <>
                      <UserPlus className="wsms-h-4 wsms-w-4 lg:wsms-mr-1.5" />
                      <span className="wsms-hidden lg:wsms-inline">Add</span>
                    </>
                  )}
                </Button>
              </div>
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Data Table */}
      <Card>
        <CardContent className="wsms-p-0">
          <DataTable
            columns={columns}
            data={subscribers}
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
                setSelectedIds(checked ? subscribers.map((s) => s.id) : [])
              },
            }}
            rowActions={rowActions}
            bulkActions={bulkActions}
            emptyMessage="No subscribers match your filters"
            emptyIcon={Users}
          />
        </CardContent>
      </Card>

      {/* Edit Dialog */}
      <Dialog open={!!editSubscriber} onOpenChange={() => setEditSubscriber(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Edit className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              Edit Subscriber
            </DialogTitle>
            <DialogDescription>Update subscriber information</DialogDescription>
          </DialogHeader>
          <DialogBody>
            <div className="wsms-space-y-4">
              <div className="wsms-space-y-2">
                <label className="wsms-text-[12px] wsms-font-medium">Name</label>
                <Input
                  value={formData.name}
                  onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                  placeholder="Subscriber name"
                />
              </div>
              <div className="wsms-space-y-2">
                <label className="wsms-text-[12px] wsms-font-medium">Mobile Number</label>
                <Input
                  value={formData.mobile}
                  onChange={(e) => setFormData({ ...formData, mobile: e.target.value })}
                  placeholder="+1234567890"
                  className="wsms-font-mono"
                />
              </div>
              <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4">
                <div className="wsms-space-y-2">
                  <label className="wsms-text-[12px] wsms-font-medium">Group</label>
                  <Select
                    value={formData.group_id || 'none'}
                    onValueChange={(v) => setFormData({ ...formData, group_id: v === 'none' ? '' : v })}
                  >
                    <SelectTrigger>
                      <SelectValue placeholder="Select group" />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="none">No Group</SelectItem>
                      {groups.map((group) => (
                        <SelectItem key={group.id} value={group.id.toString()}>
                          {group.name}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </div>
                <div className="wsms-space-y-2">
                  <label className="wsms-text-[12px] wsms-font-medium">Status</label>
                  <Select
                    value={formData.status}
                    onValueChange={(v) => setFormData({ ...formData, status: v })}
                  >
                    <SelectTrigger>
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value="1">Active</SelectItem>
                      <SelectItem value="0">Inactive</SelectItem>
                    </SelectContent>
                  </Select>
                </div>
              </div>
            </div>
          </DialogBody>
          <DialogFooter>
            <Button variant="outline" onClick={() => setEditSubscriber(null)}>
              Cancel
            </Button>
            <Button onClick={handleSave} disabled={isSaving}>
              {isSaving ? (
                <>
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" />
                  Saving...
                </>
              ) : (
                'Save Changes'
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Import Dialog */}
      <ImportExportDialog
        open={showImportDialog}
        onOpenChange={setShowImportDialog}
        mode="import"
        title="Import Subscribers"
        description="Upload a CSV file with subscriber data"
        onImport={handleImport}
        importFields={[
          { name: 'name', label: 'Name', required: false },
          { name: 'mobile', label: 'Mobile', required: true },
          { name: 'group_id', label: 'Group ID', required: false },
        ]}
      />

      {/* Quick Reply Dialog */}
      <Dialog
        open={!!quickReplyTo}
        onOpenChange={() => {
          setQuickReplyTo(null)
          setQuickReplyMessage('')
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <MessageSquare className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              Quick Reply
            </DialogTitle>
            <DialogDescription>
              Send a message to {quickReplyTo?.name || quickReplyTo?.mobile}
            </DialogDescription>
          </DialogHeader>
          <DialogBody>
            <div className="wsms-space-y-4">
              <div className="wsms-p-3 wsms-rounded-lg wsms-bg-muted/50 wsms-border wsms-border-border">
                <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-mb-1">Recipient</p>
                <p className="wsms-text-[13px] wsms-font-mono wsms-text-foreground">
                  {quickReplyTo?.mobile}
                </p>
              </div>
              <div className="wsms-space-y-2">
                <label className="wsms-text-[12px] wsms-font-medium">Message</label>
                <textarea
                  value={quickReplyMessage}
                  onChange={(e) => setQuickReplyMessage(e.target.value)}
                  placeholder="Type your message..."
                  rows={4}
                  className="wsms-w-full wsms-px-3 wsms-py-2 wsms-text-[13px] wsms-rounded-md wsms-border wsms-border-input wsms-bg-background wsms-resize-none focus:wsms-outline-none focus:wsms-ring-2 focus:wsms-ring-ring focus:wsms-ring-offset-2"
                />
                <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-text-right">
                  {quickReplyMessage.length} characters
                </p>
              </div>
            </div>
          </DialogBody>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => {
                setQuickReplyTo(null)
                setQuickReplyMessage('')
              }}
            >
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
                  Send Message
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Move to Group Dialog */}
      <Dialog
        open={showMoveToGroup}
        onOpenChange={(open) => {
          setShowMoveToGroup(open)
          if (!open) setMoveToGroupId('')
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <FolderOpen className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              Move to Group
            </DialogTitle>
            <DialogDescription>
              Move {selectedIds.length} selected subscriber(s) to a group
            </DialogDescription>
          </DialogHeader>
          <DialogBody>
            <div className="wsms-space-y-4">
              <div className="wsms-space-y-2">
                <label className="wsms-text-[12px] wsms-font-medium">Select Group</label>
                <Select value={moveToGroupId} onValueChange={setMoveToGroupId}>
                  <SelectTrigger>
                    <SelectValue placeholder="Choose a group..." />
                  </SelectTrigger>
                  <SelectContent>
                    {groups.map((group) => (
                      <SelectItem key={group.id} value={group.id.toString()}>
                        {group.name}
                      </SelectItem>
                    ))}
                  </SelectContent>
                </Select>
              </div>
              <div className="wsms-p-3 wsms-rounded-lg wsms-bg-muted/50 wsms-border wsms-border-border">
                <p className="wsms-text-[12px] wsms-text-muted-foreground">
                  This will move {selectedIds.length} subscriber(s) to the selected group.
                </p>
              </div>
            </div>
          </DialogBody>
          <DialogFooter>
            <Button
              variant="outline"
              onClick={() => {
                setShowMoveToGroup(false)
                setMoveToGroupId('')
              }}
            >
              Cancel
            </Button>
            <Button onClick={handleMoveToGroup} disabled={isLoading || !moveToGroupId}>
              {isLoading ? (
                <>
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" />
                  Moving...
                </>
              ) : (
                'Move to Group'
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
