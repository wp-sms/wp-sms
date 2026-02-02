import { useState, useEffect, useCallback } from 'react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { EmptyStateAction, ValidationMessage } from '@/components/ui/ux-helpers'
import { useToast } from '@/components/ui/toaster'
import { addonsApi } from '@/api/addonsApi'
import { __ } from '@/lib/utils'
import {
  Blocks,
  ExternalLink,
  BookOpen,
  FileText,
  Settings,
  KeyRound,
  Loader2,
  Trash2,
  ArrowUpCircle,
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
  const [removing, setRemoving] = useState(false)
  const [activateError, setActivateError] = useState('')
  const [removeError, setRemoveError] = useState('')

  const needsLicense = addon.is_installed && (addon.status === 'not_licensed' || addon.status === 'license_expired')
  const hasLicense = !!addon.license_key_masked

  const handleActivate = async () => {
    if (!licenseKey.trim()) return
    setActivating(true)
    setActivateError('')
    try {
      const response = await addonsApi.activateLicense(addon.slug, licenseKey.trim())
      onLicenseChanged(response.message || __('License activated successfully.'))
      setLicenseKey('')
    } catch (err) {
      setActivateError(err.message || __('Failed to activate license.'))
    } finally {
      setActivating(false)
    }
  }

  const handleRemove = async () => {
    setRemoving(true)
    setRemoveError('')
    try {
      const response = await addonsApi.removeLicense(addon.slug)
      onLicenseChanged(response.message || __('License removed successfully.'))
    } catch (err) {
      setRemoveError(err.message || __('Failed to remove license.'))
    } finally {
      setRemoving(false)
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

        {/* Existing license key */}
        {hasLicense && (
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-mt-3 wsms-p-2 wsms-rounded-md wsms-bg-muted/30">
            <div className="wsms-flex wsms-items-center wsms-gap-1.5 wsms-text-[11px] wsms-text-muted-foreground wsms-min-w-0">
              <KeyRound className="wsms-h-3 wsms-w-3 wsms-shrink-0" />
              <span className="wsms-font-mono wsms-truncate">{addon.license_key_masked}</span>
            </div>
            <button
              onClick={handleRemove}
              disabled={removing}
              className="wsms-p-1 wsms-rounded wsms-text-muted-foreground hover:wsms-text-destructive hover:wsms-bg-destructive/10 wsms-transition-colors disabled:wsms-opacity-50"
              title={__('Remove license')}
            >
              {removing ? (
                <Loader2 className="wsms-h-3 wsms-w-3 wsms-animate-spin" />
              ) : (
                <Trash2 className="wsms-h-3 wsms-w-3" />
              )}
            </button>
          </div>
        )}

        {/* License activation form */}
        {needsLicense && (
          <div className="wsms-mt-3 wsms-space-y-2">
            <div className="wsms-flex wsms-gap-2">
              <Input
                value={licenseKey}
                onChange={(e) => {
                  setLicenseKey(e.target.value)
                  setActivateError('')
                }}
                placeholder={__('Enter license key')}
                className="!wsms-h-8 wsms-text-[12px]"
                onKeyDown={(e) => e.key === 'Enter' && handleActivate()}
              />
              <Button
                size="sm"
                className="wsms-shrink-0 !wsms-h-8"
                onClick={handleActivate}
                disabled={activating || !licenseKey.trim()}
              >
                {activating ? (
                  <Loader2 className="wsms-h-3.5 wsms-w-3.5 wsms-animate-spin" />
                ) : (
                  __('Activate')
                )}
              </Button>
            </div>
            {activateError && <ValidationMessage type="error">{activateError}</ValidationMessage>}
          </div>
        )}

        {/* Remove license error */}
        {removeError && <ValidationMessage type="error" className="wsms-mt-2">{removeError}</ValidationMessage>}
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
        {addon.settings_url && addon.is_installed && addon.status === 'activated' && (
          <a
            href={addon.settings_url}
            className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-text-muted-foreground hover:wsms-text-foreground wsms-transition-colors"
          >
            <Settings className="wsms-h-3 wsms-w-3" />
            {__('Settings')}
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
          description={__('Add-ons extend WP SMS with extra features. Check back later for available add-ons.')}
        />
      </Card>
    )
  }

  const activeAddons = addons.filter((a) => a.status === 'activated')
  const availableAddons = addons.filter((a) => a.status !== 'activated')

  return (
    <div className="wsms-space-y-6 wsms-stagger-children">
      {activeAddons.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Blocks className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              {__('Active Add-Ons')}
            </CardTitle>
            <CardDescription>
              {__('Licensed and activated add-ons on your site')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-grid wsms-grid-cols-1 lg:wsms-grid-cols-2 xl:wsms-grid-cols-3 wsms-gap-3">
              {activeAddons.map((addon) => (
                <AddOnCard key={addon.slug} addon={addon} onLicenseChanged={handleLicenseChanged} />
              ))}
            </div>
          </CardContent>
        </Card>
      )}

      {availableAddons.length > 0 && (
        <Card>
          <CardHeader>
            <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
              <Blocks className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground" />
              {__('Available Add-Ons')}
            </CardTitle>
            <CardDescription>
              {__('Install and activate add-ons to extend WP SMS functionality')}
            </CardDescription>
          </CardHeader>
          <CardContent>
            <div className="wsms-grid wsms-grid-cols-1 lg:wsms-grid-cols-2 xl:wsms-grid-cols-3 wsms-gap-3">
              {availableAddons.map((addon) => (
                <AddOnCard key={addon.slug} addon={addon} onLicenseChanged={handleLicenseChanged} />
              ))}
            </div>
          </CardContent>
        </Card>
      )}
    </div>
  )
}
