import React, { useState, useCallback, useEffect, useMemo } from 'react'
import {
  RotateCcw,
  ShoppingCart,
  MessageSquare,
  DollarSign,
  TrendingUp,
  Clock,
  Settings,
  Trash2,
  RefreshCw,
  Search,
  Filter,
  User,
  Phone,
  CheckCircle,
  XCircle,
  AlertCircle,
  Loader2,
  ExternalLink,
} from 'lucide-react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { DataTable } from '@/components/ui/data-table'
import { DeleteConfirmDialog } from '@/components/shared/DeleteConfirmDialog'
import { PageLoadingSkeleton, Skeleton } from '@/components/ui/skeleton'
import { __, cn } from '@/lib/utils'
import { useSettings } from '@/context/SettingsContext'
import { useToast } from '@/components/ui/toaster'
import { woocommerceProApi } from '@/api/woocommerceProApi'

// SMS status badge component
function SmsStatusBadge({ status, time }) {
  const statusConfig = {
    unscheduled: {
      label: __('Not Scheduled'),
      icon: AlertCircle,
      className: 'wsms-bg-gray-100 wsms-text-gray-600',
    },
    not_sent: {
      label: __('Not Sent'),
      icon: XCircle,
      className: 'wsms-bg-gray-100 wsms-text-gray-600',
    },
    in_queue: {
      label: __('In Queue'),
      icon: Clock,
      className: 'wsms-bg-blue-100 wsms-text-blue-700',
    },
    sent: {
      label: __('Sent'),
      icon: CheckCircle,
      className: 'wsms-bg-emerald-100 wsms-text-emerald-700',
    },
  }

  const config = statusConfig[status] || statusConfig.unscheduled
  const Icon = config.icon

  return (
    <div className="wsms-flex wsms-flex-col wsms-gap-1">
      <span
        className={`wsms-inline-flex wsms-items-center wsms-gap-1.5 wsms-px-2 wsms-py-1 wsms-rounded-full wsms-text-[11px] wsms-font-medium ${config.className}`}
      >
        <Icon className="wsms-h-3 wsms-w-3" />
        {config.label}
      </span>
      {time && <span className="wsms-text-[10px] wsms-text-muted-foreground">{time}</span>}
    </div>
  )
}

// Stats card component
function StatsCard({ icon: Icon, label, value, description, color, isHtml = false }) {
  return (
    <Card>
      <CardContent className="wsms-p-4">
        <div className="wsms-flex wsms-items-center wsms-gap-3">
          <div
            className={`wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg ${color}`}
          >
            <Icon className="wsms-h-4 wsms-w-4 wsms-text-primary" />
          </div>
          <div className="wsms-flex-1">
            <p className="wsms-text-[12px] wsms-text-muted-foreground">{label}</p>
            {isHtml ? (
              <p
                className="wsms-text-lg wsms-font-semibold wsms-text-foreground"
                dangerouslySetInnerHTML={{ __html: value }}
              />
            ) : (
              <p className="wsms-text-lg wsms-font-semibold wsms-text-foreground">{value}</p>
            )}
            {description && (
              <p className="wsms-text-[10px] wsms-text-muted-foreground">{description}</p>
            )}
          </div>
        </div>
      </CardContent>
    </Card>
  )
}

