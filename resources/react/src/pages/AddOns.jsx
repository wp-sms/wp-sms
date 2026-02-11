import { useState, useEffect, useCallback } from 'react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { EmptyStateAction } from '@/components/ui/ux-helpers'
import { useToast } from '@/components/ui/toaster'
import { addonsApi } from '@/api/addonsApi'
import { __ } from '@/lib/utils'
import {
  AlertCircle,
  Blocks,
  ExternalLink,
  BookOpen,
  FileText,
  KeyRound,
  Loader2,
  Trash2,
  ArrowUpCircle,
  Pencil,
} from 'lucide-react'

const STATUS_TO_VARIANT = {
  activated: 'success',
  not_activated: 'inactive',
  not_licensed: 'failed',
  license_expired: 'warning',
  not_installed: 'default',
}

function AddOnCard({ addon, onLicenseChanged }) {
  const [licenseKey, setLicenseKey] = useState('')
  const [activating, setActivating] = useState(false)
  const [removingIndex, setRemovingIndex] = useState(null)
  const [activateError, setActivateError] = useState('')
  const [removeError, setRemoveError] = useState('')
  const [showUpdateInput, setShowUpdateInput] = useState(false)

  const needsLicense = addon.is_installed && (addon.status === 'not_licensed' || addon.status === 'license_expired')
  const licenses = addon.licenses || []
  const hasLicense = licenses.length > 0
  const showLicenseInput = needsLicense || showUpdateInput

  const handleActivate = async () => {
    if (!licenseKey.trim()) return
    setActivating(true)
    setActivateError('')
    try {
      const response = await addonsApi.activateLicense(addon.slug, licenseKey.trim())
      onLicenseChanged(response.message || __('License activated successfully.'))
      setLicenseKey('')
      setShowUpdateInput(false)
    } catch (err) {
      setActivateError(err.message || __('Failed to activate license.'))
    } finally {
      setActivating(false)
    }
  }

  const handleRemove = async (index) => {
    setRemovingIndex(index)
    setRemoveError('')
    try {
      const response = await addonsApi.removeLicense(addon.slug)
      onLicenseChanged(response.message || __('License removed successfully.'))
    } catch (err) {
      setRemoveError(err.message || __('Failed to remove license.'))
    } finally {
      setRemovingIndex(null)
    }
  }

  return (
    <div className="wsms-rounded-lg wsms-border wsms-border-border wsms-bg-card wsms-flex wsms-flex-col">
      {/* Header */}
      <div className="wsms-p-4 wsms-flex wsms-items-start wsms-gap-3">
        {addon.icon ? (
          <div className="wsms-shrink-0 wsms-w-10 wsms-h-10 wsms-rounded-lg wsms-bg-orange-500/10 wsms-flex wsms-items-center wsms-justify-center wsms-overflow-hidden">
            <img src={addon.icon} alt="" className="wsms-w-7 wsms-h-7 wsms-object-contain" />
          </div>
        ) : (
          <div className="wsms-shrink-0 wsms-w-10 wsms-h-10 wsms-rounded-lg wsms-bg-orange-500/10 wsms-flex wsms-items-center wsms-justify-center">
            <Blocks className="wsms-h-4.5 wsms-w-4.5 wsms-text-primary" />
          </div>
        )}
        <div className="wsms-flex-1 wsms-min-w-0">
          <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-flex-wrap">
            <span className="wsms-text-[13px] wsms-font-semibold wsms-text-foreground">{addon.name}</span>
            {addon.label && (
              <span className="wsms-px-1.5 wsms-py-0.5 wsms-text-[9px] wsms-font-bold wsms-uppercase wsms-rounded wsms-bg-primary wsms-text-primary-foreground">
                {addon.label}
              </span>
            )}
          </div>
          <div className="wsms-flex wsms-items-center wsms-gap-1.5 wsms-mt-1.5 wsms-flex-wrap">
            <StatusBadge variant={STATUS_TO_VARIANT[addon.status] || 'default'} size="sm">
              {addon.status_label}
            </StatusBadge>
            {addon.version && (
              <span className="wsms-text-[11px] wsms-text-muted-foreground wsms-shrink-0">v{addon.version}</span>
            )}
            {addon.is_update_available && (
              <a
                href="plugins.php?plugin_status=upgrade"
                className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-px-1.5 wsms-py-0.5 wsms-text-[9px] wsms-font-bold wsms-uppercase wsms-rounded wsms-bg-amber-500/10 wsms-text-amber-700 hover:wsms-bg-amber-500/20 wsms-transition-colors wsms-shrink-0"
              >
                <ArrowUpCircle className="wsms-h-2.5 wsms-w-2.5" />
                {__('Update Available')}
              </a>
            )}
          </div>
        </div>
      </div>

      {/* Body */}
      <div className="wsms-px-4 wsms-pb-4 wsms-flex-1 wsms-flex wsms-flex-col">
        {addon.short_description && (
          <p className="wsms-text-[12px] wsms-text-muted-foreground wsms-leading-relaxed wsms-line-clamp-2">
            {addon.short_description}
          </p>
        )}

        {/* Existing license keys with actions */}
        {hasLicense && (
          <div className="wsms-mt-3 wsms-space-y-2">
            {licenses.map((license, index) => (
              <div key={index} className="wsms-flex wsms-items-center wsms-justify-between wsms-p-2 wsms-rounded-md wsms-bg-muted/30">
                <div className="wsms-flex wsms-items-center wsms-gap-1.5 wsms-text-[11px] wsms-text-muted-foreground wsms-min-w-0">
                  <KeyRound className="wsms-h-3 wsms-w-3 wsms-shrink-0" />
                  <span className="wsms-font-mono wsms-truncate">{license.masked_key}</span>
                  {license.status === 'license_expired' && (
                    <span className="wsms-text-[9px] wsms-font-bold wsms-uppercase wsms-text-amber-600">
                      {__('Expired')}
                    </span>
                  )}
                </div>
                <div className="wsms-flex wsms-items-center wsms-gap-1 wsms-shrink-0">
                  <button
                    onClick={() => {
                      setShowUpdateInput(!showUpdateInput)
                      setLicenseKey('')
                      setActivateError('')
                    }}
                    className="wsms-p-1 wsms-rounded wsms-text-muted-foreground hover:wsms-text-primary hover:wsms-bg-primary/10 wsms-transition-colors"
                    title={__('Update license')}
                  >
                    <Pencil className="wsms-h-3 wsms-w-3" />
                  </button>
                  <button
                    onClick={() => handleRemove(index)}
                    disabled={removingIndex === index}
                    className="wsms-p-1 wsms-rounded wsms-text-muted-foreground hover:wsms-text-destructive hover:wsms-bg-destructive/10 wsms-transition-colors disabled:wsms-opacity-50"
                    title={__('Remove license')}
                  >
                    {removingIndex === index ? (
                      <Loader2 className="wsms-h-3 wsms-w-3 wsms-animate-spin" />
                    ) : (
                      <Trash2 className="wsms-h-3 wsms-w-3" />
                    )}
                  </button>
                </div>
              </div>
            ))}
            {removeError && (
              <div className="wsms-flex wsms-items-center wsms-justify-end">
                <div className="wsms-relative wsms-group">
                  <AlertCircle className="wsms-h-3.5 wsms-w-3.5 wsms-text-red-500 wsms-cursor-help" strokeWidth={2} />
                  <div className="wsms-absolute wsms-bottom-full wsms--end-2 wsms-mb-1.5 wsms-hidden group-hover:wsms-block wsms-z-50 wsms-pointer-events-none wsms-w-max">
                    <div className="wsms-bg-slate-800 wsms-text-white wsms-text-[11px] wsms-px-2 wsms-py-1 wsms-rounded wsms-max-w-[280px] wsms-shadow-lg" dangerouslySetInnerHTML={{ __html: removeError }} />
                  </div>
                </div>
              </div>
            )}
          </div>
        )}

        {/* License input (new activation or update) */}
        {showLicenseInput && (
          <div className="wsms-mt-3 wsms-space-y-2">
            <div className="wsms-flex wsms-gap-2">
              <div className="wsms-relative wsms-flex-1">
                <Input
                  value={licenseKey}
                  onChange={(e) => {
                    setLicenseKey(e.target.value)
                    setActivateError('')
                  }}
                  placeholder={showUpdateInput ? __('Enter new license key') : __('Enter license key')}
                  className={`!wsms-h-8 wsms-text-[12px] ${activateError ? 'wsms-border-red-500 focus-visible:wsms-ring-red-500/30 wsms-pe-7' : ''}`}
                  onKeyDown={(e) => e.key === 'Enter' && handleActivate()}
                />
                {activateError && (
                  <div className="wsms-absolute wsms-end-2 wsms-top-1/2 wsms--translate-y-1/2 wsms-group">
                    <AlertCircle className="wsms-h-3.5 wsms-w-3.5 wsms-text-red-500 wsms-cursor-help" strokeWidth={2} />
                    <div className="wsms-absolute wsms-bottom-full wsms--end-2 wsms-mb-1.5 wsms-hidden group-hover:wsms-block wsms-z-50 wsms-pointer-events-none wsms-w-max">
                      <div className="wsms-bg-slate-800 wsms-text-white wsms-text-[11px] wsms-px-2 wsms-py-1 wsms-rounded wsms-max-w-[280px] wsms-shadow-lg" dangerouslySetInnerHTML={{ __html: activateError }} />
                    </div>
                  </div>
                )}
              </div>
              <Button
                size="sm"
                className="wsms-shrink-0 !wsms-h-8"
                onClick={handleActivate}
                disabled={activating || !licenseKey.trim()}
              >
                {activating ? (
                  <Loader2 className="wsms-h-3.5 wsms-w-3.5 wsms-animate-spin" />
                ) : showUpdateInput ? (
                  __('Update')
                ) : (
                  __('Activate')
                )}
              </Button>
              {showUpdateInput && (
                <Button
                  size="sm"
                  variant="outline"
                  className="wsms-shrink-0 !wsms-h-8"
                  onClick={() => {
                    setShowUpdateInput(false)
                    setLicenseKey('')
                    setActivateError('')
                  }}
                >
                  {__('Cancel')}
                </Button>
              )}
            </div>
          </div>
        )}
      </div>

      {/* Footer links */}
      <div className="wsms-px-4 wsms-py-3 wsms-border-t wsms-border-border wsms-flex wsms-items-center wsms-gap-3 wsms-flex-wrap">
        {addon.product_url && (
          <a
            href={addon.product_url}
            target="_blank"
            rel="noopener noreferrer"
            className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-text-primary hover:wsms-underline"
          >
            <ExternalLink className="wsms-h-3 wsms-w-3" />
            {addon.is_installed ? __('Details') : __('Get Add-On')}
          </a>
        )}
        {addon.documentation_url && (
          <a
            href={addon.documentation_url}
            target="_blank"
            rel="noopener noreferrer"
            className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-text-primary hover:wsms-underline"
          >
            <BookOpen className="wsms-h-3 wsms-w-3" />
            {__('Docs')}
          </a>
        )}
        {addon.changelog_url && (
          <a
            href={addon.changelog_url}
            target="_blank"
            rel="noopener noreferrer"
            className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-text-muted-foreground hover:wsms-text-foreground wsms-transition-colors"
          >
            <FileText className="wsms-h-3 wsms-w-3" />
            {__('Changelog')}
          </a>
        )}
      </div>
    </div>
  )
}

