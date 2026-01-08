import React, { useState, useEffect, useCallback, useMemo } from 'react'
import { Terminal, AlertCircle, ExternalLink, Plus, Edit, Trash2, Power, RefreshCw } from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Badge } from '@/components/ui/badge'
import { Label } from '@/components/ui/label'
import { useSettings } from '@/context/SettingsContext'
import { useToast } from '@/components/ui/toaster'
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

  // Check if Two-Way add-on is active
  const hasTwoWay = isAddonActive('two-way')

  // State
  const [commands, setCommands] = useState([])
  const [actions, setActions] = useState([])
  const [isLoading, setIsLoading] = useState(true)
  const [isActionsLoading, setIsActionsLoading] = useState(true)
  const [editingCommand, setEditingCommand] = useState(null)
  const [isDialogOpen, setIsDialogOpen] = useState(false)
  const [deleteConfirm, setDeleteConfirm] = useState(null)
  const [isSaving, setIsSaving] = useState(false)

  // Form state
  const [formData, setFormData] = useState({
    name: '',
    action_reference: '',
    status: 'enabled',
    response_data: {
      success: { text: '' },
      failure: { text: '' },
    },
  })

  // Compute selected action and its variables
  const selectedActionData = useMemo(() => {
    if (!formData.action_reference || !actions.length) {
      return null
    }
    const action = actions
      .flatMap(g => g.actions || [])
      .find(a => a.reference === formData.action_reference)
    return action || null
  }, [formData.action_reference, actions])

  const successVariables = useMemo(() => {
    if (!selectedActionData) return []
    return [
      ...(selectedActionData.globalVariables || []),
      ...(selectedActionData.variables || [])
    ]
  }, [selectedActionData])

  const failureVariables = useMemo(() => {
    if (!selectedActionData) return []
    return selectedActionData.failureVariables || []
  }, [selectedActionData])

  // Fetch commands
  const fetchCommands = useCallback(async () => {
    try {
      setIsLoading(true)
      const response = await commandsApi.getCommands()

      if (response.success) {
        setCommands(Array.isArray(response.data?.commands) ? response.data.commands : [])
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: error.message || 'Failed to load commands',
        variant: 'destructive',
      })
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
    } catch (error) {
      console.error('Failed to fetch actions:', error)
    } finally {
      setIsActionsLoading(false)
    }
  }, [])

  // Load data on mount
  useEffect(() => {
    if (hasTwoWay) {
      fetchCommands()
      fetchActions()
    }
  }, [hasTwoWay, fetchCommands, fetchActions])

  // Reset form
  const resetForm = () => {
    setFormData({
      name: '',
      action_reference: '',
      status: 'enabled',
      response_data: {
        success: { text: '' },
        failure: { text: '' },
      },
    })
    setEditingCommand(null)
  }

  // Open dialog for new command
  const handleNewCommand = () => {
    resetForm()
    setIsDialogOpen(true)
  }

  // Open dialog for editing
  const handleEditCommand = async (command) => {
    setEditingCommand(command)
    setFormData({
      name: command.command_name || '',
      action_reference: command.action_reference || '',
      status: command.status || 'enabled',
      response_data: {
        success: { text: command.response_data?.success?.text || '' },
        failure: { text: command.response_data?.failure?.text || '' },
      },
    })
    setIsDialogOpen(true)
  }

  // Save command
  const handleSave = async () => {
    if (!formData.name.trim()) {
      toast({
        title: 'Error',
        description: 'Command name is required',
        variant: 'destructive',
      })
      return
    }

    if (!formData.action_reference) {
      toast({
        title: 'Error',
        description: 'Please select an action',
        variant: 'destructive',
      })
      return
    }

    try {
      setIsSaving(true)

      let response
      if (editingCommand) {
        response = await commandsApi.updateCommand(editingCommand.id, formData)
      } else {
        response = await commandsApi.createCommand(formData)
      }

      if (response.success) {
        toast({
          title: 'Success',
          description: editingCommand ? 'Command updated successfully' : 'Command created successfully',
        })
        setIsDialogOpen(false)
        resetForm()
        fetchCommands()
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: error.message || 'Failed to save command',
        variant: 'destructive',
      })
    } finally {
      setIsSaving(false)
    }
  }

  // Toggle command status
  const handleToggle = async (command) => {
    try {
      const response = await commandsApi.toggleCommand(command.id)

      if (response.success) {
        toast({
          title: 'Success',
          description: `Command ${response.data?.status === 'enabled' ? 'enabled' : 'disabled'}`,
        })
        fetchCommands()
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: error.message || 'Failed to toggle command',
        variant: 'destructive',
      })
    }
  }

  // Delete command
  const handleDelete = async (id) => {
    try {
      const response = await commandsApi.deleteCommand(id)

      if (response.success) {
        toast({
          title: 'Success',
          description: 'Command deleted',
        })
        fetchCommands()
      }
    } catch (error) {
      toast({
        title: 'Error',
        description: error.message || 'Failed to delete command',
        variant: 'destructive',
      })
    }
    setDeleteConfirm(null)
  }

  // Show placeholder if Two-Way add-on is not active
  if (!hasTwoWay) {
    return (
      <div className="wsms-space-y-6">
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Terminal className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              Auto-Reply Commands
            </CardTitle>
            <CardDescription>
              Set up automatic responses to incoming SMS messages
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-6 wsms-text-center">
              <AlertCircle className="wsms-mx-auto wsms-h-10 wsms-w-10 wsms-text-muted-foreground wsms-mb-3" />
              <h3 className="wsms-font-medium wsms-mb-2">Two-Way SMS Add-on Required</h3>
              <p className="wsms-text-sm wsms-text-muted-foreground wsms-mb-4">
                Install and activate the WP SMS Two-Way add-on to create auto-reply commands.
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
          <Terminal className="wsms-h-6 wsms-w-6" />
          Commands
        </h1>
        <p className="wsms-text-muted-foreground wsms-mt-1">
          Create auto-reply rules that trigger actions based on incoming SMS keywords.
        </p>
      </div>

      {/* Commands Table */}
      <Card>
        <CardHeader>
          <div className="wsms-flex wsms-flex-col sm:wsms-flex-row wsms-items-start sm:wsms-items-center wsms-justify-between wsms-gap-4">
            <CardTitle>Auto-Reply Rules</CardTitle>
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <Button
                variant="outline"
                size="icon"
                onClick={fetchCommands}
              >
                <RefreshCw className="wsms-h-4 wsms-w-4" />
              </Button>
              <Button onClick={handleNewCommand}>
                <Plus className="wsms-h-4 wsms-w-4 wsms-mr-1" />
                New Command
              </Button>
            </div>
          </div>
        </CardHeader>
        <CardContent>
          {isLoading ? (
            <div className="wsms-text-center wsms-py-8 wsms-text-muted-foreground">
              Loading commands...
            </div>
          ) : commands.length === 0 ? (
            <div className="wsms-text-center wsms-py-8 wsms-text-muted-foreground">
              <Terminal className="wsms-mx-auto wsms-h-10 wsms-w-10 wsms-mb-3 wsms-opacity-50" />
              <p className="wsms-mb-4">No commands yet. Create your first auto-reply rule.</p>
              <Button onClick={handleNewCommand}>
                <Plus className="wsms-h-4 wsms-w-4 wsms-mr-1" />
                New Command
              </Button>
            </div>
          ) : (
            <div className="wsms-overflow-x-auto">
              <table className="wsms-w-full">
                <thead>
                  <tr className="wsms-border-b">
                    <th className="wsms-p-2 wsms-text-left wsms-font-medium">Name</th>
                    <th className="wsms-p-2 wsms-text-left wsms-font-medium">Action</th>
                    <th className="wsms-p-2 wsms-text-left wsms-font-medium">Response Preview</th>
                    <th className="wsms-p-2 wsms-text-left wsms-font-medium">Status</th>
                    <th className="wsms-p-2 wsms-text-right wsms-font-medium">Actions</th>
                  </tr>
                </thead>
                <tbody>
                  {commands.map((command) => (
                    <tr key={command.id} className="wsms-border-b hover:wsms-bg-muted/50">
                      <td className="wsms-p-2 wsms-font-medium">
                        {command.command_name}
                      </td>
                      <td className="wsms-p-2 wsms-text-sm wsms-text-muted-foreground">
                        {command.action_reference}
                      </td>
                      <td className="wsms-p-2 wsms-max-w-xs wsms-truncate wsms-text-sm wsms-text-muted-foreground">
                        {command.response_preview || '-'}
                      </td>
                      <td className="wsms-p-2">
                        {command.status === 'enabled' ? (
                          <Badge className="wsms-bg-green-100 wsms-text-green-800">Enabled</Badge>
                        ) : (
                          <Badge variant="outline">Disabled</Badge>
                        )}
                      </td>
                      <td className="wsms-p-2 wsms-text-right">
                        <div className="wsms-flex wsms-items-center wsms-justify-end wsms-gap-1">
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => handleToggle(command)}
                            title={command.status === 'enabled' ? 'Disable' : 'Enable'}
                          >
                            <Power className={`wsms-h-4 wsms-w-4 ${command.status === 'enabled' ? 'wsms-text-green-600' : ''}`} />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => handleEditCommand(command)}
                            title="Edit"
                          >
                            <Edit className="wsms-h-4 wsms-w-4" />
                          </Button>
                          <Button
                            variant="ghost"
                            size="icon"
                            onClick={() => setDeleteConfirm(command)}
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
          )}
        </CardContent>
      </Card>

      {/* Create/Edit Dialog */}
      <Dialog open={isDialogOpen} onOpenChange={(open) => {
        if (!open) {
          setIsDialogOpen(false)
          resetForm()
        }
      }}>
        <DialogContent className="sm:wsms-max-w-[500px]">
          <DialogHeader>
            <DialogTitle>
              {editingCommand ? 'Edit Command' : 'New Command'}
            </DialogTitle>
            <DialogDescription>
              {editingCommand
                ? 'Update the auto-reply rule settings'
                : 'Create a new auto-reply rule for incoming messages'}
            </DialogDescription>
          </DialogHeader>
          <div className="wsms-px-6 wsms-pb-2 wsms-space-y-4">
            <div className="wsms-space-y-2">
              <Label htmlFor="command-name">Command Name</Label>
              <Input
                id="command-name"
                placeholder="e.g., HELP, INFO, STOP"
                value={formData.name}
                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
              />
              <p className="wsms-text-xs wsms-text-muted-foreground">
                The keyword that triggers this command when received
              </p>
            </div>

            <div className="wsms-space-y-2">
              <Label htmlFor="action">Action</Label>
              <Select
                value={formData.action_reference}
                onValueChange={(value) => setFormData({ ...formData, action_reference: value })}
              >
                <SelectTrigger>
                  <SelectValue placeholder="Select an action" />
                </SelectTrigger>
                <SelectContent>
                  {isActionsLoading ? (
                    <div className="wsms-px-2 wsms-py-1.5 wsms-text-sm wsms-text-muted-foreground">
                      Loading actions...
                    </div>
                  ) : actions.length === 0 ? (
                    <div className="wsms-px-2 wsms-py-1.5 wsms-text-sm wsms-text-muted-foreground">
                      No actions available
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
            </div>

            <div className="wsms-space-y-2">
              <Label htmlFor="success-response">Success Response</Label>
              <Textarea
                id="success-response"
                placeholder="Message sent when the action succeeds"
                rows={3}
                value={formData.response_data.success.text}
                onChange={(e) =>
                  setFormData({
                    ...formData,
                    response_data: {
                      ...formData.response_data,
                      success: { text: e.target.value },
                    },
                  })
                }
              />
              {/* Dynamic variables for success response */}
              {successVariables.length > 0 && (
                <div className="wsms-flex wsms-flex-wrap wsms-items-center wsms-gap-1.5 wsms-mt-2">
                  <span className="wsms-text-xs wsms-text-muted-foreground wsms-mr-1">Insert:</span>
                  {successVariables.map(v => (
                    <button
                      key={v}
                      type="button"
                      className="wsms-inline-flex wsms-items-center wsms-rounded wsms-border wsms-border-border wsms-px-1.5 wsms-py-0.5 wsms-text-[11px] wsms-font-mono wsms-text-muted-foreground wsms-bg-muted/30 hover:wsms-bg-primary/10 hover:wsms-border-primary hover:wsms-text-primary wsms-transition-colors wsms-cursor-pointer focus:wsms-outline-none focus:wsms-ring-2 focus:wsms-ring-primary/20"
                      onClick={() => {
                        const newText = formData.response_data.success.text + `{${v}}`
                        setFormData({
                          ...formData,
                          response_data: {
                            ...formData.response_data,
                            success: { text: newText },
                          },
                        })
                      }}
                      title="Click to insert"
                    >
                      {`{${v}}`}
                    </button>
                  ))}
                </div>
              )}
              {!formData.action_reference && (
                <p className="wsms-text-xs wsms-text-muted-foreground wsms-mt-1">
                  Select an action to see available variables
                </p>
              )}
            </div>

            <div className="wsms-space-y-2">
              <Label htmlFor="failure-response">Failure Response</Label>
              <Textarea
                id="failure-response"
                placeholder="Message sent when the action fails"
                rows={3}
                value={formData.response_data.failure.text}
                onChange={(e) =>
                  setFormData({
                    ...formData,
                    response_data: {
                      ...formData.response_data,
                      failure: { text: e.target.value },
                    },
                  })
                }
              />
              {/* Dynamic variables for failure response */}
              {failureVariables.length > 0 && (
                <div className="wsms-flex wsms-flex-wrap wsms-items-center wsms-gap-1.5 wsms-mt-2">
                  <span className="wsms-text-xs wsms-text-muted-foreground wsms-mr-1">Insert:</span>
                  {failureVariables.map(v => (
                    <button
                      key={v}
                      type="button"
                      className="wsms-inline-flex wsms-items-center wsms-rounded wsms-border wsms-border-border wsms-px-1.5 wsms-py-0.5 wsms-text-[11px] wsms-font-mono wsms-text-muted-foreground wsms-bg-muted/30 hover:wsms-bg-primary/10 hover:wsms-border-primary hover:wsms-text-primary wsms-transition-colors wsms-cursor-pointer focus:wsms-outline-none focus:wsms-ring-2 focus:wsms-ring-primary/20"
                      onClick={() => {
                        const newText = formData.response_data.failure.text + `{${v}}`
                        setFormData({
                          ...formData,
                          response_data: {
                            ...formData.response_data,
                            failure: { text: newText },
                          },
                        })
                      }}
                      title="Click to insert"
                    >
                      {`{${v}}`}
                    </button>
                  ))}
                </div>
              )}
            </div>

            <div className="wsms-flex wsms-items-center wsms-justify-between">
              <div>
                <Label htmlFor="command-status">Status</Label>
                <p className="wsms-text-xs wsms-text-muted-foreground">
                  Enable or disable this command
                </p>
              </div>
              <Switch
                id="command-status"
                checked={formData.status === 'enabled'}
                onCheckedChange={(checked) =>
                  setFormData({ ...formData, status: checked ? 'enabled' : 'disabled' })
                }
              />
            </div>
          </div>
          <DialogFooter>
            <Button variant="outline" onClick={() => {
              setIsDialogOpen(false)
              resetForm()
            }}>
              Cancel
            </Button>
            <Button onClick={handleSave} disabled={isSaving}>
              {isSaving ? 'Saving...' : editingCommand ? 'Update' : 'Create'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation */}
      <AlertDialog open={!!deleteConfirm} onOpenChange={() => setDeleteConfirm(null)}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>Delete Command?</AlertDialogTitle>
            <AlertDialogDescription>
              Are you sure you want to delete the "{deleteConfirm?.command_name}" command? This action cannot be undone.
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
    </div>
  )
}