// Stats skeleton
function StatsSkeleton() {
  return (
    <div className="wsms-grid wsms-grid-cols-4 wsms-gap-4">
      {[1, 2, 3, 4].map((i) => (
        <Card key={i}>
          <CardContent className="wsms-p-4">
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <Skeleton className="wsms-h-10 wsms-w-10 wsms-rounded-lg" />
              <div className="wsms-flex-1 wsms-space-y-2">
                <Skeleton className="wsms-h-3 wsms-w-20" />
                <Skeleton className="wsms-h-5 wsms-w-12" />
              </div>
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  )
}

// Column definitions
const cartColumns = [
  {
    id: 'customer',
    accessorKey: 'cart_owner',
    header: __('Customer'),
    cell: ({ row }) => (
      <div className="wsms-flex wsms-items-center wsms-gap-2">
        <div className="wsms-flex wsms-h-8 wsms-w-8 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-primary/10">
          <User className="wsms-h-4 wsms-w-4 wsms-text-primary" />
        </div>
        <span className="wsms-text-[13px] wsms-font-medium">
          {row.cart_owner || __('Guest')}
        </span>
      </div>
    ),
  },
  {
    id: 'phone',
    accessorKey: 'phone_number',
    header: __('Phone'),
    cell: ({ row }) => (
      <div className="wsms-flex wsms-items-center wsms-gap-1.5 wsms-text-[13px] wsms-text-muted-foreground">
        <Phone className="wsms-h-3.5 wsms-w-3.5" />
        {row.phone_number || '-'}
      </div>
    ),
  },
  {
    id: 'cart_total',
    accessorKey: 'cart_total',
    header: __('Cart Total'),
    cell: ({ row }) => (
      <span
        className="wsms-text-[13px] wsms-font-medium"
        dangerouslySetInnerHTML={{ __html: row.cart_total }}
      />
    ),
  },
  {
    id: 'recovered',
    accessorKey: 'is_recovered',
    header: __('Recovered'),
    cell: ({ row }) =>
      row.is_recovered === 'Yes' ? (
        <span className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-emerald-600 wsms-text-[12px]">
          <CheckCircle className="wsms-h-3.5 wsms-w-3.5" />
          {__('Yes')}
        </span>
      ) : (
        <span className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-muted-foreground wsms-text-[12px]">
          <XCircle className="wsms-h-3.5 wsms-w-3.5" />
          {__('No')}
        </span>
      ),
  },
  {
    id: 'sms_status',
    accessorKey: 'sms_status',
    header: __('SMS Status'),
    cell: ({ row }) => (
      <SmsStatusBadge
        status={row.sms_status?.status}
        time={row.sms_status?.time}
      />
    ),
  },
]

// Row actions
function getCartRowActions({ onDelete }) {
  return [
    {
      label: __('Delete'),
      icon: Trash2,
      onClick: onDelete,
      variant: 'destructive',
    },
  ]
}

export default function CartAbandonment() {
  const { setCurrentPage, isAddonActive } = useSettings()
  const { toast } = useToast()
  const hasWooCommercePro = isAddonActive('woocommerce')

  // Show placeholder if WooCommerce Pro add-on is not active
  if (!hasWooCommercePro) {
    return (
      <div className="wsms-space-y-6">
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <RotateCcw className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              {__('Cart Abandonment')}
            </CardTitle>
            <CardDescription>
              {__('Recover abandoned carts with automated SMS reminders.')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-rounded-lg wsms-border wsms-border-dashed wsms-bg-muted/30 wsms-p-6 wsms-text-center">
              <AlertCircle className="wsms-mx-auto wsms-h-10 wsms-w-10 wsms-text-muted-foreground wsms-mb-3" />
              <h3 className="wsms-font-medium wsms-mb-2">{__('WooCommerce Pro Add-on Required')}</h3>
              <p className="wsms-text-sm wsms-text-muted-foreground wsms-mb-4">
                {__('Install and activate the WSMS WooCommerce Pro add-on to access Cart Abandonment features.')}
              </p>
              <Button variant="outline" asChild>
                <a
                  href="https://wp-sms-pro.com/product/wp-sms-woocommerce-pro/"
                  target="_blank"
                  rel="noopener noreferrer"
                >
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

  // State
  const [isLoading, setIsLoading] = useState(true)
  const [initialLoadDone, setInitialLoadDone] = useState(false)
  const [carts, setCarts] = useState([])
  const [stats, setStats] = useState({
    recoveredCarts: 0,
    recoverableCarts: 0,
    sentSMS: 0,
    followingSMS: 0,
    recoverableRevenue: '$0.00',
    recoveredRevenue: '$0.00',
  })
  const [filters, setFilters] = useState({
    duration: 'all',
    type: 'allCarts',
    search: '',
  })

  // Delete dialog state
  const [deleteTarget, setDeleteTarget] = useState(null)
  const [isDeleting, setIsDeleting] = useState(false)

  // Fetch cart data
  const fetchData = useCallback(async () => {
    setIsLoading(true)
    try {
      const result = await woocommerceProApi.getCarts({
        duration: filters.duration === 'all' ? '' : filters.duration,
        type: filters.type,
        search: filters.search,
      })
      setCarts(result.items || [])
      if (result.stats) {
        setStats(result.stats)
      }
    } catch (error) {
      console.error('Failed to fetch cart data:', error)
      toast({
        title: __('Error'),
        description: error.message || __('Failed to load cart abandonment data'),
        variant: 'destructive',
      })
    } finally {
      setIsLoading(false)
      setInitialLoadDone(true)
    }
  }, [filters, toast])

  // Initial load
  useEffect(() => {
    fetchData()
  }, [fetchData])

  // Handle delete
  const handleDeleteConfirm = async () => {
    if (!deleteTarget) return

    setIsDeleting(true)
    try {
      await woocommerceProApi.deleteCart(deleteTarget.customer_id, deleteTarget.cart_hash)
      toast({
        title: __('Cart deleted successfully'),
        variant: 'success',
      })
      setCarts((prev) =>
        prev.filter(
          (c) =>
            c.customer_id !== deleteTarget.customer_id ||
            c.cart_hash !== deleteTarget.cart_hash
        )
      )
    } catch (error) {
      toast({
        title: error.message || __('Failed to delete cart'),
        variant: 'destructive',
      })
    } finally {
      setIsDeleting(false)
      setDeleteTarget(null)
    }
  }

  // Handle filter change
  const handleFilterChange = (key, value) => {
    setFilters((prev) => ({ ...prev, [key]: value }))
  }

  // Handle search submit
  const handleSearchSubmit = (e) => {
    e.preventDefault()
    fetchData()
  }

  // Row actions
  const rowActions = useMemo(
    () =>
      getCartRowActions({
        onDelete: (cart) => setDeleteTarget(cart),
      }),
    []
  )

  // Row ID function for composite keys
  const getRowId = useCallback(
    (row, index) => `${row.customer_id}-${row.cart_hash}-${index}`,
    []
  )

  // Loading skeleton
  if (!initialLoadDone) {
    return (
      <div className="wsms-space-y-6">
        {/* Hero skeleton */}
        <div className="wsms-rounded-lg wsms-border wsms-border-border wsms-bg-card wsms-p-5">
          <div className="wsms-flex wsms-items-center wsms-gap-4">
            <Skeleton className="wsms-h-12 wsms-w-12 wsms-rounded-xl" />
            <div className="wsms-space-y-2 wsms-flex-1">
              <Skeleton className="wsms-h-5 wsms-w-48" />
              <Skeleton className="wsms-h-3 wsms-w-96" />
            </div>
          </div>
        </div>
        <StatsSkeleton />
        <PageLoadingSkeleton />
      </div>
    )
  }

  return (
    <div className="wsms-space-y-6 wsms-stagger-children">
      {/* Hero Section */}
      <div className="wsms-relative wsms-overflow-hidden wsms-rounded-lg wsms-bg-gradient-to-br wsms-from-primary/5 wsms-via-primary/10 wsms-to-transparent wsms-border wsms-border-primary/20">
        <div className="wsms-absolute wsms-top-0 wsms-right-0 wsms-w-32 wsms-h-32 wsms-bg-primary/5 wsms-rounded-full wsms--translate-y-1/2 wsms-translate-x-1/2" />
        <div className="wsms-relative wsms-p-6">
          <div className="wsms-flex wsms-items-start wsms-justify-between">
            <div className="wsms-flex wsms-items-start wsms-gap-4">
              <div className="wsms-flex wsms-h-12 wsms-w-12 wsms-items-center wsms-justify-center wsms-rounded-xl wsms-bg-primary/10 wsms-shrink-0">
                <RotateCcw className="wsms-h-6 wsms-w-6 wsms-text-primary" />
              </div>
              <div>
                <h2 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-mb-1">
                  {__('Cart Abandonment Recovery')}
                </h2>
                <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-max-w-lg">
                  {__(
                    'Recover lost sales by automatically sending SMS reminders to customers who abandoned their shopping carts.'
                  )}
                </p>
              </div>
            </div>
            <Button variant="outline" size="sm" onClick={() => setCurrentPage('woocommerce-pro')}>
              <Settings className="wsms-h-4 wsms-w-4 wsms-mr-2" />
              {__('Settings')}
            </Button>
          </div>
        </div>
      </div>

      {/* Stats Cards */}
      <div className="wsms-grid wsms-grid-cols-4 wsms-gap-4">
        <StatsCard
          icon={ShoppingCart}
          label={__('Recoverable Carts')}
          value={stats.recoverableCarts}
          color="wsms-bg-blue-500/10 wsms-text-blue-500"
        />
        <StatsCard
          icon={TrendingUp}
          label={__('Recovered Carts')}
          value={stats.recoveredCarts}
          color="wsms-bg-emerald-500/10 wsms-text-emerald-500"
        />
        <StatsCard
          icon={DollarSign}
          label={__('Recoverable Revenue')}
          value={stats.recoverableRevenue}
          color="wsms-bg-amber-500/10 wsms-text-amber-500"
          isHtml
        />
        <StatsCard
          icon={MessageSquare}
          label={__('SMS Sent')}
          value={stats.sentSMS}
          description={`${stats.followingSMS} ${__('in queue')}`}
          color="wsms-bg-purple-500/10 wsms-text-purple-500"
        />
      </div>

      {/* Filters */}
      <Card>
        <CardHeader className="wsms-pb-4">
          <div className="wsms-flex wsms-items-center wsms-justify-between">
            <div>
              <CardTitle>{__('Abandoned Carts')}</CardTitle>
              <CardDescription>
                {__('View and manage abandoned shopping carts')}
              </CardDescription>
            </div>
            <Button variant="outline" size="sm" onClick={fetchData} disabled={isLoading}>
              <RefreshCw
                className={cn('wsms-h-4 wsms-w-4 wsms-mr-2', isLoading && 'wsms-animate-spin')}
              />
              {__('Refresh')}
            </Button>
          </div>
        </CardHeader>
        <CardContent>
          <form onSubmit={handleSearchSubmit} className="wsms-flex wsms-gap-3 wsms-mb-4">
            <div className="wsms-relative wsms-flex-1 wsms-max-w-xs">
              <Search className="wsms-absolute wsms-left-3 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <Input
                placeholder={__('Search by phone number...')}
                value={filters.search}
                onChange={(e) => handleFilterChange('search', e.target.value)}
                className="wsms-pl-9"
              />
            </div>
            <Select
              value={filters.type}
              onValueChange={(value) => handleFilterChange('type', value)}
            >
              <SelectTrigger className="wsms-w-[180px]">
                <Filter className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                <SelectValue placeholder={__('Filter by type')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="allCarts">{__('All Carts')}</SelectItem>
                <SelectItem value="abandonedCarts">{__('Abandoned Only')}</SelectItem>
                <SelectItem value="recoveredCarts">{__('Recovered Only')}</SelectItem>
              </SelectContent>
            </Select>
            <Select
              value={filters.duration}
              onValueChange={(value) => handleFilterChange('duration', value)}
            >
              <SelectTrigger className="wsms-w-[180px]">
                <Clock className="wsms-h-4 wsms-w-4 wsms-mr-2" />
                <SelectValue placeholder={__('Time period')} />
              </SelectTrigger>
              <SelectContent>
                <SelectItem value="all">{__('All Time')}</SelectItem>
                <SelectItem value="today">{__('Today')}</SelectItem>
                <SelectItem value="yesterday">{__('Yesterday')}</SelectItem>
                <SelectItem value="lastWeek">{__('Last Week')}</SelectItem>
                <SelectItem value="lastMonth">{__('Last Month')}</SelectItem>
              </SelectContent>
            </Select>
            <Button type="submit" disabled={isLoading}>
              {__('Apply')}
            </Button>
          </form>

          {/* Data Table */}
          <DataTable
            columns={cartColumns}
            data={carts}
            loading={isLoading}
            rowActions={rowActions}
            getRowId={getRowId}
            emptyMessage={__('No abandoned carts found')}
            emptyIcon={ShoppingCart}
          />
        </CardContent>
      </Card>

      {/* Delete Confirmation Dialog */}
      <DeleteConfirmDialog
        isOpen={!!deleteTarget}
        onClose={() => setDeleteTarget(null)}
        onConfirm={handleDeleteConfirm}
        isSaving={isDeleting}
        title={__('Delete Abandoned Cart')}
        description={__('Are you sure you want to delete this abandoned cart record? This action cannot be undone.')}
      >
        {deleteTarget && (
          <div className="wsms-p-4 wsms-rounded-md wsms-bg-muted/50 wsms-border wsms-border-border">
            <div className="wsms-flex wsms-items-center wsms-gap-2">
              <User className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              <span className="wsms-text-[13px] wsms-font-medium">
                {deleteTarget.cart_owner || __('Guest')}
              </span>
              {deleteTarget.phone_number && (
                <span className="wsms-text-[12px] wsms-text-muted-foreground">
                  ({deleteTarget.phone_number})
                </span>
              )}
            </div>
          </div>
        )}
      </DeleteConfirmDialog>
    </div>
  )
}