function AddOnsLoadingSkeleton() {
  return (
    <div className="wsms-space-y-6" role="status" aria-label="Loading">
      <div className="wsms-rounded-lg wsms-border wsms-border-border wsms-bg-card wsms-p-5">
        <div className="wsms-flex wsms-items-center wsms-gap-4">
          <Skeleton className="wsms-h-10 wsms-w-10 wsms-rounded-lg" />
          <div className="wsms-space-y-2 wsms-flex-1">
            <Skeleton className="wsms-h-4 wsms-w-32" />
            <Skeleton className="wsms-h-3 wsms-w-48" />
          </div>
        </div>
      </div>
      <div className="wsms-grid wsms-grid-cols-1 lg:wsms-grid-cols-2 xl:wsms-grid-cols-3 wsms-gap-3">
        {[1, 2, 3, 4, 5, 6].map((i) => (
          <div key={i} className="wsms-rounded-lg wsms-border wsms-border-border wsms-bg-card wsms-flex wsms-flex-col">
            <div className="wsms-p-4 wsms-flex wsms-items-start wsms-gap-3">
              <Skeleton className="wsms-h-10 wsms-w-10 wsms-rounded-lg wsms-shrink-0" />
              <div className="wsms-space-y-2 wsms-flex-1">
                <Skeleton className="wsms-h-4 wsms-w-24" />
                <Skeleton className="wsms-h-3 wsms-w-16" />
              </div>
            </div>
            <div className="wsms-px-4 wsms-pb-4 wsms-space-y-2">
              <Skeleton className="wsms-h-3 wsms-w-full" />
              <Skeleton className="wsms-h-3 wsms-w-4/5" />
            </div>
            <div className="wsms-px-4 wsms-py-3 wsms-border-t wsms-border-border wsms-flex wsms-gap-3">
              <Skeleton className="wsms-h-3 wsms-w-12" />
              <Skeleton className="wsms-h-3 wsms-w-16" />
            </div>
          </div>
        ))}
      </div>
      <span className="wsms-sr-only">Loading...</span>
    </div>
  )
}

