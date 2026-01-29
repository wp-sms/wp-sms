import React, { useState, useCallback, useEffect, useMemo } from 'react'
import {
  RotateCcw,
  ShoppingCart,
  MessageSquare,
  DollarSign,
  TrendingUp,
  Clock,
  Trash2,
  RefreshCw,
  Search,
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
import { PageLoadingSkeleton } from '@/components/ui/skeleton'
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
  const { isAddonActive } = useSettings()
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
      <PageLoadingSkeleton />
    )
  }

  return (
    <div className="wsms-space-y-6 wsms-stagger-children">
      {/* Stats & Actions Header */}
      <div className="wsms-px-4 lg:wsms-px-5 wsms-py-4 wsms-rounded-lg wsms-bg-muted/30 wsms-border wsms-border-border">
        <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4 lg:wsms-flex lg:wsms-items-center lg:wsms-gap-8">
            {/* Recoverable Carts */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-blue-500/10">
                <ShoppingCart className="wsms-h-5 wsms-w-5 wsms-text-blue-500" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">{stats.recoverableCarts}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Recoverable')}</p>
              </div>
            </div>

            <div className="wsms-hidden lg:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* Recovered Carts */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-emerald-500/10">
                <TrendingUp className="wsms-h-5 wsms-w-5 wsms-text-emerald-500" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-emerald-500">{stats.recoveredCarts}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Recovered')}</p>
              </div>
            </div>

            <div className="wsms-hidden lg:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* Recoverable Revenue */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-amber-500/10">
                <DollarSign className="wsms-h-5 wsms-w-5 wsms-text-amber-500" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-foreground" dangerouslySetInnerHTML={{ __html: stats.recoverableRevenue }} />
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{__('Revenue')}</p>
              </div>
            </div>

            <div className="wsms-hidden lg:wsms-block wsms-w-px wsms-h-10 wsms-bg-border" aria-hidden="true" />

            {/* SMS Sent */}
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-purple-500/10">
                <MessageSquare className="wsms-h-5 wsms-w-5 wsms-text-purple-500" aria-hidden="true" />
              </div>
              <div>
                <p className="wsms-text-xl wsms-font-bold wsms-text-foreground">{stats.sentSMS}</p>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">{stats.followingSMS} {__('in queue')}</p>
              </div>
            </div>
        </div>
      </div>

      {/* Toolbar */}
      <Card className="wsms-relative wsms-z-10">
        <CardContent className="wsms-p-0">
          <form onSubmit={handleSearchSubmit} className="wsms-flex wsms-flex-col wsms-gap-3 xl:wsms-flex-row xl:wsms-items-center xl:wsms-gap-2 wsms-p-3">
            {/* Search */}
            <div className="wsms-relative wsms-w-full xl:wsms-w-[220px] xl:wsms-shrink-0">
              <Search className="wsms-absolute wsms-left-2.5 wsms-top-1/2 wsms--translate-y-1/2 wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-pointer-events-none" aria-hidden="true" />
              <Input
                type="text"
                placeholder={__('Search by phone...')}
                value={filters.search}
                onChange={(e) => handleFilterChange('search', e.target.value)}
                className="wsms-pl-8 wsms-h-9"
              />
            </div>

            {/* Filters */}
            <div className="wsms-grid wsms-grid-cols-2 wsms-gap-2 xl:wsms-flex xl:wsms-items-center xl:wsms-gap-2">
              <Select
                value={filters.type}
                onValueChange={(value) => handleFilterChange('type', value)}
              >
                <SelectTrigger className="wsms-h-9 wsms-w-full xl:wsms-w-[140px] wsms-text-[12px]" aria-label={__('Filter by type')}>
                  <SelectValue placeholder={__('All Carts')} />
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
                <SelectTrigger className="wsms-h-9 wsms-w-full xl:wsms-w-[130px] wsms-text-[12px]" aria-label={__('Filter by duration')}>
                  <SelectValue placeholder={__('All Time')} />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="all">{__('All Time')}</SelectItem>
                  <SelectItem value="today">{__('Today')}</SelectItem>
                  <SelectItem value="yesterday">{__('Yesterday')}</SelectItem>
                  <SelectItem value="lastWeek">{__('Last Week')}</SelectItem>
                  <SelectItem value="lastMonth">{__('Last Month')}</SelectItem>
                </SelectContent>
              </Select>
            </div>

            {/* Refresh */}
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={fetchData}
              disabled={isLoading}
              className="wsms-h-9 wsms-px-2.5 xl:wsms-ml-auto"
              aria-label={__('Refresh')}
            >
              <RefreshCw
                className={cn('wsms-h-4 wsms-w-4', isLoading && 'wsms-animate-spin')}
                aria-hidden="true"
              />
            </Button>
          </form>
        </CardContent>
      </Card>

      {/* Data Table */}
      <Card>
        <CardContent className="wsms-p-0">
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
