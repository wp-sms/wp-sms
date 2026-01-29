import React, { useState, useEffect, useCallback, useMemo } from 'react'
import { Terminal, AlertCircle, ExternalLink, Plus, Edit, Trash2, Power, RefreshCw } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { DataTable } from '@/components/ui/data-table'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { DeleteConfirmDialog } from '@/components/shared/DeleteConfirmDialog'
import { PageLoadingSkeleton } from '@/components/ui/skeleton'
import { useFormDialog } from '@/hooks/useFormDialog'
import { useSettings } from '@/context/SettingsContext'
import { useToast } from '@/components/ui/toaster'
import { __ } from '@/lib/utils'
import { commandsApi } from '@/api/twoWayApi'
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
      name: '',
      action_reference: '',
      status: 'enabled',
      response_data: {
        success: { text: '' },
        failure: { text: '' },
      },
    },
    validate: (data) => {
      const errors = {}
      if (!data.name?.trim()) errors.name = __('Command name is required')
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

  // Open for edit - map command fields to form fields
  const handleEditCommand = useCallback((command) => {
    commandDialog.open({
      id: command.id,
      name: command.command_name || '',
      action_reference: command.action_reference || '',
      status: command.status || 'enabled',
      response_data: {
        success: { text: command.response_data?.success?.text || '' },
        failure: { text: command.response_data?.failure?.text || '' },
      },
    })
  }, [commandDialog])

  // Toggle command status
  const handleToggle = async (command) => {
    try {
      const response = await commandsApi.toggleCommand(command.id)
      if (response.success) {
        toast({
          title: response.data?.status === 'enabled' ? __('Command enabled') : __('Command disabled'),
          variant: 'success',
        })
        fetchCommands()
      }
    } catch (error) {
      toast({ title: error.message || __('Failed to toggle command'), variant: 'destructive' })
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

  // Table columns
  const columns = [
    {
      id: 'command_name',
      accessorKey: 'command_name',
      header: __('Name'),
      cellClassName: 'wsms-font-medium',
    },
    {
      id: 'action_reference',
      accessorKey: 'action_reference',
      header: __('Action'),
      cellClassName: 'wsms-text-muted-foreground',
    },
    {
      id: 'response_preview',
      accessorKey: 'response_preview',
      header: __('Response Preview'),
      cell: ({ value }) => (
        <span className="wsms-max-w-xs wsms-truncate wsms-block wsms-text-muted-foreground">
          {value || '-'}
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
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Terminal className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              {__('Auto-Reply Commands')}
            </CardTitle>
            <CardDescription>
              {__('Set up automatic responses to incoming SMS messages')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-6 wsms-text-center">
              <AlertCircle className="wsms-mx-auto wsms-h-10 wsms-w-10 wsms-text-muted-foreground wsms-mb-3" />
              <h3 className="wsms-font-medium wsms-mb-2">{__('Two-Way SMS Add-on Required')}</h3>
              <p className="wsms-text-sm wsms-text-muted-foreground wsms-mb-4">
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

  if (isLoading && commands.length === 0) {
    return <PageLoadingSkeleton />
  }

  return (
    <div className="wsms-space-y-6 wsms-stagger-children">
      {/* Page Header */}
      <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-4">
        <div>
          <h1 className="wsms-text-xl wsms-font-semibold wsms-text-foreground wsms-flex wsms-items-center wsms-gap-2">
            <Terminal className="wsms-h-5 wsms-w-5" />
            {__('Commands')}
          </h1>
          <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mt-1">
            {__('Create auto-reply rules that trigger actions based on incoming SMS keywords.')}
          </p>
        </div>
        <div className="wsms-flex wsms-items-center wsms-gap-2">
          <Button variant="outline" size="icon" onClick={fetchCommands}>
            <RefreshCw className="wsms-h-4 wsms-w-4" />
          </Button>
          <Button onClick={() => commandDialog.open()}>
            <Plus className="wsms-h-4 wsms-w-4 wsms-mr-1" />
            {__('New Command')}
          </Button>
        </div>
      </div>

      {/* Commands Table */}
      <Card>
        <CardContent className="wsms-p-0">
          <DataTable
            columns={columns}
            data={commands}
            loading={isLoading}
            rowActions={rowActions}
            emptyMessage={__('No commands yet. Create your first auto-reply rule.')}
            emptyIcon={Terminal}
          />
        </CardContent>
      </Card>

      {/* Create/Edit Dialog */}
      <Dialog open={commandDialog.isOpen} onOpenChange={(open) => !open && commandDialog.close()}>
        <DialogContent className="sm:wsms-max-w-[500px]">
          <DialogHeader>
            <DialogTitle>
              {commandDialog.isEdit ? __('Edit Command') : __('New Command')}
            </DialogTitle>
            <DialogDescription>
              {commandDialog.isEdit
                ? __('Update the auto-reply rule settings')
                : __('Create a new auto-reply rule for incoming messages')}
            </DialogDescription>
          </DialogHeader>
          <div className="wsms-px-6 wsms-pb-2 wsms-space-y-4">
            <div className="wsms-space-y-2">
              <Label htmlFor="command-name">{__('Command Name')}</Label>
              <Input
                id="command-name"
                placeholder={__('e.g., HELP, INFO, STOP')}
                value={commandDialog.formData.name}
                onChange={(e) => commandDialog.updateField('name', e.target.value)}
              />
              {commandDialog.hasError('name') && (
                <p className="wsms-text-xs wsms-text-destructive">{commandDialog.getError('name')}</p>
              )}
              <p className="wsms-text-xs wsms-text-muted-foreground">
                {__('The keyword that triggers this command when received')}
              </p>
            </div>

            <div className="wsms-space-y-2">
              <Label htmlFor="action">{__('Action')}</Label>
              <Select
                value={commandDialog.formData.action_reference}
                onValueChange={(value) => commandDialog.updateField('action_reference', value)}
              >
                <SelectTrigger>
                  <SelectValue placeholder={__('Select an action')} />
                </SelectTrigger>
                <SelectContent>
                  {isActionsLoading ? (
                    <div className="wsms-px-2 wsms-py-1.5 wsms-text-sm wsms-text-muted-foreground">
                      {__('Loading actions...')}
                    </div>
                  ) : actions.length === 0 ? (
                    <div className="wsms-px-2 wsms-py-1.5 wsms-text-sm wsms-text-muted-foreground">
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
                <p className="wsms-text-xs wsms-text-destructive">{commandDialog.getError('action_reference')}</p>
              )}
            </div>

            <div className="wsms-space-y-2">
              <Label htmlFor="success-response">{__('Success Response')}</Label>
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
                  <span className="wsms-text-xs wsms-text-muted-foreground wsms-mr-1">{__('Insert')}:</span>
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
                <p className="wsms-text-xs wsms-text-muted-foreground wsms-mt-1">
                  {__('Select an action to see available variables')}
                </p>
              )}
            </div>

            <div className="wsms-space-y-2">
              <Label htmlFor="failure-response">{__('Failure Response')}</Label>
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
                  <span className="wsms-text-xs wsms-text-muted-foreground wsms-mr-1">{__('Insert')}:</span>
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
                <Label htmlFor="command-status">{__('Status')}</Label>
                <p className="wsms-text-xs wsms-text-muted-foreground">
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
          <DialogFooter>
            <Button variant="outline" onClick={commandDialog.close}>
              {__('Cancel')}
            </Button>
            <Button onClick={commandDialog.save} disabled={commandDialog.isSaving}>
              {commandDialog.isSaving ? __('Saving...') : commandDialog.isEdit ? __('Update') : __('Create')}
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
        title={__('Delete Command?')}
        description={__('Are you sure you want to delete the "%s" command? This action cannot be undone.').replace('%s', deleteDialog.item?.command_name || '')}
      />
    </div>
  )
}
