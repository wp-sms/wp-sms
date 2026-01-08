import React, { useState, useCallback } from 'react'
import {
  FolderOpen,
  Plus,
  Trash2,
  Edit,
  Users,
  Loader2,
  Save,
  X,
  LayoutGrid,
  List,
} from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { DataTable } from '@/components/ui/data-table'
import { QuickAddForm } from '@/components/shared/QuickAddForm'
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
import { cn, __ } from '@/lib/utils'
import { PageLoadingSkeleton } from '@/components/ui/skeleton'
import { useDataTable } from '@/hooks/useDataTable'
import { useFormDialog } from '@/hooks/useFormDialog'
import { useToast } from '@/components/ui/toaster'

export default function Groups() {
  const { toast } = useToast()

  // Data management with useDataTable hook
  const table = useDataTable({
    fetchFn: (params) => groupsApi.getGroups(params),
  })

  // Delete confirmation dialog using useFormDialog
  const deleteDialog = useFormDialog({
    saveFn: async (id) => {
      await groupsApi.deleteGroup(id)
      table.removeItems([id])
    },
    successMessage: __('Group deleted successfully'),
  })

  // UI state
  const [isAddingQuick, setIsAddingQuick] = useState(false)
  const [viewMode, setViewMode] = useState('list') // 'list' or 'grid'

  // Inline edit state
  const [inlineEditId, setInlineEditId] = useState(null)
  const [inlineEditValue, setInlineEditValue] = useState('')
  const [isInlineEditSaving, setIsInlineEditSaving] = useState(false)

  // Handle quick add
  const handleQuickAdd = async (name) => {
    setIsAddingQuick(true)
    try {
      await groupsApi.createGroup({ name })
      toast({ title: __('Group created successfully'), variant: 'success' })
      table.fetch({ page: 1 })
      // Notify other pages (e.g., Subscribers) that groups changed
      window.dispatchEvent(new CustomEvent('wpsms:groups-changed'))
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

    setIsInlineEditSaving(true)
    try {
      await groupsApi.updateGroup(inlineEditId, { name: inlineEditValue.trim() })
      toast({ title: __('Group updated successfully'), variant: 'success' })
      setInlineEditId(null)
      table.refresh()
      // Notify other pages (e.g., Subscribers) that groups changed
      window.dispatchEvent(new CustomEvent('wpsms:groups-changed'))
    } catch (error) {
      toast({ title: error.message, variant: 'destructive' })
    } finally {
      setIsInlineEditSaving(false)
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

  // Handle delete click - opens dialog with item
  const handleDeleteClick = useCallback((group) => {
    deleteDialog.open(group)
  }, [deleteDialog])

  // Handle delete confirm
  const handleDeleteConfirm = async () => {
    if (!deleteDialog.item) return
    try {
      await deleteDialog.save()
      // Notify other pages (e.g., Subscribers) that groups changed
      window.dispatchEvent(new CustomEvent('wpsms:groups-changed'))
    } catch {
      // Error already handled by useFormDialog
    }
  }

  // Table columns
  const columns = [
    {
      id: 'id',
      accessorKey: 'id',
      header: __('ID'),
      cell: ({ row }) => (
        <span className="wsms-text-[12px] wsms-text-muted-foreground wsms-font-mono">
          {row.id}
        </span>
      ),
    },
    {
      id: 'name',
      accessorKey: 'name',
      header: __('Group Name'),
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
                disabled={isInlineEditSaving}
                aria-label={__('Save group name')}
              >
                {isInlineEditSaving ? (
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-animate-spin wsms-text-emerald-600" />
                ) : (
                  <Save className="wsms-h-4 wsms-w-4 wsms-text-emerald-600" />
                )}
              </Button>
              <Button
                size="icon"
                variant="ghost"
                className="wsms-h-8 wsms-w-8"
                onClick={handleInlineEditCancel}
                disabled={isInlineEditSaving}
                aria-label={__('Cancel editing')}
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
      header: __('Subscribers'),
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
      label: __('Edit'),
      icon: Edit,
      onClick: handleInlineEditStart,
    },
    {
      label: __('Delete'),
      icon: Trash2,
      onClick: handleDeleteClick,
      variant: 'destructive',
    },
  ]

  // Calculate total subscribers
  const totalSubscribers = table.data.reduce((sum, g) => sum + (g.subscriber_count || 0), 0)

  // Show skeleton during initial load
  if (!table.initialLoadDone) {
    return <PageLoadingSkeleton />
  }

  // Empty state
  if (table.data.length === 0 && !table.isLoading) {
    return (
      <div className="wsms-space-y-6 wsms-stagger-children">
        <Card className="wsms-border-dashed">
          <CardContent className="wsms-py-16">
            <div className="wsms-flex wsms-flex-col wsms-items-center wsms-text-center wsms-max-w-md wsms-mx-auto">
              <div className="wsms-flex wsms-h-16 wsms-w-16 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-primary/10 wsms-mb-6">
                <FolderOpen className="wsms-h-8 wsms-w-8 wsms-text-primary" strokeWidth={1.5} />
              </div>
              <h3 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-2">
                {__('Create your first group')}
              </h3>
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-6">
                {__('Groups help you organize subscribers for targeted messaging. Segment your audience by interest, location, or any criteria that matters to your communication.')}
              </p>

              <div className="wsms-w-full wsms-max-w-sm">
                <QuickAddForm
                  placeholder={__('Enter group name...')}
                  buttonLabel={__('Create Group')}
                  onSubmit={handleQuickAdd}
                  isLoading={isAddingQuick}
                  validate={(value) => {
                    if (value.length < 2) return __('Group name must be at least 2 characters')
                    if (value.length > 50) return __('Group name must be less than 50 characters')
                    return null
                  }}
                />
              </div>

              <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4 wsms-mt-8 wsms-pt-6 wsms-border-t wsms-border-border wsms-w-full">
                <div className="wsms-text-left wsms-p-3 wsms-rounded-lg wsms-bg-muted/30">
                  <Users className="wsms-h-4 wsms-w-4 wsms-text-primary wsms-mb-2" />
                  <p className="wsms-text-[12px] wsms-font-medium wsms-text-foreground">{__('Targeted Messaging')}</p>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Send SMS to specific groups only')}</p>
                </div>
                <div className="wsms-text-left wsms-p-3 wsms-rounded-lg wsms-bg-muted/30">
                  <LayoutGrid className="wsms-h-4 wsms-w-4 wsms-text-primary wsms-mb-2" />
                  <p className="wsms-text-[12px] wsms-font-medium wsms-text-foreground">{__('Easy Organization')}</p>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Manage subscribers efficiently')}</p>
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
      {/* Header Bar with Stats and Actions */}
      <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4 wsms-px-5 wsms-py-3.5 wsms-rounded-lg wsms-bg-gradient-to-r wsms-from-muted/50 wsms-to-muted/30 wsms-border wsms-border-border">
        {/* Left: Stats */}
        <div className="wsms-flex wsms-items-center wsms-gap-6">
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-primary/10">
              <FolderOpen className="wsms-h-5 wsms-w-5 wsms-text-primary" />
            </div>
            <div>
              <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">{table.pagination.total}</p>
              <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Groups')}</p>
            </div>
          </div>
          <div className="wsms-w-px wsms-h-10 wsms-bg-border" />
          <div className="wsms-flex wsms-items-center wsms-gap-3">
            <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-success/10">
              <Users className="wsms-h-5 wsms-w-5 wsms-text-success" />
            </div>
            <div>
              <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">{totalSubscribers}</p>
              <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Total Subscribers')}</p>
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

      {/* Quick Add */}
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
              data={table.data}
              loading={table.isLoading}
              pagination={{
                total: table.pagination.total,
                totalPages: table.pagination.total_pages,
                page: table.pagination.current_page,
                perPage: table.pagination.per_page,
                onPageChange: table.handlePageChange,
              }}
              rowActions={rowActions}
              emptyMessage="No groups found"
              emptyIcon={FolderOpen}
            />
          </CardContent>
        </Card>
      ) : (
        <div className="wsms-grid wsms-grid-cols-2 md:wsms-grid-cols-3 lg:wsms-grid-cols-4 wsms-gap-4">
          {table.data.map((group) => (
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
                        disabled={isInlineEditSaving}
                      >
                        <X className="wsms-h-3 wsms-w-3 wsms-mr-1" />
                        {__('Cancel')}
                      </Button>
                      <Button
                        size="sm"
                        className="wsms-flex-1 wsms-h-7"
                        onClick={handleInlineEditSave}
                        disabled={isInlineEditSaving}
                      >
                        {isInlineEditSaving ? (
                          <Loader2 className="wsms-h-3 wsms-w-3 wsms-mr-1 wsms-animate-spin" />
                        ) : (
                          <Save className="wsms-h-3 wsms-w-3 wsms-mr-1" />
                        )}
                        {isInlineEditSaving ? __('Saving...') : __('Save')}
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
                          onClick={() => handleDeleteClick(group)}
                          className="wsms-p-1.5 wsms-rounded-md wsms-bg-muted/80 wsms-text-muted-foreground hover:wsms-text-destructive wsms-transition-colors"
                        >
                          <Trash2 className="wsms-h-3.5 wsms-w-3.5" />
                        </button>
                      </div>
                    </div>

                    {/* ID Badge */}
                    <div className="wsms-absolute wsms-top-2 wsms-left-2">
                      <span className="wsms-text-[10px] wsms-font-mono wsms-text-muted-foreground wsms-bg-muted/60 wsms-px-1.5 wsms-py-0.5 wsms-rounded">
                        #{group.id}
                      </span>
                    </div>

                    {/* Content */}
                    <div className="wsms-flex wsms-items-start wsms-gap-3 wsms-mt-4">
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
      {viewMode === 'grid' && table.pagination.total_pages > 1 && (
        <div className="wsms-flex wsms-justify-center wsms-gap-2">
          <Button
            variant="outline"
            size="sm"
            onClick={() => table.handlePageChange(table.pagination.current_page - 1)}
            disabled={table.pagination.current_page === 1}
          >
            Previous
          </Button>
          <span className="wsms-flex wsms-items-center wsms-px-3 wsms-text-[12px] wsms-text-muted-foreground">
            Page {table.pagination.current_page} of {table.pagination.total_pages}
          </span>
          <Button
            variant="outline"
            size="sm"
            onClick={() => table.handlePageChange(table.pagination.current_page + 1)}
            disabled={table.pagination.current_page === table.pagination.total_pages}
          >
            Next
          </Button>
        </div>
      )}

      {/* Delete Confirmation Dialog */}
      <Dialog open={deleteDialog.isOpen} onOpenChange={(open) => !open && deleteDialog.close()}>
        <DialogContent size="sm">
          <DialogHeader>
            <DialogTitle>{__('Delete Group')}</DialogTitle>
            <DialogDescription>
              {__('Are you sure you want to delete')} "{deleteDialog.item?.name}"?
            </DialogDescription>
          </DialogHeader>
          <DialogBody>
            <div className="wsms-p-4 wsms-rounded-md wsms-bg-amber-500/10 wsms-border wsms-border-amber-500/20">
              <p className="wsms-text-[12px] wsms-text-amber-700 dark:wsms-text-amber-400">
                {__('This will remove the group but keep all subscribers. They will become ungrouped.')}
              </p>
            </div>
          </DialogBody>
          <DialogFooter>
            <Button variant="outline" onClick={deleteDialog.close}>
              {__('Cancel')}
            </Button>
            <Button variant="destructive" onClick={handleDeleteConfirm} disabled={deleteDialog.isSaving}>
              {deleteDialog.isSaving ? (
                <>
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" />
                  {__('Deleting...')}
                </>
              ) : (
                <>
                  <Trash2 className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                  {__('Delete Group')}
                </>
              )}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  )
}
