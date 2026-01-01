import React, { useState, useEffect, useCallback } from 'react'
import {
  FolderOpen,
  Plus,
  Trash2,
  Edit,
  Users,
  CheckCircle,
  AlertCircle,
  Loader2,
  Save,
  X,
  Send,
  LayoutGrid,
  List,
  MoreHorizontal,
} from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { DataTable } from '@/components/ui/data-table'
import { QuickAddForm } from '@/components/shared/QuickAddForm'
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
import { groupsApi } from '@/api/groupsApi'
import { cn } from '@/lib/utils'

export default function Groups() {
  // Data state
  const [groups, setGroups] = useState([])
  const [pagination, setPagination] = useState({
    total: 0,
    total_pages: 1,
    current_page: 1,
    per_page: 20,
  })

  // UI state
  const [isLoading, setIsLoading] = useState(true)
  const [initialLoadDone, setInitialLoadDone] = useState(false)
  const [selectedIds, setSelectedIds] = useState([])
  const [editGroup, setEditGroup] = useState(null)
  const [deleteGroup, setDeleteGroup] = useState(null)
  const [notification, setNotification] = useState(null)
  const [isAddingQuick, setIsAddingQuick] = useState(false)
  const [viewMode, setViewMode] = useState('list') // 'list' or 'grid'

  // Inline edit state
  const [inlineEditId, setInlineEditId] = useState(null)
  const [inlineEditValue, setInlineEditValue] = useState('')

  // Form state for edit dialog
  const [formData, setFormData] = useState({ name: '' })
  const [isSaving, setIsSaving] = useState(false)
  const [isDeleting, setIsDeleting] = useState(false)

  // Fetch groups
  const fetchGroups = useCallback(async (page = 1) => {
    setIsLoading(true)
    try {
      const result = await groupsApi.getGroups({
        page,
        per_page: pagination.per_page,
      })
      setGroups(result.items)
      setPagination(result.pagination)
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    } finally {
      setIsLoading(false)
      setInitialLoadDone(true)
    }
  }, [pagination.per_page])

  // Initial fetch
  useEffect(() => {
    fetchGroups()
  }, []) // eslint-disable-line react-hooks/exhaustive-deps

  // Handle page change
  const handlePageChange = (page) => {
    fetchGroups(page)
  }

  // Handle quick add
  const handleQuickAdd = async (name) => {
    setIsAddingQuick(true)
    try {
      await groupsApi.createGroup({ name })
      setNotification({ type: 'success', message: 'Group created successfully' })
      fetchGroups(1)
    } catch (error) {
      throw error
    } finally {
      setIsAddingQuick(false)
    }
  }

  // Handle inline edit start
  const handleInlineEditStart = (group) => {
    setInlineEditId(group.id)
    setInlineEditValue(group.name)
  }

  // Handle inline edit save
  const handleInlineEditSave = async () => {
    if (!inlineEditValue.trim()) {
      setInlineEditId(null)
      return
    }

    try {
      await groupsApi.updateGroup(inlineEditId, { name: inlineEditValue.trim() })
      setNotification({ type: 'success', message: 'Group updated successfully' })
      setInlineEditId(null)
      fetchGroups(pagination.current_page)
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    }
  }

  // Handle inline edit cancel
  const handleInlineEditCancel = () => {
    setInlineEditId(null)
    setInlineEditValue('')
  }

  // Handle inline edit key down
  const handleInlineEditKeyDown = (e) => {
    if (e.key === 'Enter') {
      handleInlineEditSave()
    } else if (e.key === 'Escape') {
      handleInlineEditCancel()
    }
  }

  // Handle delete
  const handleDelete = async () => {
    if (!deleteGroup) return

    setIsDeleting(true)
    try {
      await groupsApi.deleteGroup(deleteGroup.id)
      setNotification({ type: 'success', message: 'Group deleted successfully' })
      setDeleteGroup(null)
      fetchGroups(pagination.current_page)
    } catch (error) {
      setNotification({ type: 'error', message: error.message })
    } finally {
      setIsDeleting(false)
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
      header: 'Group Name',
      sortable: true,
      cell: ({ row }) => {
        if (inlineEditId === row.id) {
          return (
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <Input
                value={inlineEditValue}
                onChange={(e) => setInlineEditValue(e.target.value)}
                onKeyDown={handleInlineEditKeyDown}
                autoFocus
                className="wsms-h-8 wsms-w-48"
              />
              <Button
                size="icon"
                variant="ghost"
                className="wsms-h-8 wsms-w-8"
                onClick={handleInlineEditSave}
                aria-label="Save group name"
              >
                <Save className="wsms-h-4 wsms-w-4 wsms-text-emerald-600" />
              </Button>
              <Button
                size="icon"
                variant="ghost"
                className="wsms-h-8 wsms-w-8"
                onClick={handleInlineEditCancel}
                aria-label="Cancel editing"
              >
                <X className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              </Button>
            </div>
          )
        }

        return (
          <button
            onClick={() => handleInlineEditStart(row)}
            className="wsms-text-[13px] wsms-font-medium wsms-text-foreground hover:wsms-text-primary wsms-text-left wsms-transition-colors"
          >
            {row.name}
          </button>
        )
      },
    },
    {
      id: 'subscriber_count',
      accessorKey: 'subscriber_count',
      header: 'Subscribers',
      cell: ({ row }) => (
        <div className="wsms-flex wsms-items-center wsms-gap-2">
          <Users className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
          <span className="wsms-text-[13px] wsms-text-foreground">
            {row.subscriber_count || 0}
          </span>
        </div>
      ),
    },
  ]

  // Row actions
  const rowActions = [
    {
      label: 'Edit',
      icon: Edit,
      onClick: handleInlineEditStart,
    },
    {
      label: 'Delete',
      icon: Trash2,
      onClick: (row) => setDeleteGroup(row),
      variant: 'destructive',
    },
  ]

  // Calculate total subscribers
  const totalSubscribers = groups.reduce((sum, g) => sum + (g.subscriber_count || 0), 0)

  // Show skeleton during initial load to prevent flash
  if (!initialLoadDone) {
    return (
      <div className="wsms-space-y-6">
        <div className="wsms-h-20 wsms-rounded-lg wsms-bg-muted/30 wsms-animate-pulse" />
        <div className="wsms-h-16 wsms-rounded-lg wsms-bg-muted/30 wsms-animate-pulse" />
        <div className="wsms-h-48 wsms-rounded-lg wsms-bg-muted/30 wsms-animate-pulse" />
      </div>
    )
  }

  // Empty state
  if (groups.length === 0) {
    return (
      <div className="wsms-space-y-6 wsms-stagger-children">
        {/* Notification */}
        {notification && (
          <div
            className={cn(
              'wsms-flex wsms-items-center wsms-gap-3 wsms-p-4 wsms-rounded-lg wsms-border',
              'wsms-animate-in wsms-fade-in wsms-slide-in-from-top-2 wsms-duration-300',
              notification.type === 'success'
                ? 'wsms-bg-emerald-50 wsms-border-emerald-200 wsms-text-emerald-800 dark:wsms-bg-emerald-900/30 dark:wsms-border-emerald-800 dark:wsms-text-emerald-200'
                : 'wsms-bg-red-50 wsms-border-red-200 wsms-text-red-800 dark:wsms-bg-red-900/30 dark:wsms-border-red-800 dark:wsms-text-red-200'
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

        {/* Empty State - Full Width Centered */}
        <Card className="wsms-border-dashed">
          <CardContent className="wsms-py-16">
            <div className="wsms-flex wsms-flex-col wsms-items-center wsms-text-center wsms-max-w-md wsms-mx-auto">
              <div className="wsms-flex wsms-h-16 wsms-w-16 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-primary/10 wsms-mb-6">
                <FolderOpen className="wsms-h-8 wsms-w-8 wsms-text-primary" strokeWidth={1.5} />
              </div>
              <h3 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-2">
                Create your first group
              </h3>
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-6">
                Groups help you organize subscribers for targeted messaging.
                Segment your audience by interest, location, or any criteria that matters to your communication.
              </p>

              {/* Inline Create Form */}
              <div className="wsms-w-full wsms-max-w-sm">
                <QuickAddForm
                  placeholder="Enter group name..."
                  buttonLabel="Create Group"
                  onSubmit={handleQuickAdd}
                  isLoading={isAddingQuick}
                  validate={(value) => {
                    if (value.length < 2) return 'Group name must be at least 2 characters'
                    if (value.length > 50) return 'Group name must be less than 50 characters'
                    return null
                  }}
                />
              </div>

              {/* Feature hints */}
              <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4 wsms-mt-8 wsms-pt-6 wsms-border-t wsms-border-border wsms-w-full">
                <div className="wsms-text-left wsms-p-3 wsms-rounded-lg wsms-bg-muted/30">
                  <Users className="wsms-h-4 wsms-w-4 wsms-text-primary wsms-mb-2" />
                  <p className="wsms-text-[12px] wsms-font-medium wsms-text-foreground">Targeted Messaging</p>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground">Send SMS to specific groups only</p>
                </div>
                <div className="wsms-text-left wsms-p-3 wsms-rounded-lg wsms-bg-muted/30">
                  <LayoutGrid className="wsms-h-4 wsms-w-4 wsms-text-primary wsms-mb-2" />
                  <p className="wsms-text-[12px] wsms-font-medium wsms-text-foreground">Easy Organization</p>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground">Manage subscribers efficiently</p>
                </div>
              </div>
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
              ? 'wsms-bg-emerald-50 wsms-border-emerald-200 wsms-text-emerald-800 dark:wsms-bg-emerald-900/30 dark:wsms-border-emerald-800 dark:wsms-text-emerald-200'
              : 'wsms-bg-red-50 wsms-border-red-200 wsms-text-red-800 dark:wsms-bg-red-900/30 dark:wsms-border-red-800 dark:wsms-text-red-200'
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

      {/* Header Bar with Stats and Actions */}
      <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-5 wsms-py-3.5 wsms-rounded-lg wsms-bg-gradient-to-r wsms-from-muted/50 wsms-to-muted/30 wsms-border wsms-border-border">
        {/* Left: Stats */}
        <div className="wsms-flex wsms-items-center wsms-gap-6">
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-primary/10">
              <FolderOpen className="wsms-h-5 wsms-w-5 wsms-text-primary" />
            </div>
            <div>
              <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">{pagination.total}</p>
              <p className="wsms-text-[11px] wsms-text-muted-foreground">Groups</p>
            </div>
          </div>
          <div className="wsms-w-px wsms-h-10 wsms-bg-border" />
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-success/10">
              <Users className="wsms-h-5 wsms-w-5 wsms-text-success" />
            </div>
            <div>
              <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">{totalSubscribers}</p>
              <p className="wsms-text-[11px] wsms-text-muted-foreground">Total Subscribers</p>
            </div>
          </div>
        </div>

        {/* Right: View Toggle */}
        <div className="wsms-flex wsms-items-center wsms-gap-2">
          <div className="wsms-flex wsms-items-center wsms-rounded-lg wsms-border wsms-border-border wsms-p-1">
            <button
              onClick={() => setViewMode('list')}
              className={cn(
                'wsms-p-1.5 wsms-rounded-md wsms-transition-colors',
                viewMode === 'list' ? 'wsms-bg-muted wsms-text-foreground' : 'wsms-text-muted-foreground hover:wsms-text-foreground'
              )}
              aria-label="List view"
              aria-pressed={viewMode === 'list'}
            >
              <List className="wsms-h-4 wsms-w-4" />
            </button>
            <button
              onClick={() => setViewMode('grid')}
              className={cn(
                'wsms-p-1.5 wsms-rounded-md wsms-transition-colors',
                viewMode === 'grid' ? 'wsms-bg-muted wsms-text-foreground' : 'wsms-text-muted-foreground hover:wsms-text-foreground'
              )}
              aria-label="Grid view"
              aria-pressed={viewMode === 'grid'}
            >
              <LayoutGrid className="wsms-h-4 wsms-w-4" />
            </button>
          </div>
        </div>
      </div>

      {/* Quick Add - Inline style */}
      <Card>
        <CardContent className="wsms-py-4">
          <div className="wsms-flex wsms-items-center wsms-gap-4">
            <Plus className="wsms-h-5 wsms-w-5 wsms-text-primary wsms-shrink-0" />
            <div className="wsms-flex-1">
              <QuickAddForm
                placeholder="Enter group name to create..."
                buttonLabel="Create Group"
                onSubmit={handleQuickAdd}
                isLoading={isAddingQuick}
                validate={(value) => {
                  if (value.length < 2) return 'Group name must be at least 2 characters'
                  if (value.length > 50) return 'Group name must be less than 50 characters'
                  return null
                }}
              />
            </div>
          </div>
        </CardContent>
      </Card>

      {/* Content - List or Grid View */}
      {viewMode === 'list' ? (
        <Card>
          <CardContent className="wsms-p-0">
            <DataTable
              columns={columns}
              data={groups}
              loading={isLoading}
              pagination={{
                total: pagination.total,
                totalPages: pagination.total_pages,
                page: pagination.current_page,
                perPage: pagination.per_page,
                onPageChange: handlePageChange,
              }}
              rowActions={rowActions}
              emptyMessage="No groups found"
              emptyIcon={FolderOpen}
            />
          </CardContent>
        </Card>
      ) : (
        <div className="wsms-grid wsms-grid-cols-2 md:wsms-grid-cols-3 lg:wsms-grid-cols-4 wsms-gap-4">
          {groups.map((group) => (
            <Card
              key={group.id}
              className="wsms-card-hover wsms-group wsms-relative wsms-overflow-hidden"
            >
              <CardContent className="wsms-py-4">
                {inlineEditId === group.id ? (
                  <div className="wsms-space-y-3">
                    <Input
                      value={inlineEditValue}
                      onChange={(e) => setInlineEditValue(e.target.value)}
                      onKeyDown={handleInlineEditKeyDown}
                      autoFocus
                      className="wsms-h-8"
                    />
                    <div className="wsms-flex wsms-gap-2">
                      <Button
                        size="sm"
                        variant="ghost"
                        className="wsms-flex-1 wsms-h-7"
                        onClick={handleInlineEditCancel}
                      >
                        <X className="wsms-h-3 wsms-w-3 wsms-mr-1" />
                        Cancel
                      </Button>
                      <Button
                        size="sm"
                        className="wsms-flex-1 wsms-h-7"
                        onClick={handleInlineEditSave}
                      >
                        <Save className="wsms-h-3 wsms-w-3 wsms-mr-1" />
                        Save
                      </Button>
                    </div>
                  </div>
                ) : (
                  <>
                    {/* Actions - appears on hover */}
                    <div className="wsms-absolute wsms-top-2 wsms-right-2 wsms-opacity-0 group-hover:wsms-opacity-100 wsms-transition-opacity">
                      <div className="wsms-flex wsms-gap-1">
                        <button
                          onClick={() => handleInlineEditStart(group)}
                          className="wsms-p-1.5 wsms-rounded-md wsms-bg-muted/80 wsms-text-muted-foreground hover:wsms-text-foreground wsms-transition-colors"
                        >
                          <Edit className="wsms-h-3.5 wsms-w-3.5" />
                        </button>
                        <button
                          onClick={() => setDeleteGroup(group)}
                          className="wsms-p-1.5 wsms-rounded-md wsms-bg-muted/80 wsms-text-muted-foreground hover:wsms-text-destructive wsms-transition-colors"
                        >
                          <Trash2 className="wsms-h-3.5 wsms-w-3.5" />
                        </button>
                      </div>
                    </div>

                    {/* Content */}
                    <div className="wsms-flex wsms-items-start wsms-gap-3">
                      <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-primary/10 wsms-shrink-0">
                        <FolderOpen className="wsms-h-5 wsms-w-5 wsms-text-primary" />
                      </div>
                      <div className="wsms-min-w-0">
                        <button
                          onClick={() => handleInlineEditStart(group)}
                          className="wsms-text-[13px] wsms-font-medium wsms-text-foreground hover:wsms-text-primary wsms-text-left wsms-transition-colors wsms-truncate wsms-block wsms-w-full"
                        >
                          {group.name}
                        </button>
                        <div className="wsms-flex wsms-items-center wsms-gap-1 wsms-mt-1">
                          <Users className="wsms-h-3 wsms-w-3 wsms-text-muted-foreground" />
                          <span className="wsms-text-[12px] wsms-text-muted-foreground">
                            {group.subscriber_count || 0} subscribers
                          </span>
                        </div>
                      </div>
                    </div>
                  </>
                )}
              </CardContent>
            </Card>
          ))}
        </div>
      )}

      {/* Pagination for grid view */}
      {viewMode === 'grid' && pagination.total_pages > 1 && (
        <div className="wsms-flex wsms-justify-center wsms-gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => handlePageChange(pagination.current_page - 1)}
            disabled={pagination.current_page === 1}
          >
            Previous
          </Button>
          <span className="wsms-flex wsms-items-center wsms-px-3 wsms-text-[12px] wsms-text-muted-foreground">
            Page {pagination.current_page} of {pagination.total_pages}
          </span>
          <Button
            variant="outline"
            size="sm"
            onClick={() => handlePageChange(pagination.current_page + 1)}
            disabled={pagination.current_page === pagination.total_pages}
          >
            Next
          </Button>
        </div>
      )}

      {/* Delete Confirmation Dialog */}
      <Dialog open={!!deleteGroup} onOpenChange={() => setDeleteGroup(null)}>
        <DialogContent size="sm">
          <DialogHeader>
            <DialogTitle>Delete Group</DialogTitle>
            <DialogDescription>
              Are you sure you want to delete "{deleteGroup?.name}"?
            </DialogDescription>
          </DialogHeader>
          <DialogBody>
            <div className="wsms-p-4 wsms-rounded-md wsms-bg-amber-500/10 wsms-border wsms-border-amber-500/20">
              <p className="wsms-text-[12px] wsms-text-amber-700 dark:wsms-text-amber-400">
                This will remove the group but keep all subscribers. They will become ungrouped.
              </p>
            </div>
          </DialogBody>
          <DialogFooter>
            <Button variant="outline" onClick={() => setDeleteGroup(null)}>
              Cancel
            </Button>
            <Button variant="destructive" onClick={handleDelete} disabled={isDeleting}>
              {isDeleting ? (
                <>
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" />
                  Deleting...
                </>
              ) : (
                <>
                  <Trash2 className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                  Delete Group
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
