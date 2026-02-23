import React, { useState, useEffect, useCallback, useMemo } from 'react'
import {
  Megaphone,
  Plus,
  Edit2,
  Trash2,
  Clock,
  CheckCircle2,
  XCircle,
  AlertCircle,
  Calendar,
  Timer,
  Send,
  Eye,
  ListOrdered,
  X,
  Save,
  Loader2,
  ExternalLink,
  RefreshCw,
  Search,
} from 'lucide-react'
import { Card, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
  DialogBody,
} from '@/components/ui/dialog'
import { Badge } from '@/components/ui/badge'
import { DataTable } from '@/components/ui/data-table'
import { DeleteConfirmDialog } from '@/components/shared/DeleteConfirmDialog'
import { AddonUpdateRequired } from '@/components/shared/AddonUpdateRequired'
import { DialogLoadingSpinner } from '@/components/shared/DialogLoadingSpinner'
import { PageLoadingSkeleton } from '@/components/ui/skeleton'
import { TemplateTextarea } from '@/components/shared/TemplateTextarea'
import { useSettings } from '@/context/SettingsContext'
import { useListPage } from '@/hooks/useListPage'
import { useFormDialog } from '@/hooks/useFormDialog'
import { useToast } from '@/components/ui/toaster'
import { woocommerceProApi } from '@/api/woocommerceProApi'
import { __, cn, formatDate, isAddonDashboardReady } from '@/lib/utils'

// Status badge component
const StatusBadge = ({ status }) => {
  const statusConfig = {
    publish: { label: __('Active'), icon: CheckCircle2, className: 'wsms-bg-green-100 wsms-text-green-800 dark:wsms-bg-green-900 dark:wsms-text-green-300' },
    draft: { label: __('Draft'), icon: Edit2, className: 'wsms-bg-gray-100 wsms-text-gray-800 dark:wsms-bg-gray-800 dark:wsms-text-gray-300' },
    pending: { label: __('Pending'), icon: Clock, className: 'wsms-bg-yellow-100 wsms-text-yellow-800 dark:wsms-bg-yellow-900 dark:wsms-text-yellow-300' },
  }

  const config = statusConfig[status] || statusConfig.draft
  const Icon = config.icon

  return (
    <Badge variant="outline" className={`wsms-inline-flex wsms-items-center wsms-gap-1 ${config.className}`}>
      <Icon className="wsms-h-3 wsms-w-3" />
      {config.label}
    </Badge>
  )
}

// Queue status badge
const QueueStatusBadge = ({ queueStatus, nextSchedule }) => {
  if (!queueStatus) {
    return <span className="wsms-text-muted-foreground wsms-text-[12px]">&mdash;</span>
  }

  const statusConfig = {
    pending: { label: __('Queued'), icon: Clock, className: 'wsms-bg-purple-100 wsms-text-purple-800' },
    processing: { label: __('Processing'), icon: RefreshCw, className: 'wsms-bg-blue-100 wsms-text-blue-800' },
    completed: { label: __('Completed'), icon: CheckCircle2, className: 'wsms-bg-green-100 wsms-text-green-800' },
    failed: { label: __('Failed'), icon: XCircle, className: 'wsms-bg-red-100 wsms-text-red-800' },
  }

  const config = statusConfig[queueStatus] || statusConfig.pending
  const Icon = config.icon

  return (
    <div className="wsms-flex wsms-flex-col wsms-gap-1">
      <Badge variant="outline" className={`wsms-inline-flex wsms-w-fit wsms-items-center wsms-gap-1 wsms-text-[11px] ${config.className}`}>
        <Icon className="wsms-h-3 wsms-w-3" />
        {config.label}
      </Badge>
      {nextSchedule && (
        <span className="wsms-text-[11px] wsms-text-muted-foreground">{nextSchedule}</span>
      )}
    </div>
  )
}

// Time specification display
const TimeSpecDisplay = ({ timeSpec, specificDate, delayedTime }) => {
  const defaultConfig = { label: __('Right Away'), icon: Send }
  const specConfig = {
    'right-away': defaultConfig,
    'specific-date': { label: __('Specific Date'), icon: Calendar },
    'after-placing-order': { label: __('After Placing Order'), icon: Timer },
  }

  const config = (timeSpec && specConfig[timeSpec]) || defaultConfig
  const Icon = config.icon

  return (
    <div className="wsms-flex wsms-flex-col wsms-gap-1">
      <div className="wsms-flex wsms-items-center wsms-gap-1.5 wsms-text-[12px]">
        <Icon className="wsms-h-3.5 wsms-w-3.5 wsms-text-muted-foreground" />
        <span>{config.label}</span>
      </div>
      {timeSpec === 'specific-date' && specificDate && (
        <span className="wsms-text-[11px] wsms-text-muted-foreground wsms-ms-5">{specificDate}</span>
      )}
      {timeSpec === 'after-placing-order' && delayedTime && (
        <span className="wsms-text-[11px] wsms-text-muted-foreground wsms-ms-5">
          {delayedTime.value} {delayedTime.unit}
        </span>
      )}
    </div>
  )
}

// Normalize conditions into group structure for AND/OR support
function normalizeConditionGroups(conditions) {
  if (!Array.isArray(conditions) || conditions.length === 0) return []
  // If already in group format [{condition: [...]}]
  if (conditions[0]?.condition) {
    return conditions.map(group => ({
      conditions: (group.condition || []).map(c => ({
        condition_key: c.condition_key || '',
        condition_value: c.condition_value || '',
      }))
    }))
  }
  // If flat format [{type, value}] (legacy React format) — convert to single AND group
  return [{
    conditions: conditions.map(c => ({
      condition_key: c.type || c.condition_key || '',
      condition_value: c.value || c.condition_value || '',
    }))
  }]
}

