import React, { useState, useEffect, useCallback, useMemo } from 'react'
import {
  Terminal,
  ExternalLink,
  Plus,
  Edit,
  Trash2,
  Power,
  RefreshCw,
  CheckCircle,
  XCircle,
  Loader2,
  Search,
  X,
} from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { DataTable } from '@/components/ui/data-table'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { DeleteConfirmDialog } from '@/components/shared/DeleteConfirmDialog'
import { DialogLoadingSpinner } from '@/components/shared/DialogLoadingSpinner'
import { PageLoadingSkeleton } from '@/components/ui/skeleton'
import { useFormDialog } from '@/hooks/useFormDialog'
import { useSettings } from '@/context/SettingsContext'
import { useToast } from '@/components/ui/toaster'
import { cn, __ } from '@/lib/utils'
import { commandsApi } from '@/api/twoWayApi'
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
  SelectGroup,
  SelectItem,
  SelectLabel,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Textarea } from '@/components/ui/textarea'
import { Switch } from '@/components/ui/switch'

export default function TwoWayCommands() {
  const { isAddonActive } = useSettings()
  const { toast } = useToast()
  const hasTwoWay = isAddonActive('two-way')

  // Data state
  const [commands, setCommands] = useState([])
  const [actions, setActions] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [isInitialLoad, setIsInitialLoad] = useState(true)
  const [isActionsLoading, setIsActionsLoading] = useState(true)

  // Fetch commands
  const fetchCommands = useCallback(async () => {
    try {
      setIsLoading(true)
      const response = await commandsApi.getCommands()
      if (response.success) {
        setCommands(Array.isArray(response.data?.commands) ? response.data.commands : [])
      }
    } catch (error) {
      toast({ title: error.message || __('Failed to load commands'), variant: 'destructive' })
    } finally {
      setIsLoading(false)
      setIsInitialLoad(false)
    }
  }, [toast])

  // Fetch available actions
  const fetchActions = useCallback(async () => {
    try {
      setIsActionsLoading(true)
      const response = await commandsApi.getActions()
      if (response.success) {
        setActions(Array.isArray(response.data) ? response.data : [])
      }
    } catch {
      // silent
    } finally {
      setIsActionsLoading(false)
    }
  }, [])

  useEffect(() => {
    if (hasTwoWay) {
      fetchCommands()
      fetchActions()
    }
  }, [hasTwoWay, fetchCommands, fetchActions])

  // Command form dialog
  const commandDialog = useFormDialog({
    saveFn: async (id, formData) => {
      if (id) {
        return commandsApi.updateCommand(id, formData)
      }
      return commandsApi.createCommand(formData)
    },
    initialData: {
      command_name: '',
      action_reference: '',
      status: 'enabled',
      response_data: {
        success: { text: '' },
        failure: { text: '' },
      },
    },
    validate: (data) => {
      const errors = {}
      if (!data.command_name?.trim()) errors.command_name = __('Command name is required')
      if (!data.action_reference) errors.action_reference = __('Please select an action')
      return { valid: Object.keys(errors).length === 0, errors }
    },
    onSuccess: () => fetchCommands(),
    createSuccessMessage: __('Command created successfully'),
    updateSuccessMessage: __('Command updated successfully'),
  })

  // Delete confirmation dialog
  const deleteDialog = useFormDialog({
    saveFn: async (id) => {
      await commandsApi.deleteCommand(id)
    },
    successMessage: __('Command deleted'),
    onSuccess: () => fetchCommands(),
  })

  const handleDeleteClick = useCallback((command) => {
    deleteDialog.open(command)
  }, [deleteDialog])

  const handleDeleteConfirm = async () => {
    if (!deleteDialog.item) return
    try {
      await deleteDialog.save()
    } catch {
      // handled
    }
  }

  // Open for edit - show dialog immediately, then fetch full command data (list endpoint omits response_data)
  const [isEditLoading, setIsEditLoading] = useState(false)
  const handleEditCommand = useCallback(async (command) => {
    // Open dialog immediately with list data
    commandDialog.open({
      id: command.id,
      command_name: command.command_name || '',
      action_reference: command.action_reference || '',
      status: command.status || 'enabled',
      response_data: {
        success: { text: '' },
        failure: { text: '' },
      },
    })

    // Then fetch full data (includes response_data)
    try {
      setIsEditLoading(true)
      const response = await commandsApi.getCommand(command.id)
      if (response.success && response.data) {
        const full = response.data
        commandDialog.setFormData({
          id: full.id,
          command_name: full.command_name || '',
          action_reference: full.action_reference || '',
          status: full.status || 'enabled',
          response_data: {
            success: { text: full.response_data?.success?.text || '' },
            failure: { text: full.response_data?.failure?.text || '' },
          },
        })
      }
    } catch {
      // Keep list data already in dialog
    } finally {
      setIsEditLoading(false)
    }
  }, [commandDialog])

  // Toggle command status
  const [togglingId, setTogglingId] = useState(null)
  const handleToggle = async (command) => {
    try {
      setTogglingId(command.id)
      const response = await commandsApi.toggleCommand(command.id)
      if (response.success) {
        toast({
          title: response.data?.status === 'enabled' ? __('Command enabled') : __('Command disabled'),
          variant: 'success',
        })
        await fetchCommands()
      }
    } catch (error) {
      toast({ title: error.message || __('Failed to toggle command'), variant: 'destructive' })
    } finally {
      setTogglingId(null)
    }
  }

  // Computed action variables
  const selectedActionData = useMemo(() => {
    if (!commandDialog.formData.action_reference || !actions.length) return null
    return actions.flatMap(g => g.actions || []).find(a => a.reference === commandDialog.formData.action_reference) || null
  }, [commandDialog.formData.action_reference, actions])

  const successVariables = useMemo(() => {
    if (!selectedActionData) return []
    return [...(selectedActionData.globalVariables || []), ...(selectedActionData.variables || [])]
  }, [selectedActionData])

  const failureVariables = useMemo(() => {
    if (!selectedActionData) return []
    return selectedActionData.failureVariables || []
  }, [selectedActionData])

  // Filters (client-side since commands aren't paginated)
  const [searchTerm, setSearchTerm] = useState('')
  const [filterStatus, setFilterStatus] = useState('all')
  const [filterAction, setFilterAction] = useState('all')

  const filteredCommands = useMemo(() => {
    return commands.filter(cmd => {
      if (searchTerm && !cmd.command_name?.toLowerCase().includes(searchTerm.toLowerCase())) return false
      if (filterStatus !== 'all' && cmd.status !== filterStatus) return false
      if (filterAction !== 'all' && cmd.action_reference !== filterAction) return false
      return true
    })
  }, [commands, searchTerm, filterStatus, filterAction])

  const hasActiveFilters = searchTerm || filterStatus !== 'all' || filterAction !== 'all'

  const resetFilters = () => {
    setSearchTerm('')
    setFilterStatus('all')
    setFilterAction('all')
  }

  // Unique action references for filter dropdown
  const uniqueActions = useMemo(() => {
    const refs = [...new Set(commands.map(c => c.action_reference).filter(Boolean))]
    return refs.sort()
  }, [commands])

  // Stats
  const enabledCount = commands.filter(c => c.status === 'enabled').length
  const disabledCount = commands.filter(c => c.status !== 'enabled').length

  // Table columns
  const columns = [
    {
      id: 'command_name',
      accessorKey: 'command_name',
      header: __('Name'),
      cell: ({ row }) => (
        <span className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">
          {row.command_name}
        </span>
      ),
    },
    {
      id: 'action_reference',
      accessorKey: 'action_reference',
      header: __('Action'),
      cell: ({ row }) => (
        <span className="wsms-text-[12px] wsms-text-muted-foreground">
          {row.action_reference || '—'}
        </span>
      ),
    },
    {
      id: 'response_preview',
      accessorKey: 'response_preview',
      header: __('Response Preview'),
      cell: ({ value }) => (
        <span className="wsms-text-[12px] wsms-max-w-xs wsms-truncate wsms-block wsms-text-muted-foreground">
          {value || '—'}
        </span>
      ),
    },
    {
      id: 'status',
      accessorKey: 'status',
      header: __('Status'),
      cell: ({ value }) => value === 'enabled'
        ? <StatusBadge variant="active">{__('Enabled')}</StatusBadge>
        : <StatusBadge variant="inactive">{__('Disabled')}</StatusBadge>,
    },
  ]

  // Row actions
  const rowActions = [
    {
      label: __('Toggle'),
      icon: Power,
      onClick: handleToggle,
      loading: (row) => togglingId === row.id,
    },
    {
      label: __('Edit'),
      icon: Edit,
      onClick: handleEditCommand,
    },
    {
      label: __('Delete'),
      icon: Trash2,
      variant: 'destructive',
      onClick: handleDeleteClick,
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
                <Terminal className="wsms-h-8 wsms-w-8 wsms-text-primary" strokeWidth={1.5} />
              </div>
              <h3 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-2">
                {__('Two-Way SMS Add-on Required')}
              </h3>
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-6">
                {__('Install and activate the WP SMS Two-Way add-on to create auto-reply commands.')}
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

  if (isInitialLoad) {
    return <PageLoadingSkeleton />
  }

  // Empty state - no commands at all
  if (commands.length === 0) {
    return (
      <div className="wsms-space-y-6 wsms-stagger-children">
        <Card className="wsms-border-dashed">
          <CardContent className="wsms-py-16">
            <div className="wsms-flex wsms-flex-col wsms-items-center wsms-text-center wsms-max-w-md wsms-mx-auto">
              <div className="wsms-flex wsms-h-16 wsms-w-16 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-primary/10 wsms-mb-6">
                <Terminal className="wsms-h-8 wsms-w-8 wsms-text-primary" strokeWidth={1.5} />
              </div>
              <h3 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-2">
                {__('No commands yet')}
              </h3>
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-6">
                {__('Create auto-reply rules that trigger actions when subscribers send specific keywords via SMS.')}
              </p>
              <Button onClick={() => commandDialog.open()}>
                <Plus className="wsms-h-4 wsms-w-4 wsms-mr-2" aria-hidden="true" />
                {__('Create First Command')}
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    )
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
                <Terminal className="wsms-h-5 wsms-w-5 wsms-text-primary" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">{commands.length}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Total')}</p>
              </div>
            </div>

            <div className="wsms-hidden xl:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* Enabled */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-success/10">
                <CheckCircle className="wsms-h-5 wsms-w-5 wsms-text-success" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-success">{enabledCount}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Enabled')}</p>
              </div>
            </div>

            <div className="wsms-hidden xl:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* Disabled */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-slate-200 dark:wsms-bg-slate-700">
                <XCircle className="wsms-h-5 wsms-w-5 wsms-text-slate-500 dark:wsms-text-slate-400" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-muted-foreground">{disabledCount}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Disabled')}</p>
              </div>
            </div>
          </div>

          {/* Actions */}
          <div className="wsms-col-span-2 xl:wsms-col-span-1 wsms-flex wsms-items-center wsms-justify-end wsms-gap-2 wsms-mt-2 xl:wsms-mt-0">
            <Button onClick={() => commandDialog.open()}>
              <Plus className="wsms-h-4 wsms-w-4 wsms-mr-2" aria-hidden="true" />
              {__('New Command')}
            </Button>
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
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                placeholder={__('Search commands...')}
                className="wsms-pl-8 wsms-h-9"
                aria-label={__('Search commands')}
              />
            </div>

            {/* Filters */}
            <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 xl:wsms-flex xl:wsms-items-center xl:wsms-gap-2">
              <Select value={filterStatus} onValueChange={setFilterStatus}>
                <SelectTrigger className="wsms-h-9 wsms-w-full xl:wsms-w-[120px] wsms-text-[12px]" aria-label={__('Filter by status')}>
                  <SelectValue placeholder={__('All Status')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{__('All Status')}</SelectItem>
                  <SelectItem value="enabled">{__('Enabled')}</SelectItem>
                  <SelectItem value="disabled">{__('Disabled')}</SelectItem>
                </SelectContent>
              </Select>

              <Select value={filterAction} onValueChange={setFilterAction}>
                <SelectTrigger className="wsms-h-9 wsms-w-full xl:wsms-w-[160px] wsms-text-[12px]" aria-label={__('Filter by action')}>
                  <SelectValue placeholder={__('All Actions')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{__('All Actions')}</SelectItem>
                  {uniqueActions.map((ref) => (
                    <SelectItem key={ref} value={ref}>
                      {ref}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            </div>

            {/* Actions */}
            <div className="wsms-flex wsms-items-center wsms-gap-2 xl:wsms-ml-auto">
              {hasActiveFilters && (
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={resetFilters}
                  className="wsms-h-9 wsms-px-2.5 wsms-text-muted-foreground hover:wsms-text-foreground"
                  aria-label={__('Clear all filters')}
                >
                  <X className="wsms-h-4 wsms-w-4" aria-hidden="true" />
                </Button>
              )}
              <Button
                variant="outline"
                size="sm"
                onClick={fetchCommands}
                className="wsms-h-9 wsms-px-2.5"
                aria-label={__('Refresh commands')}
              >
                <RefreshCw
                  className={cn('wsms-h-4 wsms-w-4', isLoading && 'wsms-animate-spin')}
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
            data={filteredCommands}
            loading={isLoading}
            rowActions={rowActions}
            emptyMessage={__('No commands match your filters')}
            emptyIcon={Terminal}
          />
        </CardContent>
      </Card>

      {/* Create/Edit Dialog */}
      <Dialog open={commandDialog.isOpen} onOpenChange={(open) => !open && commandDialog.close()}>
        <DialogContent className="sm:wsms-max-w-[500px]">
          <DialogHeader>
            <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Terminal className="wsms-h-4 wsms-w-4 wsms-text-primary" aria-hidden="true" />
              {commandDialog.isEdit ? __('Edit Command') : __('New Command')}
            </DialogTitle>
            <DialogDescription>
              {commandDialog.isEdit
                ? __('Update the auto-reply rule settings')
                : __('Create a new auto-reply rule for incoming messages')}
            </DialogDescription>
          </DialogHeader>
          {isEditLoading ? (
            <DialogLoadingSpinner />
          ) : (
          <DialogBody overflow="visible">
            <div className="wsms-space-y-4">
              <div className="wsms-space-y-2">
                <label className="wsms-text-[12px] wsms-font-medium">{__('Command Name')}</label>
                <Input
                  id="command-name"
                  placeholder={__('e.g., HELP, INFO, STOP')}
                  value={commandDialog.formData.command_name}
                  onChange={(e) => commandDialog.updateField('command_name', e.target.value)}
                />
                {commandDialog.hasError('command_name') && (
                  <p className="wsms-text-[11px] wsms-text-destructive">{commandDialog.getError('command_name')}</p>
                )}
                <p className="wsms-text-[11px] wsms-text-muted-foreground">
                  {__('The keyword that triggers this command when received')}
                </p>
              </div>

              <div className="wsms-space-y-2">
                <label className="wsms-text-[12px] wsms-font-medium">{__('Action')}</label>
                <Select
                  key={`action-${isActionsLoading}-${commandDialog.formData.action_reference}`}
                  value={commandDialog.formData.action_reference}
                  onValueChange={(value) => commandDialog.updateField('action_reference', value)}
                >
                  <SelectTrigger>
                    <SelectValue placeholder={__('Select an action')} />
                  </SelectTrigger>
                  <SelectContent>
                    {isActionsLoading ? (
                      <div className="wsms-px-2 wsms-py-1.5 wsms-text-[12px] wsms-text-muted-foreground">
                        {__('Loading actions...')}
                      </div>
                    ) : actions.length === 0 ? (
                      <div className="wsms-px-2 wsms-py-1.5 wsms-text-[12px] wsms-text-muted-foreground">
                        {__('No actions available')}
                      </div>
                    ) : (
                      actions.map((group) => (
                        <SelectGroup key={group.name}>
                          <SelectLabel>{group.label}</SelectLabel>
                          {Array.isArray(group.actions) && group.actions.map((action) => (
                            <SelectItem key={action.reference} value={action.reference}>
                              {action.label}
                            </SelectItem>
                          ))}
                        </SelectGroup>
                      ))
                    )}
                  </SelectContent>
                </Select>
                {commandDialog.hasError('action_reference') && (
                  <p className="wsms-text-[11px] wsms-text-destructive">{commandDialog.getError('action_reference')}</p>
                )}
              </div>

              <div className="wsms-space-y-2">
                <label className="wsms-text-[12px] wsms-font-medium">{__('Success Response')}</label>
                <Textarea
                  id="success-response"
                  placeholder={__('Message sent when the action succeeds')}
                  rows={3}
                  value={commandDialog.formData.response_data.success.text}
                  onChange={(e) =>
                    commandDialog.updateField('response_data', {
                      ...commandDialog.formData.response_data,
                      success: { text: e.target.value },
                    })
                  }
                />
                {successVariables.length > 0 && (
                  <div className="wsms-flex wsms-flex-wrap wsms-items-center wsms-gap-1.5 wsms-mt-2">
                    <span className="wsms-text-[11px] wsms-text-muted-foreground wsms-mr-1">{__('Insert')}:</span>
                    {successVariables.map(v => (
                      <button
                        key={v}
                        type="button"
                        className="wsms-inline-flex wsms-items-center wsms-rounded wsms-border wsms-border-border wsms-px-1.5 wsms-py-0.5 wsms-text-[11px] wsms-font-mono wsms-text-muted-foreground wsms-bg-muted/30 hover:wsms-bg-primary/10 hover:wsms-border-primary hover:wsms-text-primary wsms-transition-colors wsms-cursor-pointer focus:wsms-outline-none focus:wsms-ring-2 focus:wsms-ring-primary/20"
                        onClick={() => {
                          const newText = commandDialog.formData.response_data.success.text + `{${v}}`
                          commandDialog.updateField('response_data', {
                            ...commandDialog.formData.response_data,
                            success: { text: newText },
                          })
                        }}
                        title={__('Click to insert')}
                      >
                        {`{${v}}`}
                      </button>
                    ))}
                  </div>
                )}
                {!commandDialog.formData.action_reference && (
                  <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mt-1">
                    {__('Select an action to see available variables')}
                  </p>
                )}
              </div>

              <div className="wsms-space-y-2">
                <label className="wsms-text-[12px] wsms-font-medium">{__('Failure Response')}</label>
                <Textarea
                  id="failure-response"
                  placeholder={__('Message sent when the action fails')}
                  rows={3}
                  value={commandDialog.formData.response_data.failure.text}
                  onChange={(e) =>
                    commandDialog.updateField('response_data', {
                      ...commandDialog.formData.response_data,
                      failure: { text: e.target.value },
                    })
                  }
                />
                {failureVariables.length > 0 && (
                  <div className="wsms-flex wsms-flex-wrap wsms-items-center wsms-gap-1.5 wsms-mt-2">
                    <span className="wsms-text-[11px] wsms-text-muted-foreground wsms-mr-1">{__('Insert')}:</span>
                    {failureVariables.map(v => (
                      <button
                        key={v}
                        type="button"
                        className="wsms-inline-flex wsms-items-center wsms-rounded wsms-border wsms-border-border wsms-px-1.5 wsms-py-0.5 wsms-text-[11px] wsms-font-mono wsms-text-muted-foreground wsms-bg-muted/30 hover:wsms-bg-primary/10 hover:wsms-border-primary hover:wsms-text-primary wsms-transition-colors wsms-cursor-pointer focus:wsms-outline-none focus:wsms-ring-2 focus:wsms-ring-primary/20"
                        onClick={() => {
                          const newText = commandDialog.formData.response_data.failure.text + `{${v}}`
                          commandDialog.updateField('response_data', {
                            ...commandDialog.formData.response_data,
                            failure: { text: newText },
                          })
                        }}
                        title={__('Click to insert')}
                      >
                        {`{${v}}`}
                      </button>
                    ))}
                  </div>
                )}
              </div>

              <div className="wsms-flex wsms-items-center wsms-justify-between">
                <div>
                  <label className="wsms-text-[12px] wsms-font-medium">{__('Status')}</label>
                  <p className="wsms-text-[11px] wsms-text-muted-foreground">
                    {__('Enable or disable this command')}
                  </p>
                </div>
                <Switch
                  id="command-status"
                  checked={commandDialog.formData.status === 'enabled'}
                  onCheckedChange={(checked) =>
                    commandDialog.updateField('status', checked ? 'enabled' : 'disabled')
                  }
                />
              </div>
            </div>
          </DialogBody>
          )}
          <DialogFooter>
            <Button variant="outline" onClick={commandDialog.close}>
              {__('Cancel')}
            </Button>
            <Button onClick={commandDialog.save} disabled={commandDialog.isSaving || isEditLoading}>
              {commandDialog.isSaving ? (
                <>
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" aria-hidden="true" />
                  {__('Saving...')}
                </>
              ) : commandDialog.isEdit ? __('Save Changes') : __('Create Command')}
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
        title={__('Delete Command')}
        description={__('Are you sure you want to delete this command?')}
      >
        <div className="wsms-p-4 wsms-rounded-md wsms-bg-muted/50 wsms-border wsms-border-border">
          <div className="wsms-space-y-1">
            <p className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">
              {deleteDialog.item?.command_name}
            </p>
            <p className="wsms-text-[12px] wsms-text-muted-foreground">
              {deleteDialog.item?.action_reference}
            </p>
          </div>
        </div>
      </DeleteConfirmDialog>
    </div>
  )
}
