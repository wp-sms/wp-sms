import React, { useState, useEffect, useCallback } from 'react'
import {
  Megaphone,
  Plus,
  Edit2,
  Trash2,
  Search,
  RefreshCw,
  ChevronLeft,
  ChevronRight,
  Clock,
  CheckCircle2,
  XCircle,
  AlertCircle,
  Calendar,
  Timer,
  Send,
  Eye,
  X,
  Save,
  Loader2,
} from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Textarea } from '@/components/ui/textarea'
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
import { Badge } from '@/components/ui/badge'
import { __, getWpSettings } from '@/lib/utils'

// WooCommerce Pro API client
const createWooCommerceProApi = () => {
  const { nonce } = getWpSettings()
  const baseUrl = '/wp-json/wp-sms-woo-pro/v1'

  const request = async (endpoint, options = {}) => {
    const url = `${baseUrl}${endpoint}`
    const response = await fetch(url, {
      ...options,
      headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce,
        ...options.headers,
      },
    })
    const data = await response.json()
    if (!response.ok) {
      throw new Error(data.message || 'API request failed')
    }
    return data
  }

  return {
    listCampaigns: (params = {}) => {
      const query = new URLSearchParams(params).toString()
      return request(`/campaigns${query ? `?${query}` : ''}`)
    },
    getCampaign: (id) => request(`/campaigns/${id}`),
    createCampaign: (data) => request('/campaigns', {
      method: 'POST',
      body: JSON.stringify(data),
    }),
    updateCampaign: (id, data) => request(`/campaigns/${id}`, {
      method: 'POST',
      body: JSON.stringify(data),
    }),
    deleteCampaign: (id, force = false) => request(`/campaigns/${id}/delete`, {
      method: 'POST',
      body: JSON.stringify({ force }),
    }),
    getConditionOptions: () => request('/campaigns/conditions'),
  }
}

const api = createWooCommerceProApi()

// Status badge component
const StatusBadge = ({ status }) => {
  const statusConfig = {
    publish: { label: __('Active'), variant: 'default', icon: CheckCircle2, className: 'wsms-bg-green-100 wsms-text-green-800 dark:wsms-bg-green-900 dark:wsms-text-green-300' },
    draft: { label: __('Draft'), variant: 'secondary', icon: Edit2, className: 'wsms-bg-gray-100 wsms-text-gray-800 dark:wsms-bg-gray-800 dark:wsms-text-gray-300' },
    pending: { label: __('Pending'), variant: 'outline', icon: Clock, className: 'wsms-bg-yellow-100 wsms-text-yellow-800 dark:wsms-bg-yellow-900 dark:wsms-text-yellow-300' },
    trash: { label: __('Trashed'), variant: 'destructive', icon: Trash2, className: 'wsms-bg-red-100 wsms-text-red-800 dark:wsms-bg-red-900 dark:wsms-text-red-300' },
  }

  const config = statusConfig[status] || statusConfig.draft
  const Icon = config.icon

  return (
    <Badge variant={config.variant} className={`wsms-inline-flex wsms-items-center wsms-gap-1 ${config.className}`}>
      <Icon className="wsms-h-3 wsms-w-3" />
      {config.label}
    </Badge>
  )
}

