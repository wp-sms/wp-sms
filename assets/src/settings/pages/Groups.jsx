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
} from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { DataTable } from '@/components/ui/data-table'
import { QuickAddForm } from '@/components/shared/QuickAddForm'
import { Tip, EmptyStateAction } from '@/components/ui/ux-helpers'
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
  const [selectedIds, setSelectedIds] = useState([])
  const [editGroup, setEditGroup] = useState(null)
  const [deleteGroup, setDeleteGroup] = useState(null)
  const [notification, setNotification] = useState(null)
  const [isAddingQuick, setIsAddingQuick] = useState(false)

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
              >
                <Save className="wsms-h-4 wsms-w-4 wsms-text-emerald-600" />
              </Button>
              <Button
                size="icon"
                variant="ghost"
                className="wsms-h-8 wsms-w-8"
                onClick={handleInlineEditCancel}
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

      {/* First-time helper */}
      {groups.length === 0 && !isLoading && (
        <Tip>
          <strong>Organize your audience with Groups!</strong> Create groups to segment subscribers by interest, location, or any criteria.
          This makes it easy to send targeted messages to the right people.
        </Tip>
      )}

      {/* Stats Cards - only show when there are groups */}
      {groups.length > 0 && (
        <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4">
          <Card className="wsms-py-4 wsms-stat-card wsms-card-hover">
            <CardContent className="wsms-py-0 wsms-text-center">
              <p className="wsms-text-2xl wsms-font-bold wsms-text-foreground wsms-count-animate">{pagination.total}</p>
              <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-uppercase wsms-tracking-wide">Total Groups</p>
            </CardContent>
          </Card>
          <Card className="wsms-py-4 wsms-stat-card wsms-stat-card-success wsms-card-hover">
            <CardContent className="wsms-py-0 wsms-text-center">
              <p className="wsms-text-2xl wsms-font-bold wsms-text-success wsms-count-animate">{totalSubscribers}</p>
              <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-uppercase wsms-tracking-wide">Total Subscribers</p>
            </CardContent>
          </Card>
        </div>
      )}

      {/* Quick Add */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Plus className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            Create New Group
          </CardTitle>
          <CardDescription>
            Groups help you organize subscribers for targeted messaging
          </CardDescription>
        </CardHeader>
        <CardContent>
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
        </CardContent>
      </Card>

      {/* Data Table */}
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <FolderOpen className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            All Groups
          </CardTitle>
          <CardDescription>
            Click on a group name to edit it inline
          </CardDescription>
        </CardHeader>
        <CardContent className="wsms-p-0 wsms-border-t wsms-border-border">
          <DataTable
            columns={columns}
            data={groups}
            loading={isLoading}
            pagination={{
              total: pagination.total,
              page: pagination.current_page,
              perPage: pagination.per_page,
              onPageChange: handlePageChange,
            }}
            rowActions={rowActions}
            emptyMessage="No groups found. Create your first group above."
            emptyIcon={FolderOpen}
          />
        </CardContent>
      </Card>

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