export default function AddOns() {
  const [addons, setAddons] = useState([])
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const { toast } = useToast()

  const fetchAddons = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const data = await addonsApi.getAll()
      setAddons(data.addons || [])
    } catch (err) {
      setError(err.message || __('Failed to load add-ons.'))
    } finally {
      setLoading(false)
    }
  }, [])

  useEffect(() => {
    fetchAddons()
  }, [fetchAddons])

  const handleLicenseChanged = (message) => {
    toast({ title: message, variant: 'success' })
    fetchAddons()
  }

  if (loading) {
    return <AddOnsLoadingSkeleton />
  }

  if (error) {
    return (
      <Card className="wsms-border-dashed">
        <EmptyStateAction
          icon={Blocks}
          title={__('Unable to load add-ons')}
          description={error}
          action={fetchAddons}
          actionLabel={__('Retry')}
        />
      </Card>
    )
  }

  if (addons.length === 0) {
    return (
      <Card className="wsms-border-dashed">
        <EmptyStateAction
          icon={Blocks}
          title={__('No add-ons available')}
          description={__('Add-ons extend WSMS with extra features. Check back later for available add-ons.')}
        />
      </Card>
    )
  }

  // Sort: active addons first, then by name
  const statusOrder = { activated: 0, not_activated: 1, not_licensed: 2, license_expired: 3, not_installed: 4 }
  const sortedAddons = [...addons].sort((a, b) => {
    const orderDiff = (statusOrder[a.status] ?? 5) - (statusOrder[b.status] ?? 5)
    if (orderDiff !== 0) return orderDiff
    return (a.name || '').localeCompare(b.name || '')
  })

  return (
    <div className="wsms-space-y-6 wsms-stagger-children">
      <Card>
        <CardHeader>
          <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
            <Blocks className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            {__('Add-Ons')}
          </CardTitle>
          <CardDescription>
            {__('Manage add-ons to extend WSMS functionality')}
          </CardDescription>
        </CardHeader>
        <CardContent>
          <div className="wsms-grid wsms-grid-cols-1 lg:wsms-grid-cols-2 xl:wsms-grid-cols-3 wsms-gap-3">
            {sortedAddons.map((addon) => (
              <AddOnCard key={addon.slug} addon={addon} onLicenseChanged={handleLicenseChanged} />
            ))}
          </div>
        </CardContent>
      </Card>
    </div>
  )
}
