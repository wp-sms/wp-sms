import React, { useState, useEffect, useCallback } from 'react'
import {
  Users,
  UserPlus,
  Trash2,
  Edit,
  Search,
  Upload,
  CheckCircle,
  XCircle,
  Loader2,
  MessageSquare,
  FolderOpen,
  Send,
  UserCheck,
  UserX,
} from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { DataTable } from '@/components/ui/data-table'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { ImportExportDialog } from '@/components/shared/ImportExportDialog'
import { ExportButton } from '@/components/shared/ExportButton'
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
import { formatDate, getWpSettings, __, downloadCsv } from '@/lib/utils'
import { useListPage } from '@/hooks/useListPage'
import { useFormDialog } from '@/hooks/useFormDialog'
import { PageLoadingSkeleton } from '@/components/ui/skeleton'
import { useToast } from '@/components/ui/toaster'

export default function Subscribers() {
  const { toast } = useToast()

  // Get countries from settings
  const { countries = [] } = getWpSettings()
  const showActivateCode = window.wpSmsSettings?.newsletter_form_verify || false

  // Groups state (fetched separately)
  const [groups, setGroups] = useState([])

  // Use combined list page hook
  const { filters, table, handleDelete, handleBulkAction } = useListPage({
    fetchFn: subscribersApi.getSubscribers,
    deleteFn: subscribersApi.deleteSubscriber,
    bulkActionFn: subscribersApi.bulkAction,
    initialFilters: { search: '', group_id: 'all', status: 'all', country_code: 'all' },
    messages: {
      deleteSuccess: __('Subscriber deleted successfully'),
      bulkSuccess: __('Subscribers updated successfully'),
    },
  })

  // Edit dialog using useFormDialog
  const editDialog = useFormDialog({
    saveFn: async (id, data) => {
      await subscribersApi.updateSubscriber(id, {
        name: data.name,
        mobile: data.mobile,
        group_id: data.group_id ? parseInt(data.group_id) : null,
        status: data.status,
      })
    },
    initialData: { name: '', mobile: '', group_id: '', status: '1' },
    onSuccess: () => table.refresh(),
    successMessage: __('Subscriber updated successfully'),
  })

  // Fetch groups function
  const fetchGroups = useCallback(async () => {
    try {
      const result = await groupsApi.getGroupsList()
      setGroups(result)
    } catch (error) {
      console.error('Failed to fetch groups:', error)
    }
  }, [])

  // Fetch groups on mount
  useEffect(() => {
    fetchGroups()
  }, [fetchGroups])

  // Listen for groups changes from other pages (e.g., Groups page)
  useEffect(() => {
    const handleGroupsChanged = () => {
      fetchGroups()
    }
    window.addEventListener('wpsms:groups-changed', handleGroupsChanged)
    return () => window.removeEventListener('wpsms:groups-changed', handleGroupsChanged)
  }, [fetchGroups])

  // UI state
  const [showImportDialog, setShowImportDialog] = useState(false)
  const [isImporting, setIsImporting] = useState(false)
  const [isAddingQuick, setIsAddingQuick] = useState(false)

  // Quick add form state
  const [quickAddName, setQuickAddName] = useState('')
  const [quickAddPhone, setQuickAddPhone] = useState('')

  // Quick reply state
  const [quickReplyTo, setQuickReplyTo] = useState(null)
  const [quickReplyMessage, setQuickReplyMessage] = useState('')
  const [isSendingReply, setIsSendingReply] = useState(false)

  // Move to group state
  const [showMoveToGroup, setShowMoveToGroup] = useState(false)
  const [moveToGroupId, setMoveToGroupId] = useState('')

  // Aliases for easier access
  const stats = table.stats || { total: 0, active: 0, inactive: 0 }

  // Handle quick add
  const handleQuickAdd = async (name, mobile) => {
    if (!mobile?.trim()) return
    setIsAddingQuick(true)
    try {
      await subscribersApi.createSubscriber({
        name: name?.trim() || '',
        mobile: mobile.trim(),
        group_id: filters.filters.group_id !== 'all' ? parseInt(filters.filters.group_id) : undefined,
        status: '1',
      })
      toast({ title: __('Subscriber added successfully'), variant: 'success' })
      setQuickAddName('')
      setQuickAddPhone('')
      table.fetch({ page: 1 })
    } catch (error) {
      toast({ title: error.message || __('Failed to add subscriber'), variant: 'destructive' })
    } finally {
      setIsAddingQuick(false)
    }
  }

  // Handle edit - opens dialog with subscriber data
  const handleEdit = useCallback((subscriber) => {
    editDialog.open({
      id: subscriber.id,
      name: subscriber.name || '',
      mobile: subscriber.mobile || '',
      group_id: subscriber.group_id?.toString() || '',
      status: subscriber.status || '1',
    })
  }, [editDialog])

  // Handle import
  const handleImport = async (file) => {
    setIsImporting(true)
    try {
      const result = await subscribersApi.importCsv(file, {
        group_id: filters.filters.group_id !== 'all' ? parseInt(filters.filters.group_id) : undefined,
        skip_duplicates: true,
      })
      toast({
        title: __('Imported %d subscribers, skipped %d').replace('%d', result.imported).replace('%d', result.skipped),
        variant: 'success',
      })
      setShowImportDialog(false)
      table.fetch({ page: 1 })
    } catch (error) {
      toast({
        title: error.message || __('Import failed'),
        variant: 'destructive',
      })
      throw error
    } finally {
      setIsImporting(false)
    }
  }

  // Handle export
  const handleExport = async () => {
    const result = await subscribersApi.exportCsv({
      group_id: filters.filters.group_id !== 'all' ? filters.filters.group_id : undefined,
      status: filters.filters.status !== 'all' ? filters.filters.status : undefined,
    })
    downloadCsv(result.data, result.filename)
    return { count: result.count || result.data.length - 1 }
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
      toast({ title: __('Message sent to %s').replace('%s', quickReplyTo.mobile), variant: 'success' })
      setQuickReplyTo(null)
      setQuickReplyMessage('')
    } catch (error) {
      toast({ title: error.message || __('Failed to send message'), variant: 'destructive' })
    } finally {
      setIsSendingReply(false)
    }
  }

  // Handle move to group
  const handleMoveToGroup = async () => {
    if (table.selectedIds.length === 0 || !moveToGroupId) return

    try {
      const result = await subscribersApi.bulkAction('move_to_group', table.selectedIds, {
        group_id: parseInt(moveToGroupId),
      })
      toast({
        title: __('%d subscriber(s) moved to group').replace('%d', result.affected),
        variant: 'success',
      })
      table.clearSelection()
      setShowMoveToGroup(false)
      setMoveToGroupId('')
      table.fetch({ page: 1 })
    } catch (error) {
      toast({ title: error.message, variant: 'destructive' })
    }
  }

  // Table columns
  const columns = [
    {
      id: 'name',
      accessorKey: 'name',
      header: __('Name'),
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
      header: __('Mobile'),
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
      header: __('Group'),
      cell: ({ row }) => (
        <span className="wsms-text-[12px] wsms-text-muted-foreground">
          {row.group_name || '—'}
        </span>
      ),
    },
    {
      id: 'custom_fields',
      accessorKey: 'custom_fields',
      header: __('Custom Fields'),
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
                +{entries.length - 2} {__('more')}
              </span>
            )}
          </div>
        )
      },
    },
    {
      id: 'status',
      accessorKey: 'status',
      header: __('Status'),
      cell: ({ row }) => (
        <StatusBadge variant={row.status === '1' ? 'active' : 'inactive'}>
          {row.status === '1' ? __('Active') : __('Inactive')}
        </StatusBadge>
      ),
    },
    ...(showActivateCode
      ? [
          {
            id: 'activate_key',
            accessorKey: 'activate_key',
            header: __('Activate Code'),
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
      header: __('Subscribed'),
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
      label: __('Quick Reply'),
      icon: MessageSquare,
      onClick: (row) => setQuickReplyTo(row),
    },
    {
      label: __('Edit'),
      icon: Edit,
      onClick: handleEdit,
    },
    {
      label: __('Delete'),
      icon: Trash2,
      onClick: (row) => handleDelete(row.id),
      variant: 'destructive',
    },
  ]

  // Bulk actions
  const bulkActions = [
    {
      label: __('Move to Group'),
      icon: FolderOpen,
      onClick: () => setShowMoveToGroup(true),
    },
    {
      label: __('Activate Selected'),
      icon: CheckCircle,
      onClick: () => handleBulkAction('activate'),
    },
    {
      label: __('Deactivate Selected'),
      icon: XCircle,
      onClick: () => handleBulkAction('deactivate'),
    },
    {
      label: __('Delete Selected'),
      icon: Trash2,
      onClick: () => handleBulkAction('delete'),
      variant: 'destructive',
    },
  ]

  // Show skeleton during initial load
  if (!table.initialLoadDone) {
    return <PageLoadingSkeleton />
  }

  // Empty state - only show when truly no subscribers and no filters applied
  const hasNoSubscribers = table.data.length === 0 &&
    !filters.filters.search &&
    filters.filters.group_id === 'all' &&
    filters.filters.status === 'all' &&
    filters.filters.country_code === 'all'

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
                {__('No subscribers yet')}
              </h3>
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-6">
                {__('Start building your SMS audience. Add subscribers manually, import from CSV, or let users subscribe through your website forms.')}
              </p>

              {/* Quick Add */}
              <div className="wsms-w-full wsms-max-w-sm wsms-mb-6">
                <div className="wsms-flex wsms-gap-2">
                  <Input
                    type="tel"
                    value={quickAddPhone}
                    onChange={(e) => setQuickAddPhone(e.target.value)}
                    placeholder={__('Phone number')}
                    className="wsms-flex-1"
                    onKeyDown={(e) => {
                      if (e.key === 'Enter' && quickAddPhone.trim()) {
                        handleQuickAdd('', quickAddPhone)
                      }
                    }}
                  />
                  <Button
                    onClick={() => handleQuickAdd('', quickAddPhone)}
                    disabled={isAddingQuick || !quickAddPhone.trim()}
                  >
                    {isAddingQuick ? (
                      <Loader2 className="wsms-h-4 wsms-w-4 wsms-animate-spin" />
                    ) : (
                      __('Add')
                    )}
                  </Button>
                </div>
              </div>

              {/* Import option */}
              <Button variant="outline" onClick={() => setShowImportDialog(true)}>
                <Upload className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                {__('Import from CSV')}
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
          isLoading={isImporting}
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
      {/* Stats & Actions Header */}
      <div className="wsms-px-4 lg:wsms-px-5 wsms-py-4 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
        <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4 lg:wsms-flex lg:wsms-items-center lg:wsms-justify-between lg:wsms-gap-4">
          <div className="wsms-contents lg:wsms-flex lg:wsms-items-center lg:wsms-gap-8">
            {/* Total */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-primary/10">
                <Users className="wsms-h-5 wsms-w-5 wsms-text-primary" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">{stats.total}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Total')}</p>
              </div>
            </div>

            <div className="wsms-hidden lg:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* Active */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-success/10">
                <UserCheck className="wsms-h-5 wsms-w-5 wsms-text-success" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-success">{stats.active}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Active')}</p>
              </div>
            </div>

            <div className="wsms-hidden lg:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* Inactive */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-slate-200 dark:wsms-bg-slate-700">
                <UserX className="wsms-h-5 wsms-w-5 wsms-text-slate-500 dark:wsms-text-slate-400" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-muted-foreground">{stats.inactive}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Inactive')}</p>
              </div>
            </div>
          </div>

          {/* Import/Export */}
          <div className="wsms-col-span-2 lg:wsms-col-span-1 wsms-flex wsms-items-center wsms-justify-end wsms-gap-2 wsms-mt-2 lg:wsms-mt-0">
            <Button variant="outline" onClick={() => setShowImportDialog(true)}>
              <Upload className="wsms-h-4 wsms-w-4 wsms-mr-2" aria-hidden="true" />
              {__('Import')}
            </Button>
            <ExportButton
              onExport={handleExport}
              successMessage={__('Exported %d subscribers successfully')}
            />
          </div>
        </div>
      </div>

      {/* Toolbar */}
      <Card>
        <CardContent className="wsms-p-0">
          {/* Row 1: Search + Filters */}
          <div className="wsms-flex wsms-flex-col wsms-gap-3 xl:wsms-flex-row xl:wsms-items-center xl:wsms-gap-2 wsms-p-3">
            {/* Search */}
            <div className="wsms-relative wsms-w-full xl:wsms-w-[220px] xl:wsms-shrink-0">
              <Search className="wsms-absolute wsms-left-2.5 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-pointer-events-none" aria-hidden="true" />
              <Input
                type="text"
                value={filters.filters.search}
                onChange={(e) => filters.setFilter('search', e.target.value)}
                placeholder={__('Search...')}
                className="wsms-pl-8 wsms-h-9"
              />
            </div>

            {/* Filters */}
            <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 xl:wsms-flex xl:wsms-items-center xl:wsms-gap-2">
              <Select value={filters.filters.group_id} onValueChange={(v) => filters.setFilter('group_id', v)}>
                <SelectTrigger className="wsms-h-9 wsms-w-full xl:wsms-w-[120px] wsms-text-[12px]" aria-label={__('Filter by group')}>
                  <SelectValue placeholder={__('All Groups')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{__('All Groups')}</SelectItem>
                  {groups.map((group) => (
                    <SelectItem key={group.id} value={group.id.toString()}>
                      {group.name}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>

              <Select value={filters.filters.status} onValueChange={(v) => filters.setFilter('status', v)}>
                <SelectTrigger className="wsms-h-9 wsms-w-full xl:wsms-w-[100px] wsms-text-[12px]" aria-label={__('Filter by status')}>
                  <SelectValue placeholder={__('Status')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{__('All Status')}</SelectItem>
                  <SelectItem value="active">{__('Active')}</SelectItem>
                  <SelectItem value="inactive">{__('Inactive')}</SelectItem>
                </SelectContent>
              </Select>

              {countries.length > 0 && (
                <Select value={filters.filters.country_code} onValueChange={(v) => filters.setFilter('country_code', v)}>
                  <SelectTrigger className="wsms-h-9 wsms-w-full xl:wsms-w-[150px] wsms-text-[12px]" aria-label={__('Filter by country')}>
                    <SelectValue placeholder={__('All Countries')} />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="all">{__('All Countries')}</SelectItem>
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
            </div>
          </div>

          {/* Separator */}
          <div className="wsms-border-t wsms-border-dashed wsms-border-border" />

          {/* Row 2: Quick Add */}
          <div className="wsms-flex wsms-flex-col wsms-gap-3 xl:wsms-flex-row xl:wsms-items-center xl:wsms-gap-3 wsms-p-3">
            <span className="wsms-text-[11px] wsms-font-medium wsms-text-muted-foreground wsms-uppercase wsms-tracking-wide wsms-shrink-0">
              {__('Quick Add')}
            </span>

            <div className="wsms-flex wsms-flex-1 wsms-items-center wsms-gap-2">
              <Input
                type="text"
                value={quickAddName}
                onChange={(e) => setQuickAddName(e.target.value)}
                placeholder={__('Name (optional)')}
                className="wsms-h-9 wsms-flex-1 xl:wsms-flex-none xl:wsms-w-[160px] wsms-text-[13px]"
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && quickAddPhone.trim()) {
                    handleQuickAdd(quickAddName, quickAddPhone)
                  }
                }}
              />
              <Input
                type="tel"
                value={quickAddPhone}
                onChange={(e) => setQuickAddPhone(e.target.value)}
                placeholder={__('Phone number')}
                className="wsms-h-9 wsms-flex-1 xl:wsms-flex-none xl:wsms-w-[160px] wsms-font-mono wsms-text-[13px]"
                onKeyDown={(e) => {
                  if (e.key === 'Enter' && quickAddPhone.trim()) {
                    handleQuickAdd(quickAddName, quickAddPhone)
                  }
                }}
              />
              <Button
                disabled={isAddingQuick || !quickAddPhone.trim()}
                onClick={() => handleQuickAdd(quickAddName, quickAddPhone)}
                className="wsms-h-9 wsms-px-4 wsms-shrink-0"
              >
                {isAddingQuick ? (
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-1.5 wsms-animate-spin" aria-hidden="true" />
                ) : (
                  <UserPlus className="wsms-h-4 wsms-w-4 wsms-mr-1.5" aria-hidden="true" />
                )}
                {__('Add')}
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
            emptyMessage="No subscribers match your filters"
            emptyIcon={Users}
          />
        </CardContent>
      </Card>

      {/* Edit Dialog */}
      <Dialog open={editDialog.isOpen} onOpenChange={(open) => !open && editDialog.close()}>
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
                  value={editDialog.formData.name}
                  onChange={(e) => editDialog.updateField('name', e.target.value)}
                  placeholder="Subscriber name"
                />
              </div>
              <div className="wsms-space-y-2">
                <label className="wsms-text-[12px] wsms-font-medium">Mobile Number</label>
                <Input
                  value={editDialog.formData.mobile}
                  onChange={(e) => editDialog.updateField('mobile', e.target.value)}
                  placeholder="+1234567890"
                  className="wsms-font-mono"
                />
              </div>
              <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4">
                <div className="wsms-space-y-2">
                  <label className="wsms-text-[12px] wsms-font-medium">Group</label>
                  <Select
                    value={editDialog.formData.group_id || 'none'}
                    onValueChange={(v) => editDialog.updateField('group_id', v === 'none' ? '' : v)}
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
                    value={editDialog.formData.status}
                    onValueChange={(v) => editDialog.updateField('status', v)}
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
            <Button variant="outline" onClick={editDialog.close}>
              Cancel
            </Button>
            <Button onClick={editDialog.save} disabled={editDialog.isSaving}>
              {editDialog.isSaving ? (
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
        isLoading={isImporting}
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
              Move {table.selectedIds.length} selected subscriber(s) to a group
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
                  This will move {table.selectedIds.length} subscriber(s) to the selected group.
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
            <Button onClick={handleMoveToGroup} disabled={table.isLoading || !moveToGroupId}>
              {table.isLoading ? (
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