// Queue status badge
const QueueStatusBadge = ({ queueStatus, nextSchedule }) => {
  if (!queueStatus) {
    return <span className="wsms-text-muted-foreground wsms-text-[12px]">—</span>
  }

  const statusConfig = {
    pending: { label: __('Pending'), icon: Clock, className: 'wsms-bg-yellow-100 wsms-text-yellow-800' },
    processing: { label: __('Processing'), icon: RefreshCw, className: 'wsms-bg-blue-100 wsms-text-blue-800' },
    completed: { label: __('Completed'), icon: CheckCircle2, className: 'wsms-bg-green-100 wsms-text-green-800' },
    failed: { label: __('Failed'), icon: XCircle, className: 'wsms-bg-red-100 wsms-text-red-800' },
  }

  const config = statusConfig[queueStatus] || statusConfig.pending
  const Icon = config.icon

  return (
    <div className="wsms-flex wsms-flex-col wsms-gap-1">
      <Badge variant="outline" className={`wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] ${config.className}`}>
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
  const specConfig = {
    immediately: { label: __('Immediately'), icon: Send },
    specific_date: { label: __('Specific Date'), icon: Calendar },
    after_placing_order: { label: __('After Order'), icon: Timer },
  }

  const config = specConfig[timeSpec] || { label: timeSpec || '—', icon: Clock }
  const Icon = config.icon

  return (
    <div className="wsms-flex wsms-flex-col wsms-gap-1">
      <div className="wsms-flex wsms-items-center wsms-gap-1.5 wsms-text-[12px]">
        <Icon className="wsms-h-3.5 wsms-w-3.5 wsms-text-muted-foreground" />
        <span>{config.label}</span>
      </div>
      {timeSpec === 'specific_date' && specificDate && (
        <span className="wsms-text-[11px] wsms-text-muted-foreground wsms-ml-5">{specificDate}</span>
      )}
      {timeSpec === 'after_placing_order' && delayedTime && (
        <span className="wsms-text-[11px] wsms-text-muted-foreground wsms-ml-5">
          {delayedTime.value} {delayedTime.unit}
        </span>
      )}
    </div>
  )
}

// Campaign form component
const CampaignForm = ({ campaign, conditionOptions, timeSpecifications, onSave, onCancel, isLoading }) => {
  const [formData, setFormData] = useState({
    title: campaign?.title || '',
    status: campaign?.status || 'draft',
    conditions: campaign?.conditions || [],
    time_specification: campaign?.time_specification || 'immediately',
    specific_date: campaign?.specific_date || '',
    delayed_time: campaign?.delayed_time || { value: 30, unit: 'minutes' },
    message_content: campaign?.message_content || '',
  })

  const handleSubmit = (e) => {
    e.preventDefault()
    onSave(formData)
  }

  const updateField = (field, value) => {
    setFormData(prev => ({ ...prev, [field]: value }))
  }

  const addCondition = () => {
    setFormData(prev => ({
      ...prev,
      conditions: [...prev.conditions, { type: '', operator: 'is', value: '' }]
    }))
  }

  const updateCondition = (index, field, value) => {
    setFormData(prev => {
      const newConditions = [...prev.conditions]
      newConditions[index] = { ...newConditions[index], [field]: value }
      return { ...prev, conditions: newConditions }
    })
  }

  const removeCondition = (index) => {
    setFormData(prev => ({
      ...prev,
      conditions: prev.conditions.filter((_, i) => i !== index)
    }))
  }

  // Get options for a condition type
  const getConditionValues = (type) => {
    if (!Array.isArray(conditionOptions)) return []
    const conditionType = conditionOptions.find(c => c.key === type)
    const options = conditionType?.options
    return Array.isArray(options) ? options : []
  }

  return (
    <form onSubmit={handleSubmit} className="wsms-space-y-6 wsms-px-6 wsms-pb-2">
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
          <SelectTrigger>
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
        <div className="wsms-flex wsms-items-center wsms-justify-between">
          <Label>{__('Conditions')}</Label>
          <Button type="button" variant="outline" size="sm" onClick={addCondition}>
            <Plus className="wsms-h-4 wsms-w-4 wsms-mr-1" />
            {__('Add Condition')}
          </Button>
        </div>

        {formData.conditions.length === 0 && (
          <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-py-4 wsms-text-center wsms-border wsms-border-dashed wsms-rounded-lg">
            {__('No conditions added. Campaign will match all orders.')}
          </p>
        )}

        {formData.conditions.map((condition, index) => (
          <div key={index} className="wsms-flex wsms-items-center wsms-gap-2 wsms-p-3 wsms-bg-muted/30 wsms-rounded-lg">
            <Select
              value={condition.type}
              onValueChange={(value) => updateCondition(index, 'type', value)}
            >
              <SelectTrigger className="wsms-w-[180px]">
                <SelectValue placeholder={__('Select type...')} />
              </SelectTrigger>
              <SelectContent>
                {Array.isArray(conditionOptions) && conditionOptions.map(opt => (
                  <SelectItem key={opt.key} value={opt.key}>{opt.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Select
              value={condition.operator}
              onValueChange={(value) => updateCondition(index, 'operator', value)}
            >
              <SelectTrigger className="wsms-w-[100px]">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="is">{__('is')}</SelectItem>
                <SelectItem value="is_not">{__('is not')}</SelectItem>
              </SelectContent>
            </Select>

            <Select
              value={condition.value}
              onValueChange={(value) => updateCondition(index, 'value', value)}
            >
              <SelectTrigger className="wsms-flex-1">
                <SelectValue placeholder={__('Select value...')} />
              </SelectTrigger>
              <SelectContent>
                {getConditionValues(condition.type).map(opt => (
                  <SelectItem key={opt.value} value={opt.value}>{opt.label}</SelectItem>
                ))}
              </SelectContent>
            </Select>

            <Button
              type="button"
              variant="ghost"
              size="icon"
              onClick={() => removeCondition(index)}
              className="wsms-shrink-0"
            >
              <X className="wsms-h-4 wsms-w-4" />
            </Button>
          </div>
        ))}
      </div>

      {/* Time Specification */}
      <div className="wsms-space-y-3">
        <Label>{__('When to Send')}</Label>
        <Select
          value={formData.time_specification}
          onValueChange={(value) => updateField('time_specification', value)}
        >
          <SelectTrigger>
            <SelectValue placeholder={__('Select when to send...')} />
          </SelectTrigger>
          <SelectContent>
            {(timeSpecifications.length > 0 ? timeSpecifications : [
              { value: 'immediately', label: __('Immediately') },
              { value: 'specific_date', label: __('Specific Date') },
              { value: 'after_placing_order', label: __('After Placing Order') },
            ]).map(spec => (
              <SelectItem key={spec.value} value={spec.value}>{spec.label}</SelectItem>
            ))}
          </SelectContent>
        </Select>

        {formData.time_specification === 'specific_date' && (
          <Input
            type="datetime-local"
            value={formData.specific_date}
            onChange={(e) => updateField('specific_date', e.target.value)}
          />
        )}

        {formData.time_specification === 'after_placing_order' && (
          <div className="wsms-flex wsms-items-center wsms-gap-2">
            <Input
              type="number"
              min="1"
              value={formData.delayed_time.value}
              onChange={(e) => updateField('delayed_time', { ...formData.delayed_time, value: parseInt(e.target.value) || 1 })}
              className="wsms-w-24"
            />
            <Select
              value={formData.delayed_time.unit}
              onValueChange={(value) => updateField('delayed_time', { ...formData.delayed_time, unit: value })}
            >
              <SelectTrigger className="wsms-w-32">
                <SelectValue />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="minutes">{__('Minutes')}</SelectItem>
                <SelectItem value="hours">{__('Hours')}</SelectItem>
                <SelectItem value="days">{__('Days')}</SelectItem>
              </SelectContent>
            </Select>
            <span className="wsms-text-[12px] wsms-text-muted-foreground">{__('after order is placed')}</span>
          </div>
        )}
      </div>

      {/* Message Content */}
      <div className="wsms-space-y-2">
        <Label htmlFor="message">{__('Message Content')}</Label>
        <Textarea
          id="message"
          value={formData.message_content}
          onChange={(e) => updateField('message_content', e.target.value)}
          placeholder={__('Enter your SMS message...')}
          rows={4}
        />
        <p className="wsms-text-[11px] wsms-text-muted-foreground">
          {__('Available variables:')} <code>%customer_name%</code>, <code>%order_id%</code>, <code>%order_status%</code>, <code>%order_total%</code>, <code>%product_name%</code>
        </p>
      </div>

      {/* Form Actions */}
      <DialogFooter>
        <Button type="button" variant="outline" onClick={onCancel} disabled={isLoading}>
          {__('Cancel')}
        </Button>
        <Button type="submit" disabled={isLoading}>
          {isLoading ? (
            <>
              <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-2 wsms-animate-spin" />
              {__('Saving...')}
            </>
          ) : (
            <>
              <Save className="wsms-h-4 wsms-w-4 wsms-mr-2" />
              {campaign ? __('Update Campaign') : __('Create Campaign')}
            </>
          )}
        </Button>
      </DialogFooter>
    </form>
  )
}

export default function SmsCampaigns() {
  const [campaigns, setCampaigns] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState(null)
  const [pagination, setPagination] = useState({ page: 1, perPage: 10, total: 0, totalPages: 0 })
  const [searchTerm, setSearchTerm] = useState('')
  const [statusFilter, setStatusFilter] = useState('any')

  // Modal states
  const [isFormOpen, setIsFormOpen] = useState(false)
  const [isDeleteOpen, setIsDeleteOpen] = useState(false)
  const [isViewOpen, setIsViewOpen] = useState(false)
  const [selectedCampaign, setSelectedCampaign] = useState(null)
  const [formLoading, setFormLoading] = useState(false)

  // Condition options from API
  const [conditionOptions, setConditionOptions] = useState([])
  const [timeSpecifications, setTimeSpecifications] = useState([])

  // Fetch campaigns
  const fetchCampaigns = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const response = await api.listCampaigns({
        page: pagination.page,
        per_page: pagination.perPage,
        status: statusFilter,
        search: searchTerm,
      })
      setCampaigns(response.data.campaigns)
      setPagination(prev => ({
        ...prev,
        total: response.data.total,
        totalPages: response.data.total_pages,
      }))
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [pagination.page, pagination.perPage, statusFilter, searchTerm])

  // Fetch condition options
  const fetchConditionOptions = useCallback(async () => {
    try {
      const response = await api.getConditionOptions()
      const conditions = response?.data?.conditions
      const timeSpecs = response?.data?.time_specifications

      // Transform conditions object to array format expected by the form
      // API returns: { order_statues: { 'wc-pending': 'Pending payment', ... }, ... }
      // Form expects: [{ key: 'order_status', label: 'Order Status', options: [{ value: 'wc-pending', label: 'Pending payment' }] }]
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
        })).filter(condition => condition.options.length > 0) // Only show conditions with options
      } else if (Array.isArray(conditions)) {
        transformedConditions = conditions
      }

      setConditionOptions(transformedConditions)
      setTimeSpecifications(Array.isArray(timeSpecs) ? timeSpecs : [])
    } catch (err) {
      console.error('Failed to fetch condition options:', err)
    }
  }, [])

  useEffect(() => {
    fetchCampaigns()
  }, [fetchCampaigns])

  useEffect(() => {
    fetchConditionOptions()
  }, [fetchConditionOptions])

  // Handlers
  const handleSearch = (e) => {
    e.preventDefault()
    setPagination(prev => ({ ...prev, page: 1 }))
    fetchCampaigns()
  }

  const handleCreate = () => {
    setSelectedCampaign(null)
    setIsFormOpen(true)
  }

  const handleEdit = async (campaign) => {
    try {
      const response = await api.getCampaign(campaign.id)
      setSelectedCampaign(response.data)
      setIsFormOpen(true)
    } catch (err) {
      setError(err.message)
    }
  }

  const handleView = async (campaign) => {
    try {
      const response = await api.getCampaign(campaign.id)
      setSelectedCampaign(response.data)
      setIsViewOpen(true)
    } catch (err) {
      setError(err.message)
    }
  }

  const handleDelete = (campaign) => {
    setSelectedCampaign(campaign)
    setIsDeleteOpen(true)
  }

  const handleSave = async (formData) => {
    setFormLoading(true)
    try {
      if (selectedCampaign) {
        await api.updateCampaign(selectedCampaign.id, formData)
      } else {
        await api.createCampaign(formData)
      }
      setIsFormOpen(false)
      fetchCampaigns()
    } catch (err) {
      setError(err.message)
    } finally {
      setFormLoading(false)
    }
  }

  const confirmDelete = async () => {
    if (!selectedCampaign) return
    try {
      await api.deleteCampaign(selectedCampaign.id, true)
      setIsDeleteOpen(false)
      setSelectedCampaign(null)
      fetchCampaigns()
    } catch (err) {
      setError(err.message)
    }
  }

  const handlePageChange = (newPage) => {
    setPagination(prev => ({ ...prev, page: newPage }))
  }

  return (
    <div className="wsms-space-y-6">
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
              <Plus className="wsms-h-4 wsms-w-4 wsms-mr-2" />
              {__('New Campaign')}
            </Button>
          </div>
        </div>
      </div>

      {/* Error Message */}
      {error && (
        <Card className="wsms-border-destructive">
          <CardContent className="wsms-py-4">
            <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-text-destructive">
              <AlertCircle className="wsms-h-4 wsms-w-4" />
              <span className="wsms-text-[13px]">{error}</span>
            </div>
          </CardContent>
        </Card>
      )}

      {/* Filters */}
      <Card>
        <CardContent className="wsms-py-4">
          <form onSubmit={handleSearch} className="wsms-flex wsms-items-center wsms-gap-4">
            <div className="wsms-relative wsms-flex-1">
              <Search className="wsms-absolute wsms-left-3 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <Input
                type="search"
                placeholder={__('Search campaigns...')}
                value={searchTerm}
                onChange={(e) => setSearchTerm(e.target.value)}
                className="wsms-pl-10"
              />
            </div>
            <Select value={statusFilter} onValueChange={(value) => { setStatusFilter(value); setPagination(prev => ({ ...prev, page: 1 })) }}>
              <SelectTrigger className="wsms-w-[150px]">
                <SelectValue placeholder={__('All Statuses')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="any">{__('All Statuses')}</SelectItem>
                <SelectItem value="publish">{__('Active')}</SelectItem>
                <SelectItem value="draft">{__('Draft')}</SelectItem>
                <SelectItem value="pending">{__('Pending')}</SelectItem>
                <SelectItem value="trash">{__('Trashed')}</SelectItem>
              </SelectContent>
            </Select>
            <Button type="submit" variant="secondary">
              <Search className="wsms-h-4 wsms-w-4 wsms-mr-2" />
              {__('Search')}
            </Button>
            <Button type="button" variant="outline" onClick={fetchCampaigns}>
              <RefreshCw className="wsms-h-4 wsms-w-4" />
            </Button>
          </form>
        </CardContent>
      </Card>

      {/* Campaigns Table */}
      <Card>
        <CardHeader>
          <CardTitle>{__('Campaigns')}</CardTitle>
          <CardDescription>
            {pagination.total > 0
              ? __('Showing %d of %d campaigns').replace('%d', campaigns.length).replace('%d', pagination.total)
              : __('No campaigns found')
            }
          </CardDescription>
        </CardHeader>
        <CardContent>
          {loading ? (
            <div className="wsms-flex wsms-items-center wsms-justify-center wsms-py-12">
              <RefreshCw className="wsms-h-6 wsms-w-6 wsms-animate-spin wsms-text-muted-foreground" />
            </div>
          ) : campaigns.length === 0 ? (
            <div className="wsms-text-center wsms-py-12">
              <Megaphone className="wsms-h-12 wsms-w-12 wsms-mx-auto wsms-text-muted-foreground wsms-mb-4" />
              <h3 className="wsms-text-[14px] wsms-font-medium wsms-text-foreground wsms-mb-1">
                {__('No campaigns yet')}
              </h3>
              <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-4">
                {__('Create your first SMS campaign to get started.')}
              </p>
              <Button onClick={handleCreate}>
                <Plus className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                {__('Create Campaign')}
              </Button>
            </div>
          ) : (
            <div className="wsms-border wsms-rounded-lg wsms-overflow-hidden">
              <table className="wsms-w-full">
                <thead className="wsms-bg-muted/50">
                  <tr>
                    <th className="wsms-px-4 wsms-py-3 wsms-text-left wsms-text-[12px] wsms-font-medium wsms-text-muted-foreground">{__('Campaign')}</th>
                    <th className="wsms-px-4 wsms-py-3 wsms-text-left wsms-text-[12px] wsms-font-medium wsms-text-muted-foreground">{__('Status')}</th>
                    <th className="wsms-px-4 wsms-py-3 wsms-text-left wsms-text-[12px] wsms-font-medium wsms-text-muted-foreground">{__('Schedule')}</th>
                    <th className="wsms-px-4 wsms-py-3 wsms-text-left wsms-text-[12px] wsms-font-medium wsms-text-muted-foreground">{__('Queue')}</th>
                    <th className="wsms-px-4 wsms-py-3 wsms-text-left wsms-text-[12px] wsms-font-medium wsms-text-muted-foreground">{__('Created')}</th>
                    <th className="wsms-px-4 wsms-py-3 wsms-text-right wsms-text-[12px] wsms-font-medium wsms-text-muted-foreground">{__('Actions')}</th>
                  </tr>
                </thead>
                <tbody className="wsms-divide-y wsms-divide-border">
                  {campaigns.map((campaign) => (
                    <tr key={campaign.id} className="hover:wsms-bg-muted/30">
                      <td className="wsms-px-4 wsms-py-3">
                        <div>
                          <p className="wsms-font-medium wsms-text-[13px]">{campaign.title}</p>
                          {campaign.message_content && (
                            <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-truncate wsms-max-w-[300px]">
                              {campaign.message_content}
                            </p>
                          )}
                        </div>
                      </td>
                      <td className="wsms-px-4 wsms-py-3">
                        <StatusBadge status={campaign.status} />
                      </td>
                      <td className="wsms-px-4 wsms-py-3">
                        <TimeSpecDisplay
                          timeSpec={campaign.time_specification}
                          specificDate={campaign.specific_date}
                          delayedTime={campaign.delayed_time}
                        />
                      </td>
                      <td className="wsms-px-4 wsms-py-3">
                        <QueueStatusBadge
                          queueStatus={campaign.queue_status}
                          nextSchedule={campaign.next_schedule}
                        />
                      </td>
                      <td className="wsms-px-4 wsms-py-3">
                        <span className="wsms-text-[12px] wsms-text-muted-foreground">
                          {new Date(campaign.created_at).toLocaleDateString()}
                        </span>
                      </td>
                      <td className="wsms-px-4 wsms-py-3 wsms-text-right">
                        <div className="wsms-flex wsms-items-center wsms-justify-end wsms-gap-1">
                          <Button variant="ghost" size="icon" onClick={() => handleView(campaign)} title={__('View')}>
                            <Eye className="wsms-h-4 wsms-w-4" />
                          </Button>
                          <Button variant="ghost" size="icon" onClick={() => handleEdit(campaign)} title={__('Edit')}>
                            <Edit2 className="wsms-h-4 wsms-w-4" />
                          </Button>
                          <Button variant="ghost" size="icon" onClick={() => handleDelete(campaign)} title={__('Delete')} className="wsms-text-destructive hover:wsms-text-destructive">
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

        {/* Pagination */}
        {pagination.totalPages > 1 && (
          <CardFooter className="wsms-flex wsms-items-center wsms-justify-between wsms-border-t wsms-pt-4">
            <p className="wsms-text-[12px] wsms-text-muted-foreground">
              {__('Page %d of %d').replace('%d', pagination.page).replace('%d', pagination.totalPages)}
            </p>
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => handlePageChange(pagination.page - 1)}
                disabled={pagination.page <= 1}
              >
                <ChevronLeft className="wsms-h-4 wsms-w-4" />
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => handlePageChange(pagination.page + 1)}
                disabled={pagination.page >= pagination.totalPages}
              >
                <ChevronRight className="wsms-h-4 wsms-w-4" />
              </Button>
            </div>
          </CardFooter>
        )}
      </Card>

      {/* Create/Edit Dialog */}
      <Dialog open={isFormOpen} onOpenChange={setIsFormOpen}>
        <DialogContent className="wsms-max-w-2xl wsms-max-h-[90vh] wsms-overflow-y-auto">
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
          <CampaignForm
            campaign={selectedCampaign}
            conditionOptions={conditionOptions}
            timeSpecifications={timeSpecifications}
            onSave={handleSave}
            onCancel={() => setIsFormOpen(false)}
            isLoading={formLoading}
          />
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
          {selectedCampaign && (
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
                    {selectedCampaign.conditions.map((condition, index) => (
                      <div key={index} className="wsms-text-[12px] wsms-p-2 wsms-bg-muted/30 wsms-rounded">
                        {condition.type} {condition.operator} {condition.value}
                      </div>
                    ))}
                  </div>
                </div>
              )}

              <div>
                <Label className="wsms-text-[12px] wsms-text-muted-foreground">{__('Message Content')}</Label>
                <div className="wsms-mt-1 wsms-p-3 wsms-bg-muted/30 wsms-rounded-lg wsms-text-[13px]">
                  {selectedCampaign.message_content || <span className="wsms-text-muted-foreground">{__('No message content')}</span>}
                </div>
              </div>

              <div>
                <Label className="wsms-text-[12px] wsms-text-muted-foreground">{__('Queue Status')}</Label>
                <div className="wsms-mt-1">
                  <QueueStatusBadge
                    queueStatus={selectedCampaign.queue_status}
                    nextSchedule={selectedCampaign.next_schedule}
                  />
                </div>
              </div>
            </div>
          )}
          <DialogFooter>
            <Button variant="outline" onClick={() => setIsViewOpen(false)}>
              {__('Close')}
            </Button>
            <Button onClick={() => { setIsViewOpen(false); handleEdit(selectedCampaign) }}>
              <Edit2 className="wsms-h-4 wsms-w-4 wsms-mr-2" />
              {__('Edit')}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>

      {/* Delete Confirmation */}
      <AlertDialog open={isDeleteOpen} onOpenChange={setIsDeleteOpen}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{__('Delete Campaign')}</AlertDialogTitle>
            <AlertDialogDescription>
              {__('Are you sure you want to delete "%s"? This action cannot be undone.').replace('%s', selectedCampaign?.title || '')}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel>{__('Cancel')}</AlertDialogCancel>
            <AlertDialogAction onClick={confirmDelete} className="wsms-bg-destructive wsms-text-destructive-foreground hover:wsms-bg-destructive/90">
              {__('Delete')}
            </AlertDialogAction>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </div>
  )
}