// Normalize delayed_time from backend {days, hours, minutes} to UI {value, unit}
function normalizeDelayedTime(dt) {
  if (!dt) return { value: 30, unit: 'minutes' }
  // Already in UI format
  if (dt.value !== undefined && dt.unit !== undefined) return dt
  // Convert from backend {days, hours, minutes} format
  const days = parseInt(dt.days) || 0
  const hours = parseInt(dt.hours) || 0
  const minutes = parseInt(dt.minutes) || 0
  if (days > 0 && hours === 0 && minutes === 0) return { value: days, unit: 'days' }
  if (hours > 0 && days === 0 && minutes === 0) return { value: hours, unit: 'hours' }
  // Mixed or minutes-only: convert to total minutes
  const totalMinutes = days * 1440 + hours * 60 + minutes
  return { value: totalMinutes || 30, unit: 'minutes' }
}

// Convert UI {value, unit} to backend {days, hours, minutes}
function serializeDelayedTime(dt) {
  const val = parseInt(dt.value) || 0
  if (dt.unit === 'days') return { days: val, hours: 0, minutes: 0 }
  if (dt.unit === 'hours') return { days: 0, hours: val, minutes: 0 }
  return { days: 0, hours: 0, minutes: val }
}

// Campaign form component
const CampaignForm = ({ campaign, conditionOptions, timeSpecifications, messageVariables, onSave, onCancel, isLoading, formId = 'campaign-form' }) => {
  const [formData, setFormData] = useState({
    title: campaign?.title || '',
    status: campaign?.status || 'draft',
    conditionGroups: normalizeConditionGroups(campaign?.conditions),
    time_specification: campaign?.time_specification || 'right-away',
    specific_date: campaign?.specific_date || '',
    delayed_time: normalizeDelayedTime(campaign?.delayed_time),
    message_content: campaign?.message_content || '',
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    const submitData = {
      ...formData,
      conditions: formData.conditionGroups.map(group => ({
        condition: group.conditions.map(c => ({
          condition_key: c.condition_key,
          condition_value: c.condition_value,
        }))
      })),
      delayed_time: serializeDelayedTime(formData.delayed_time),
    }
    delete submitData.conditionGroups
    onSave(submitData)
  }

  const updateField = (field, value) => {
    setFormData(prev => ({ ...prev, [field]: value }))
  }

  const addConditionGroup = () => {
    setFormData(prev => ({
      ...prev,
      conditionGroups: [...prev.conditionGroups, { conditions: [{ condition_key: '', condition_value: '' }] }]
    }))
  }

  const addConditionToGroup = (groupIndex) => {
    setFormData(prev => {
      const groups = [...prev.conditionGroups]
      groups[groupIndex] = {
        ...groups[groupIndex],
        conditions: [...groups[groupIndex].conditions, { condition_key: '', condition_value: '' }]
      }
      return { ...prev, conditionGroups: groups }
    })
  }

  const updateConditionInGroup = (groupIndex, condIndex, field, value) => {
    setFormData(prev => {
      const groups = [...prev.conditionGroups]
      const conditions = [...groups[groupIndex].conditions]
      conditions[condIndex] = { ...conditions[condIndex], [field]: value }
      groups[groupIndex] = { ...groups[groupIndex], conditions }
      return { ...prev, conditionGroups: groups }
    })
  }

  const removeConditionFromGroup = (groupIndex, condIndex) => {
    setFormData(prev => {
      const groups = [...prev.conditionGroups]
      const conditions = groups[groupIndex].conditions.filter((_, i) => i !== condIndex)
      if (conditions.length === 0) {
        return { ...prev, conditionGroups: groups.filter((_, i) => i !== groupIndex) }
      }
      groups[groupIndex] = { ...groups[groupIndex], conditions }
      return { ...prev, conditionGroups: groups }
    })
  }

  const getConditionValues = (type) => {
    if (!Array.isArray(conditionOptions)) return []
    const conditionType = conditionOptions.find(c => c.key === type)
    const options = conditionType?.options
    return Array.isArray(options) ? options : []
  }

  return (
    <>
      <form id={formId} onSubmit={handleSubmit} className="wsms-space-y-6 wsms-px-6">
        {/* Title */}
        <div className="wsms-space-y-2">
          <Label htmlFor="title">{__('Campaign Title')}</Label>
          <Input
            id="title"
            value={formData.title}
            onChange={(e) => updateField('title', e.target.value)}
            placeholder={__('Enter campaign title...')}
            required
          />
        </div>

        {/* Status */}
        <div className="wsms-space-y-2">
          <Label htmlFor="status">{__('Status')}</Label>
          <Select value={formData.status} onValueChange={(value) => updateField('status', value)}>
            <SelectTrigger id="status">
              <SelectValue />
            </SelectTrigger>
            <SelectContent>
              <SelectItem value="draft">{__('Draft')}</SelectItem>
              <SelectItem value="publish">{__('Active')}</SelectItem>
              <SelectItem value="pending">{__('Pending')}</SelectItem>
            </SelectContent>
          </Select>
        </div>

        {/* Conditions */}
        <div className="wsms-space-y-3">
          <Label>{__('Conditions')}</Label>

          {formData.conditionGroups.length === 0 && (
            <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-py-4 wsms-text-center wsms-border wsms-border-dashed wsms-rounded-lg">
              {__('No conditions added. Campaign will match all orders.')}
            </p>
          )}

          {formData.conditionGroups.map((group, groupIndex) => (
            <div key={groupIndex}>
              {groupIndex > 0 && (
                <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-my-2">
                  <div className="wsms-flex-1 wsms-border-t wsms-border-border" />
                  <span className="wsms-text-xs wsms-font-medium wsms-text-orange-600 wsms-bg-orange-50 wsms-px-2 wsms-py-0.5 wsms-rounded">{__('OR')}</span>
                  <div className="wsms-flex-1 wsms-border-t wsms-border-border" />
                </div>
              )}
              <div className="wsms-p-3 wsms-border wsms-border-border wsms-rounded-lg wsms-space-y-2">
                {groupIndex === 0 && (
                  <p className="wsms-text-xs wsms-text-muted-foreground wsms-mb-1">{__('Send SMS to orders if')}</p>
                )}
                {group.conditions.map((condition, condIndex) => (
                  <div key={condIndex}>
                    {condIndex > 0 && (
                      <div className="wsms-flex wsms-justify-center wsms-my-1">
                        <span className="wsms-text-[11px] wsms-font-medium wsms-text-blue-600 wsms-bg-blue-50 wsms-px-2 wsms-py-0.5 wsms-rounded">{__('AND')}</span>
                      </div>
                    )}
                    <div className="wsms-flex wsms-items-center wsms-gap-2">
                      <div className="wsms-flex-1 wsms-min-w-0">
                        <Select
                          value={condition.condition_key}
                          onValueChange={(value) => updateConditionInGroup(groupIndex, condIndex, 'condition_key', value)}
                        >
                          <SelectTrigger aria-label={__('Condition type')}>
                            <SelectValue placeholder={__('Select type...')} />
                          </SelectTrigger>
                          <SelectContent>
                            {Array.isArray(conditionOptions) && conditionOptions.map(opt => (
                              <SelectItem key={opt.key} value={opt.key}>{opt.label}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>

                      <div className="wsms-flex-1 wsms-min-w-0">
                        <Select
                          value={condition.condition_value}
                          onValueChange={(value) => updateConditionInGroup(groupIndex, condIndex, 'condition_value', value)}
                        >
                          <SelectTrigger aria-label={__('Condition value')}>
                            <SelectValue placeholder={__('Select value...')} />
                          </SelectTrigger>
                          <SelectContent>
                            {getConditionValues(condition.condition_key).map(opt => (
                              <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      </div>

                      <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        onClick={() => removeConditionFromGroup(groupIndex, condIndex)}
                        className="wsms-shrink-0"
                      >
                        <X className="wsms-h-4 wsms-w-4" />
                      </Button>
                    </div>
                  </div>
                ))}
                <Button type="button" variant="ghost" size="sm" onClick={() => addConditionToGroup(groupIndex)} className="wsms-mt-1">
                  <Plus className="wsms-h-3.5 wsms-w-3.5 wsms-me-1" />
                  {__('Add condition')}
                </Button>
              </div>
            </div>
          ))}

          <Button type="button" variant="outline" size="sm" onClick={addConditionGroup}>
            <Plus className="wsms-h-4 wsms-w-4 wsms-me-1" />
            {__('Add condition group')}
          </Button>
        </div>

        {/* Time Specification */}
        <div className="wsms-space-y-3">
          <Label>{__('When to Send')}</Label>
          <Select
            value={formData.time_specification}
            onValueChange={(value) => updateField('time_specification', value)}
          >
            <SelectTrigger aria-label={__('When to send')}>
              <SelectValue placeholder={__('Select when to send...')} />
            </SelectTrigger>
            <SelectContent>
              {(timeSpecifications.length > 0 ? timeSpecifications : [
                { value: 'right-away', label: __('Right Away') },
                { value: 'specific-date', label: __('Specific Date') },
                { value: 'after-placing-order', label: __('After Placing Order') },
              ]).map(spec => (
                <SelectItem key={spec.value} value={spec.value}>{spec.label}</SelectItem>
              ))}
            </SelectContent>
          </Select>

          {formData.time_specification === 'specific-date' && (
            <Input
              type="datetime-local"
              value={formData.specific_date}
              onChange={(e) => updateField('specific_date', e.target.value)}
              aria-label={__('Specific date')}
            />
          )}

          {formData.time_specification === 'after-placing-order' && (
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <div className="wsms-w-20 wsms-shrink-0">
                <Input
                  type="number"
                  min="1"
                  value={formData.delayed_time.value}
                  onChange={(e) => updateField('delayed_time', { ...formData.delayed_time, value: parseInt(e.target.value) || 1 })}
                  aria-label={__('Delay value')}
                />
              </div>
              <div className="wsms-w-28 wsms-shrink-0">
                <Select
                  value={formData.delayed_time.unit}
                  onValueChange={(value) => updateField('delayed_time', { ...formData.delayed_time, unit: value })}
                >
                  <SelectTrigger aria-label={__('Delay unit')}>
                    <SelectValue />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="minutes">{__('Minutes')}</SelectItem>
                    <SelectItem value="hours">{__('Hours')}</SelectItem>
                    <SelectItem value="days">{__('Days')}</SelectItem>
                  </SelectContent>
                </Select>
              </div>
              <span className="wsms-text-xs wsms-text-muted-foreground wsms-whitespace-nowrap">{__('after order is placed')}</span>
            </div>
          )}
        </div>

        {/* Message Content */}
        <div className="wsms-space-y-2">
          <Label htmlFor="message">{__('Message Content')}</Label>
          <TemplateTextarea
            id="message"
            value={formData.message_content}
            onChange={(value) => updateField('message_content', value)}
            placeholder={__('Enter your SMS message...')}
            rows={4}
            variables={messageVariables.length > 0 ? messageVariables : [
              { variable: '%billing_first_name%', description: 'First Name' },
              { variable: '%billing_last_name%', description: 'Last Name' },
              { variable: '%order_number%', description: 'Order Number' },
              { variable: '%order_total%', description: 'Order Total' },
              { variable: '%status%', description: 'Order Status' },
              { variable: '%order_items%', description: 'Order Items' },
              { variable: '%billing_phone%', description: 'Phone' },
              { variable: '%billing_email%', description: 'Email' },
              { variable: '%shipping_method%', description: 'Shipping Method' },
            ]}
          />
        </div>
      </form>

      {/* Form Actions - outside form to use DialogFooter's own padding */}
      <DialogFooter>
        <Button type="button" variant="outline" onClick={onCancel} disabled={isLoading}>
          {__('Cancel')}
        </Button>
        <Button type="submit" form={formId} disabled={isLoading}>
          {isLoading ? (
            <>
              <Loader2 className="wsms-h-4 wsms-w-4 wsms-me-2 wsms-animate-spin" />
              {__('Saving...')}
            </>
          ) : (
            <>
              <Save className="wsms-h-4 wsms-w-4 wsms-me-2" />
              {campaign ? __('Update Campaign') : __('Create Campaign')}
            </>
          )}
        </Button>
      </DialogFooter>
    </>
  )
}

// Column definitions for DataTable
const campaignColumns = [
  {
    id: 'title',
    accessorKey: 'title',
    header: __('Campaign'),
    cell: ({ row }) => (
      <div>
        <p className="wsms-font-medium wsms-text-[13px]">{row.title}</p>
        {row.message_content && (
          <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-truncate wsms-max-w-[300px]">
            {row.message_content}
          </p>
        )}
      </div>
    ),
  },
  {
    id: 'status',
    accessorKey: 'status',
    header: __('Status'),
    cell: ({ row }) => <StatusBadge status={row.status} />,
  },
  {
    id: 'schedule',
    accessorKey: 'time_specification',
    header: __('Schedule'),
    cell: ({ row }) => (
      <TimeSpecDisplay
        timeSpec={row.time_specification}
        specificDate={row.specific_date}
        delayedTime={row.delayed_time}
      />
    ),
  },
  {
    id: 'queue',
    accessorKey: 'queue_status',
    header: __('Queue'),
    cell: ({ row }) => (
      <QueueStatusBadge
        queueStatus={row.queue_status}
        nextSchedule={row.next_schedule}
      />
    ),
  },
  {
    id: 'created',
    accessorKey: 'created_at',
    header: __('Created'),
    cell: ({ row }) => (
      <span className="wsms-text-[12px] wsms-text-muted-foreground">
        {row.created_at ? (row.created_at_formatted || formatDate(row.created_at)) : '\u2014'}
      </span>
    ),
  },
]

// Row actions factory
function getCampaignRowActions({ onView, onViewQueue, onEdit, onDelete }) {
  return [
    {
      label: __('View Details'),
      icon: Eye,
      onClick: onView,
    },
    {
      label: __('View Queue'),
      icon: ListOrdered,
      onClick: onViewQueue,
    },
    {
      label: __('Edit'),
      icon: Edit2,
      onClick: onEdit,
    },
    {
      label: __('Delete'),
      icon: Trash2,
      onClick: onDelete,
      variant: 'destructive',
    },
  ]
}

export default function SmsCampaigns() {
  const { isAddonActive } = useSettings()
  const { toast } = useToast()
  const hasWooCommercePro = isAddonActive('woocommerce')
  const dashboardReady = isAddonDashboardReady('woocommerce')

  // useListPage for data management
  const { filters, table } = useListPage({
    fetchFn: woocommerceProApi.getCampaigns,
    deleteFn: woocommerceProApi.deleteCampaign,
    bulkActionFn: async () => {},
    initialFilters: { search: '', status: 'any' },
    perPage: 10,
    fetchOnMount: hasWooCommercePro && dashboardReady,
    messages: {
      deleteSuccess: __('Campaign deleted successfully'),
    },
  })

  // Condition options from API
  const [conditionOptions, setConditionOptions] = useState([])
  const [timeSpecifications, setTimeSpecifications] = useState([])
  const [messageVariables, setMessageVariables] = useState([])

  // Dialog states
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [isViewOpen, setIsViewOpen] = useState(false)
  const [isQueueOpen, setIsQueueOpen] = useState(false)
  const [selectedCampaign, setSelectedCampaign] = useState(null)
  const [queueCampaign, setQueueCampaign] = useState(null)
  const [formLoading, setFormLoading] = useState(false)
  const [detailLoading, setDetailLoading] = useState(false)
  const [queueLoading, setQueueLoading] = useState(false)

  // Delete confirmation dialog
  const deleteDialog = useFormDialog({
    saveFn: async (id) => {
      await woocommerceProApi.deleteCampaign(id, { force: true })
      table.removeItems([id])
    },
    successMessage: __('Campaign deleted successfully'),
  })

  // Fetch condition options
  useEffect(() => {
    if (!hasWooCommercePro || !dashboardReady) return
    const fetchConditionOptions = async () => {
      try {
        const response = await woocommerceProApi.getConditionOptions()
        const conditions = response?.data?.conditions
        const timeSpecs = response?.data?.time_specifications

        let transformedConditions = []
        if (conditions && typeof conditions === 'object' && !Array.isArray(conditions)) {
          const labelMap = {
            order_statues: __('Order Status'),
            coupon_codes: __('Coupon Code'),
            product: __('Product'),
            product_type: __('Product Type'),
          }
          transformedConditions = Object.entries(conditions).map(([key, options]) => ({
            key,
            label: labelMap[key] || key.replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase()),
            options: Array.isArray(options)
              ? options.map(opt => typeof opt === 'string' ? { value: opt, label: opt } : opt)
              : Object.entries(options).map(([value, label]) => ({ value, label }))
          })).filter(condition => condition.options.length > 0)
        } else if (Array.isArray(conditions)) {
          transformedConditions = conditions
        }

        setConditionOptions(transformedConditions)
        setTimeSpecifications(Array.isArray(timeSpecs) ? timeSpecs : [])

        const vars = response?.data?.message_variables
        setMessageVariables(Array.isArray(vars) ? vars : [])
      } catch (err) {
        console.error('Failed to fetch condition options:', err)
      }
    }
    fetchConditionOptions()
  }, [hasWooCommercePro, dashboardReady])

  // Handlers
  const handleCreate = () => {
    setSelectedCampaign(null)
    setIsFormOpen(true)
  }

  const handleEdit = useCallback(async (campaign) => {
    setSelectedCampaign(campaign)
    setIsFormOpen(true)
    setDetailLoading(true)
    try {
      const data = await woocommerceProApi.getCampaign(campaign.id)
      setSelectedCampaign(data)
    } catch (err) {
      toast({ title: err.message || __('Failed to load campaign details'), variant: 'destructive' })
    } finally {
      setDetailLoading(false)
    }
  }, [toast])

  const handleView = useCallback(async (campaign) => {
    setSelectedCampaign(campaign)
    setIsViewOpen(true)
    setDetailLoading(true)
    try {
      const data = await woocommerceProApi.getCampaign(campaign.id)
      setSelectedCampaign(data)
    } catch (err) {
      toast({ title: err.message || __('Failed to load campaign details'), variant: 'destructive' })
    } finally {
      setDetailLoading(false)
    }
  }, [toast])

  const handleViewQueue = useCallback(async (campaign) => {
    setQueueCampaign(campaign)
    setIsQueueOpen(true)
    setQueueLoading(true)
    try {
      const data = await woocommerceProApi.getCampaign(campaign.id)
      setQueueCampaign(data)
    } catch (err) {
      toast({ title: err.message || __('Failed to load queue data'), variant: 'destructive' })
    } finally {
      setQueueLoading(false)
    }
  }, [toast])

  const handleDeleteClick = useCallback((campaign) => {
    deleteDialog.open(campaign)
  }, [deleteDialog])

  const handleDeleteConfirm = async () => {
    if (!deleteDialog.item) return
    try {
      await deleteDialog.save()
    } catch {
      // Error handled by useFormDialog
    }
  }

  const handleSave = async (formData) => {
    setFormLoading(true)
    try {
      if (selectedCampaign) {
        await woocommerceProApi.updateCampaign(selectedCampaign.id, formData)
        toast({ title: __('Campaign updated successfully'), variant: 'success' })
      } else {
        await woocommerceProApi.createCampaign(formData)
        toast({ title: __('Campaign created successfully'), variant: 'success' })
      }
      setIsFormOpen(false)
      table.refresh()
    } catch (err) {
      toast({ title: err.message || __('Failed to save campaign'), variant: 'destructive' })
    } finally {
      setFormLoading(false)
    }
  }

  // Row actions
  const rowActions = useMemo(() => getCampaignRowActions({
    onView: handleView,
    onViewQueue: handleViewQueue,
    onEdit: handleEdit,
    onDelete: handleDeleteClick,
  }), [handleView, handleViewQueue, handleEdit, handleDeleteClick])

  // Show placeholder if WooCommerce Pro add-on is not active
  if (!hasWooCommercePro) {
    return (
      <div className="wsms-space-y-6">
        <Card>
          <CardContent className="wsms-py-8">
            <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-6 wsms-text-center">
              <AlertCircle className="wsms-mx-auto wsms-h-10 wsms-w-10 wsms-text-muted-foreground wsms-mb-3" />
              <h3 className="wsms-font-medium wsms-mb-2">{__('WooCommerce Pro Add-on Required')}</h3>
              <p className="wsms-text-sm wsms-text-muted-foreground wsms-mb-4">
                {__('Install and activate the WSMS WooCommerce Pro add-on to access SMS Campaigns.')}
              </p>
              <Button variant="outline" asChild>
                <a
                  href="https://wsms.io/product/wp-sms-woocommerce-pro/"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  {__('Learn More')}
                  <ExternalLink className="wsms-ms-2 wsms-h-4 wsms-w-4" />
                </a>
              </Button>
            </div>
          </CardContent>
        </Card>
      </div>
    )
  }

  if (!dashboardReady) {
    return <AddonUpdateRequired addonKey="woocommerce" icon={Megaphone} />
  }

  // Loading skeleton
  if (!table.initialLoadDone) {
    return <PageLoadingSkeleton />
  }

  return (
    <div className="wsms-space-y-6 wsms-stagger-children">
      {/* Header */}
      <div className="wsms-relative wsms-overflow-hidden wsms-rounded-lg wsms-bg-gradient-to-br wsms-from-primary/5 wsms-via-primary/10 wsms-to-transparent wsms-border wsms-border-primary/20">
        <div className="wsms-absolute wsms-top-0 wsms-right-0 wsms-w-32 wsms-h-32 wsms-bg-primary/5 wsms-rounded-full wsms--translate-y-1/2 wsms-translate-x-1/2" />
        <div className="wsms-relative wsms-p-6">
          <div className="wsms-flex wsms-items-start wsms-justify-between">
            <div className="wsms-flex wsms-items-start wsms-gap-4">
              <div className="wsms-flex wsms-h-12 wsms-w-12 wsms-items-center wsms-justify-center wsms-rounded-xl wsms-bg-primary/10 wsms-shrink-0">
                <Megaphone className="wsms-h-6 wsms-w-6 wsms-text-primary" />
              </div>
              <div>
                <h2 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-1">
                  {__('SMS Campaigns')}
                </h2>
                <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-max-w-lg">
                  {__('Create targeted SMS marketing campaigns based on customer behavior. Set conditions, schedule delivery, and track results.')}
                </p>
              </div>
            </div>
            <Button size="sm" onClick={handleCreate}>
              <Plus className="wsms-h-4 wsms-w-4 wsms-me-2" />
              {__('New Campaign')}
            </Button>
          </div>
        </div>
      </div>

      {/* Filters */}
      <Card>
        <CardContent className="wsms-p-3">
          <div className="wsms-flex wsms-flex-col wsms-gap-3 xl:wsms-flex-row xl:wsms-items-center xl:wsms-gap-3">
            {/* Search */}
            <div className="wsms-relative wsms-w-full xl:wsms-w-[220px] xl:wsms-shrink-0">
              <Search
                className="wsms-absolute wsms-start-2.5 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-pointer-events-none"
                aria-hidden="true"
              />
              <Input
                type="text"
                value={filters.filters.search}
                onChange={(e) => filters.setFilter('search', e.target.value)}
                placeholder={__('Search campaigns...')}
                className="wsms-ps-8 wsms-h-9"
                aria-label={__('Search campaigns')}
              />
            </div>

            {/* Status Filter */}
            <Select
              value={filters.filters.status}
              onValueChange={(value) => filters.setFilter('status', value)}
            >
              <SelectTrigger className="wsms-h-9 wsms-w-full xl:wsms-w-[140px] wsms-text-[12px]" aria-label={__('Filter by status')}>
                <SelectValue placeholder={__('All Statuses')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="any">{__('All Statuses')}</SelectItem>
                <SelectItem value="publish">{__('Active')}</SelectItem>
                <SelectItem value="draft">{__('Draft')}</SelectItem>
                <SelectItem value="pending">{__('Pending')}</SelectItem>
              </SelectContent>
            </Select>

            {/* Clear Filters */}
            {(filters.filters.search || filters.filters.status !== 'any') && (
              <Button
                variant="ghost"
                size="sm"
                onClick={() => filters.resetFilters()}
                className="wsms-h-9 wsms-px-2.5 wsms-text-muted-foreground hover:wsms-text-foreground"
                aria-label={__('Clear all filters')}
              >
                <X className="wsms-h-4 wsms-w-4" aria-hidden="true" />
              </Button>
            )}

            {/* Refresh */}
            <Button
              variant="outline"
              size="sm"
              onClick={() => table.fetch({ page: 1 })}
              className="wsms-h-9 wsms-px-2.5 xl:wsms-ms-auto"
              aria-label={__('Refresh')}
            >
              <RefreshCw className={cn('wsms-h-4 wsms-w-4', table.isLoading && 'wsms-animate-spin')} />
            </Button>
          </div>
        </CardContent>
      </Card>

      {/* Data Table */}
      <Card>
        <CardContent className="wsms-p-0">
          <DataTable
            columns={campaignColumns}
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
            emptyMessage={__('No campaigns found')}
            emptyIcon={Megaphone}
          />
        </CardContent>
      </Card>

      {/* Create/Edit Dialog */}
      <Dialog open={isFormOpen} onOpenChange={setIsFormOpen}>
        <DialogContent className="wsms-max-h-[90vh] wsms-overflow-y-auto wsms-scrollbar-thin" style={{ maxWidth: '768px' }}>
          <DialogHeader>
            <DialogTitle>
              {selectedCampaign ? __('Edit Campaign') : __('Create Campaign')}
            </DialogTitle>
            <DialogDescription>
              {selectedCampaign
                ? __('Update your SMS campaign settings.')
                : __('Create a new targeted SMS marketing campaign.')
              }
            </DialogDescription>
          </DialogHeader>
          {detailLoading ? (
            <DialogLoadingSpinner />
          ) : (
            <CampaignForm
              campaign={selectedCampaign}
              conditionOptions={conditionOptions}
              timeSpecifications={timeSpecifications}
              messageVariables={messageVariables}
              onSave={handleSave}
              onCancel={() => setIsFormOpen(false)}
              isLoading={formLoading}
            />
          )}
        </DialogContent>
      </Dialog>

      {/* View Dialog */}
      <Dialog open={isViewOpen} onOpenChange={setIsViewOpen}>
        <DialogContent className="wsms-max-w-lg">
          <DialogHeader>
            <DialogTitle>{selectedCampaign?.title}</DialogTitle>
            <DialogDescription>
              {__('Campaign details and configuration')}
            </DialogDescription>
          </DialogHeader>
          {detailLoading ? (
            <DialogLoadingSpinner />
          ) : selectedCampaign && (
            <DialogBody>
              <div className="wsms-space-y-4">
                <div>
                  <Label className="wsms-text-[12px] wsms-text-muted-foreground">{__('Status')}</Label>
                  <div className="wsms-mt-1">
                    <StatusBadge status={selectedCampaign.status} />
                  </div>
                </div>

                <div>
                  <Label className="wsms-text-[12px] wsms-text-muted-foreground">{__('Schedule')}</Label>
                  <div className="wsms-mt-1">
                    <TimeSpecDisplay
                      timeSpec={selectedCampaign.time_specification}
                      specificDate={selectedCampaign.specific_date}
                      delayedTime={selectedCampaign.delayed_time}
                    />
                  </div>
                </div>

                {selectedCampaign.conditions && selectedCampaign.conditions.length > 0 && (
                  <div>
                    <Label className="wsms-text-[12px] wsms-text-muted-foreground">{__('Conditions')}</Label>
                    <div className="wsms-mt-1 wsms-space-y-1">
                      {normalizeConditionGroups(selectedCampaign.conditions).map((group, groupIndex) => (
                        <div key={groupIndex}>
                          {groupIndex > 0 && (
                            <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-my-1">
                              <div className="wsms-flex-1 wsms-border-t wsms-border-border" />
                              <span className="wsms-text-[10px] wsms-font-medium wsms-text-orange-600">{__('OR')}</span>
                              <div className="wsms-flex-1 wsms-border-t wsms-border-border" />
                            </div>
                          )}
                          <div className="wsms-p-2 wsms-bg-muted/30 wsms-rounded wsms-space-y-1">
                            {group.conditions.map((condition, condIndex) => (
                              <div key={condIndex}>
                                {condIndex > 0 && (
                                  <span className="wsms-text-[10px] wsms-font-medium wsms-text-blue-600 wsms-block wsms-text-center wsms-my-0.5">{__('AND')}</span>
                                )}
                                <div className="wsms-text-[12px]">
                                  {condition.condition_key} = {condition.condition_value}
                                </div>
                              </div>
                            ))}
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}

                <div>
                  <Label className="wsms-text-[12px] wsms-text-muted-foreground">{__('Message Preview')}</Label>
                  <div className="wsms-mt-2 wsms-rounded-lg wsms-border wsms-border-border wsms-bg-muted/10 wsms-p-4">
                    {selectedCampaign.message_content ? (
                      <div className="wsms-max-w-[75%]">
                        <div className="wsms-bg-primary/10 wsms-rounded-xl wsms-rounded-tl-sm wsms-px-3 wsms-py-2.5">
                          <p className="wsms-text-[13px] wsms-text-foreground wsms-whitespace-pre-wrap wsms-break-words">
                            {selectedCampaign.message_content}
                          </p>
                        </div>
                        <p className="wsms-text-[10px] wsms-text-muted-foreground wsms-mt-1 wsms-ms-1">
                          {selectedCampaign.message_content.length} {__('characters')}
                        </p>
                      </div>
                    ) : (
                      <p className="wsms-text-[12px] wsms-text-muted-foreground">{__('No message content')}</p>
                    )}
                  </div>
                </div>

              </div>
            </DialogBody>
          )}
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsViewOpen(false)}>
              {__('Close')}
            </Button>
            <Button onClick={() => { setIsViewOpen(false); handleEdit(selectedCampaign) }}>
              <Edit2 className="wsms-h-4 wsms-w-4 wsms-me-2" />
              {__('Edit')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Queue Dialog */}
      <Dialog open={isQueueOpen} onOpenChange={setIsQueueOpen}>
        <DialogContent className="wsms-max-w-[95vw] md:wsms-max-w-2xl">
          <DialogHeader>
            <DialogTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <ListOrdered className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              <span className="wsms-truncate">{queueCampaign?.title}</span>
            </DialogTitle>
            <DialogDescription>
              {__('Queue execution details and target orders')}
            </DialogDescription>
          </DialogHeader>
          {queueLoading ? (
            <DialogLoadingSpinner />
          ) : queueCampaign && (
            <DialogBody>
              <div className="wsms-space-y-4">
                {/* Queue Summary */}
                <div className="wsms-grid wsms-grid-cols-1 sm:wsms-grid-cols-3 wsms-gap-3">
                  <div>
                    <Label className="wsms-text-[12px] wsms-text-muted-foreground">{__('Status')}</Label>
                    <div className="wsms-mt-1">
                      <QueueStatusBadge
                        queueStatus={queueCampaign.queue_status}
                        nextSchedule={queueCampaign.next_schedule}
                      />
                    </div>
                  </div>
                  {queueCampaign.last_execution && (
                    <div>
                      <Label className="wsms-text-[12px] wsms-text-muted-foreground">{__('Last Execution')}</Label>
                      <p className="wsms-mt-1 wsms-text-[13px]">{queueCampaign.last_execution_formatted || formatDate(queueCampaign.last_execution)}</p>
                    </div>
                  )}
                  {queueCampaign.queue_response && (
                    <div>
                      <Label className="wsms-text-[12px] wsms-text-muted-foreground">{__('Response')}</Label>
                      <p className="wsms-mt-1 wsms-text-[13px] wsms-text-muted-foreground">{queueCampaign.queue_response}</p>
                    </div>
                  )}
                </div>

                {/* Target Orders */}
                {Array.isArray(queueCampaign.target_orders) && queueCampaign.target_orders.length > 0 ? (
                  <div>
                    <Label className="wsms-text-[12px] wsms-text-muted-foreground">
                      {__('Target Orders')} ({queueCampaign.target_orders.length})
                    </Label>

                    {/* Mobile: Card layout */}
                    <div className="wsms-mt-2 wsms-space-y-2 md:wsms-hidden">
                      {queueCampaign.target_orders.map((order, index) => {
                        const statusConfig = {
                          success: { label: __('Sent'), className: 'wsms-text-emerald-600' },
                          pending: { label: __('In Queue'), className: 'wsms-text-blue-600' },
                          failed: { label: __('Failed'), className: 'wsms-text-red-600' },
                        }
                        const smsStatus = statusConfig[order.status] || statusConfig.pending
                        return (
                          <div key={index} className="wsms-rounded-lg wsms-border wsms-border-border wsms-p-3 wsms-space-y-1.5 wsms-text-[12px]">
                            <div className="wsms-flex wsms-items-center wsms-justify-between">
                              <a
                                href={`/wp-admin/post.php?post=${order.order_id}&action=edit`}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="wsms-text-primary wsms-font-medium hover:wsms-underline"
                              >
                                #{order.order_id}
                              </a>
                              <span className={`wsms-font-medium ${smsStatus.className}`}>{smsStatus.label}</span>
                            </div>
                            <div className="wsms-flex wsms-items-center wsms-justify-between wsms-text-muted-foreground">
                              <span className="wsms-font-mono">{order.mobile_number || '—'}</span>
                              <span>{order.order_date ? (order.order_date_formatted || formatDate(order.order_date)) : '—'}</span>
                            </div>
                            {order.response && (
                              <p className="wsms-text-muted-foreground wsms-text-[11px]">{order.response}</p>
                            )}
                          </div>
                        )
                      })}
                    </div>

                    {/* Desktop: Table layout */}
                    <div className="wsms-mt-2 wsms-rounded-lg wsms-border wsms-border-border wsms-overflow-hidden wsms-max-h-[400px] wsms-overflow-y-auto wsms-hidden md:wsms-block">
                      <table className="wsms-w-full wsms-text-[12px]">
                        <thead className="wsms-sticky wsms-top-0">
                          <tr className="wsms-bg-muted/50 wsms-border-b wsms-border-border">
                            <th className="wsms-px-3 wsms-py-2 wsms-text-start wsms-font-medium wsms-text-muted-foreground">{__('Order ID')}</th>
                            <th className="wsms-px-3 wsms-py-2 wsms-text-start wsms-font-medium wsms-text-muted-foreground">{__('Order Date')}</th>
                            <th className="wsms-px-3 wsms-py-2 wsms-text-start wsms-font-medium wsms-text-muted-foreground">{__('Mobile Number')}</th>
                            <th className="wsms-px-3 wsms-py-2 wsms-text-start wsms-font-medium wsms-text-muted-foreground">{__('SMS Status')}</th>
                            <th className="wsms-px-3 wsms-py-2 wsms-text-start wsms-font-medium wsms-text-muted-foreground">{__('Response')}</th>
                          </tr>
                        </thead>
                        <tbody>
                          {queueCampaign.target_orders.map((order, index) => {
                            const statusConfig = {
                              success: { label: __('Sent'), className: 'wsms-text-emerald-600' },
                              pending: { label: __('In Queue'), className: 'wsms-text-blue-600' },
                              failed: { label: __('Failed'), className: 'wsms-text-red-600' },
                            }
                            const smsStatus = statusConfig[order.status] || statusConfig.pending
                            return (
                              <tr key={index} className="wsms-border-b wsms-border-border last:wsms-border-0">
                                <td className="wsms-px-3 wsms-py-2">
                                  <a
                                    href={`/wp-admin/post.php?post=${order.order_id}&action=edit`}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="wsms-text-primary hover:wsms-underline"
                                  >
                                    #{order.order_id}
                                  </a>
                                </td>
                                <td className="wsms-px-3 wsms-py-2 wsms-text-muted-foreground">{order.order_date ? (order.order_date_formatted || formatDate(order.order_date)) : '—'}</td>
                                <td className="wsms-px-3 wsms-py-2 wsms-font-mono">{order.mobile_number || '—'}</td>
                                <td className={`wsms-px-3 wsms-py-2 wsms-font-medium ${smsStatus.className}`}>{smsStatus.label}</td>
                                <td className="wsms-px-3 wsms-py-2 wsms-text-muted-foreground">{order.response || '—'}</td>
                              </tr>
                            )
                          })}
                        </tbody>
                      </table>
                    </div>
                  </div>
                ) : (
                  <div className="wsms-text-center wsms-py-8">
                    <ListOrdered className="wsms-h-8 wsms-w-8 wsms-text-muted-foreground wsms-mx-auto wsms-mb-2" />
                    <p className="wsms-text-[13px] wsms-text-muted-foreground">{__('No target orders found for this campaign')}</p>
                  </div>
                )}
              </div>
            </DialogBody>
          )}
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsQueueOpen(false)}>
              {__('Close')}
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
        title={__('Delete Campaign')}
        description={__('Are you sure you want to delete this campaign? This action is irreversible and cannot be undone.')}
      >
        {deleteDialog.item && (
          <div className="wsms-p-4 wsms-rounded-md wsms-bg-muted/50 wsms-border wsms-border-border">
            <p className="wsms-text-[13px] wsms-font-medium">{deleteDialog.item.title}</p>
          </div>
        )}
      </DeleteConfirmDialog>
    </div>
  )
}
