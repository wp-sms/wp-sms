import { __, _n, sprintf } from '@wordpress/i18n'
import React, { useState, useEffect, useCallback } from 'react'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { StatusBadge } from '@/components/shared/StatusBadge'
import { AlertDialog, AlertDialogContent, AlertDialogHeader, AlertDialogTitle, AlertDialogDescription, AlertDialogFooter, AlertDialogCancel } from '@/components/ui/alert-dialog'
import { EmptyStateAction } from '@/components/ui/ux-helpers'
import { useToast } from '@/components/ui/toaster'
import { addonsApi } from '@/api/addonsApi'
import AllInOneCompare from '@/components/shared/AllInOneCompare'
import {
  Blocks,
  ExternalLink,
  BookOpen,
  FileText,
  KeyRound,
  Loader2,
  Trash2,
  ArrowUpCircle,
  ShoppingCart,
  User,
  AlertCircle,
  Trash2 as TrashIcon,
  Power,
  CheckCircle2,
  Settings as SettingsIcon,
} from 'lucide-react'
import { cn, appendUtm } from '@/lib/utils'
import { useSettings } from '@/context/SettingsContext'

const STATUS_TO_VARIANT = {
  activated: 'success',
  not_activated: 'inactive',
  not_licensed: 'failed',
  license_expired: 'warning',
  not_installed: 'default',
}

const TONES = {
  unlock: {
    tint: 'linear-gradient(135deg, rgba(234,120,50,.10), rgba(234,120,50,0) 65%)',
    border: 'rgba(234,150,90,.45)',
    icon: KeyRound,
    iconClass: 'wsms-bg-primary/10 wsms-text-primary',
  },
  expired: {
    tint: 'linear-gradient(135deg, rgba(239,68,68,.10), rgba(239,68,68,0) 65%)',
    border: 'rgba(239,120,110,.5)',
    icon: AlertCircle,
    iconClass: 'wsms-bg-red-500/10 wsms-text-red-600',
  },
  off: {
    tint: 'linear-gradient(135deg, rgba(245,158,11,.12), rgba(245,158,11,0) 65%)',
    border: 'rgba(245,180,60,.5)',
    icon: Power,
    iconClass: 'wsms-bg-amber-500/15 wsms-text-amber-600',
  },
  ok: {
    tint: 'linear-gradient(135deg, rgba(16,185,129,.10), rgba(16,185,129,0) 65%)',
    border: 'rgba(52,190,140,.45)',
    icon: CheckCircle2,
    iconClass: 'wsms-bg-emerald-500/10 wsms-text-emerald-600',
  },
}


/**
 * The license server answers with a sentence that can hold links (for example "view this licence" or "contact support").
 * Keep only plain text and https or mailto links.
 */
function ServerMessage({ html }) {
  const doc = new DOMParser().parseFromString(`<div>${html || ''}</div>`, 'text/html')
  const render = (node, key) => {
    if (node.nodeType === 3) return node.textContent
    if (node.nodeType !== 1) return null
    const children = [...node.childNodes].map((child, i) => render(child, `${key}-${i}`))
    if (node.tagName === 'A') {
      const href = node.getAttribute('href') || ''
      if (/^(https:\/\/|mailto:)/i.test(href)) {
        return <a key={key} href={href} target={href.startsWith('mailto:') ? undefined : '_blank'} rel="noopener noreferrer" className="wsms-font-semibold wsms-text-primary wsms-underline wsms-underline-offset-2 hover:wsms-no-underline">{children}</a>
      }
    }
    return <React.Fragment key={key}>{children}</React.Fragment>
  }
  return <>{[...(doc.body.firstChild?.childNodes || [])].map((n, i) => render(n, String(i)))}</>
}

function hasLink(html) {
  return /<a\s/i.test(html || '')
}

function collectKeys(addons) {
  const map = new Map()
  addons.forEach((addon) => {
    ;(addon.licenses || []).forEach((license) => {
      const key = license.masked_key
      if (!map.has(key)) {
        map.set(key, { masked: key, status: license.status, covers: license.covers || 1, slug: addon.slug, renewal: license.renewal || null, sku: license.sku || null, validUntil: license.valid_until || null, maxDomains: license.max_domains })
      }
    })
  })
  return [...map.values()]
}

/**
 * What to say about when a key runs out, and whether it needs attention.
 * `tone` is 'ok', 'soon' (30 days or less) or 'expired'.
 */
function describeExpiry(key) {
  const date = key.validUntil ? new Date(`${key.validUntil}T00:00:00`) : null
  if (!date || Number.isNaN(date.getTime())) return null
  const days = Math.ceil((date.getTime() - Date.now()) / 86400000)
  const formatted = date.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' })
  if (key.status === 'license_expired' || days < 0) return { tone: 'expired', text: sprintf(__('Expired %s', 'wp-sms'), formatted) }
  if (days <= 30) return { tone: 'soon', text: days === 0 ? __('Expires today', 'wp-sms') : sprintf(_n('Expires in %d day', 'Expires in %d days', days, 'wp-sms'), days) }
  return { tone: 'ok', text: sprintf(__('Expires %s', 'wp-sms'), formatted) }
}

function describeSites(key) {
  const n = Number(key.maxDomains)
  if (!key.maxDomains || Number.isNaN(n) || n <= 0) return key.maxDomains === undefined || key.maxDomains === null ? null : __('Unlimited sites', 'wp-sms')
  return sprintf(_n('%d site', '%d sites', n, 'wp-sms'), n)
}

/**
 * The one place to enter, review and renew licenses.
 */
function LicenseHero({ addons, summary, onChanged, compact = false }) {
  const { toast } = useToast()
  const [licenseKey, setLicenseKey] = useState('')
  const [activating, setActivating] = useState(false)
  const [showAdd, setShowAdd] = useState(false)
  const [confirmKey, setConfirmKey] = useState(null)
  const [removingKey, setRemovingKey] = useState(null)
  // Set when the license server could not free this site's seat: the dialog then offers "Remove anyway".
  const [removeError, setRemoveError] = useState(null)
  const [error, setError] = useState(null)
  const [info, setInfo] = useState('')

  // Every number comes from the same summary the header, menu badge and notification use.
  const expired = summary.expired || 0
  const missing = summary.missing || 0
  // Add-ons waiting for another plugin (for example Elementor Pro) cannot be turned on from Plugins.
  const waiting = addons.filter((a) => a.needs && a.status === 'not_activated').length
  const inactive = Math.max(0, (summary.inactive || 0) - waiting)
  const licensedCount = summary.licensed || 0
  const total = summary.total || addons.length
  const keys = collectKeys(addons)
  const renew = keys.find((k) => k.status === 'license_expired' && k.renewal?.renew_url)?.renewal

  let tone = 'ok'
  let title = __('All your add-ons are licensed', 'wp-sms')
  let text = __('Updates and support are on.', 'wp-sms')
  let needsInput = false

  if (expired > 0) {
    tone = 'expired'
    title = sprintf(_n('%d add-on lost updates and support', '%d add-ons lost updates and support', expired, 'wp-sms'), expired)
    text = __('Renew to bring them back, or enter a new key.', 'wp-sms')
    needsInput = true
  } else if (missing > 0) {
    tone = 'unlock'
    title = licensedCount > 0 ? __('Unlock the rest', 'wp-sms') : __('Unlock your add-ons', 'wp-sms')
    text = licensedCount > 0
      ? sprintf(__('%1$d of %2$d add-ons are licensed. Paste a key to unlock the rest.', 'wp-sms'), licensedCount, total)
      : __('Paste a key from your account. One key can unlock several add-ons.', 'wp-sms')
    needsInput = true
  } else if (inactive > 0) {
    tone = 'off'
    title = sprintf(_n('%d add-on is licensed but turned off', '%d add-ons are licensed but turned off', inactive, 'wp-sms'), inactive)
    text = __('Activate it from the Plugins page. Nothing else is needed.', 'wp-sms')
  }

  const t = TONES[tone]
  const showInput = needsInput || showAdd

  const handleActivate = async () => {
    if (!licenseKey.trim()) return
    setActivating(true)
    setError(null)
    setInfo('')
    try {
      const response = await addonsApi.activateLicense('', licenseKey.trim())
      if (response.data?.already) {
        setInfo(response.message)
        setLicenseKey('')
      } else {
        onChanged(response.message || __('License activated successfully.', 'wp-sms'))
        setLicenseKey('')
        setShowAdd(false)
      }
    } catch (err) {
      setError({
        message: err.message || __('Failed to activate license.', 'wp-sms'),
        status: err.data?.status || '',
        renewUrl: err.data?.renewal?.renew_url || '',
        // The server may send an email address; a web page is better for people, so a mailto link falls back to the support page.
        supportUrl: /^https:/i.test(err.data?.support_url || '') ? err.data.support_url : 'https://wsms.io/support/?utm_source=wsms&utm_medium=plugin&utm_campaign=license-error',
      })
    } finally {
      setActivating(false)
    }
  }

  const handleRemove = async (key, force = false) => {
    setRemovingKey(key.masked)
    try {
      const response = await addonsApi.removeLicense(key.slug, force, key.masked)
      onChanged(response.message || __('License removed successfully.', 'wp-sms'))
      setConfirmKey(null)
      setRemoveError(null)
    } catch (err) {
      if (force) {
        toast({ title: err.message || __('Failed to remove license.', 'wp-sms'), variant: 'destructive' })
        setConfirmKey(null)
        setRemoveError(null)
      } else {
        // Keep the dialog open so the person can choose to remove it here anyway.
        setRemoveError(err.message || __('Could not free this site on the license server.', 'wp-sms'))
      }
    } finally {
      setRemovingKey(null)
    }
  }

  return (
    <section
      id="wsms-license-hero"
      className={cn('wsms-relative wsms-overflow-hidden wsms-rounded-2xl wsms-bg-card wsms-text-foreground', compact ? 'wsms-px-5 wsms-py-4 wsms-text-start' : 'wsms-px-6 wsms-pb-5 wsms-pt-9 wsms-text-center sm:wsms-px-10')}
      style={{ border: `1px solid ${t.border}` }}
      aria-label={__('Licenses', 'wp-sms')}
    >
      <span aria-hidden="true" className="wsms-pointer-events-none wsms-absolute wsms-inset-0" style={{ background: t.tint }} />
      <div className={cn('wsms-relative', compact ? '' : 'wsms-mx-auto wsms-max-w-[680px]')}>
        <span className={cn('wsms-mx-auto wsms-h-16 wsms-w-16 wsms-items-center wsms-justify-center wsms-rounded-full', compact ? 'wsms-hidden' : 'wsms-flex', t.iconClass)}>
          <t.icon className="wsms-h-8 wsms-w-8" strokeWidth={1.75} />
        </span>
        <h2 className={cn('wsms-text-foreground', compact ? 'wsms-text-[16px] wsms-font-semibold' : 'wsms-mt-4 wsms-text-[30px] wsms-font-extrabold wsms-leading-tight wsms-tracking-tight')}>{title}</h2>
        <p className={cn('wsms-text-muted-foreground', compact ? 'wsms-mt-0.5 wsms-text-[13px]' : 'wsms-mx-auto wsms-mt-2 wsms-max-w-[520px] wsms-text-[15px] wsms-leading-relaxed')}>{text}</p>

        {renew?.renew_url && expired > 0 && (
          <a
            href={renew.renew_url}
            target="_blank"
            rel="noopener noreferrer"
            className="wsms-mt-5 wsms-inline-flex wsms-h-12 wsms-items-center wsms-justify-center wsms-gap-2 wsms-rounded-xl wsms-bg-red-600 wsms-px-7 wsms-text-[15px] wsms-font-bold wsms-text-white wsms-no-underline hover:wsms-bg-red-700"
          >
            <span aria-hidden="true">🔄</span>
            {renew.offer?.discount_type === 'percentage' && renew.offer?.discount_value
              ? sprintf(__('Renew with %s%% discount', 'wp-sms'), String(Number(renew.offer.discount_value)))
              : __('Renew license', 'wp-sms')}
            {renew.price?.with_offer ? <span className="wsms-font-medium wsms-opacity-80">· {renew.price.currency} {renew.price.with_offer}</span> : null}
          </a>
        )}

        {showInput ? (
          <div className="wsms-mt-6 wsms-flex wsms-flex-col wsms-gap-3 sm:wsms-flex-row">
            <input
              value={licenseKey}
              onChange={(e) => { setLicenseKey(e.target.value); if (error) setError(null) }}
              onKeyDown={(e) => e.key === 'Enter' && handleActivate()}
              placeholder={__('Paste your license key', 'wp-sms')}
              aria-label={__('License key', 'wp-sms')}
              dir="ltr"
              className={cn('wsms-min-w-0 wsms-flex-1 wsms-rounded-2xl wsms-border wsms-border-border wsms-bg-background wsms-px-5 wsms-text-foreground placeholder:wsms-text-muted-foreground/70 focus:wsms-outline-none', compact ? 'wsms-h-11 wsms-text-[14px]' : 'wsms-h-14 wsms-text-center wsms-text-[16px] sm:wsms-text-start')}
            />
            <button
              type="button"
              onClick={handleActivate}
              disabled={activating || !licenseKey.trim()}
              className={cn('wsms-inline-flex wsms-shrink-0 wsms-items-center wsms-justify-center wsms-gap-2 wsms-rounded-2xl wsms-bg-primary wsms-font-bold wsms-text-primary-foreground hover:wsms-bg-primary/90 disabled:wsms-cursor-not-allowed disabled:wsms-opacity-40', compact ? 'wsms-h-11 wsms-px-6 wsms-text-[14px]' : 'wsms-h-14 wsms-px-8 wsms-text-[16px]')}
            >
              {activating ? <Loader2 className="wsms-h-4 wsms-w-4 wsms-animate-spin" /> : null}
              {__('Activate', 'wp-sms')}
            </button>
          </div>
        ) : (
          <button
            type="button"
            onClick={() => setShowAdd(true)}
            className="wsms-mt-6 wsms-inline-flex wsms-h-12 wsms-items-center wsms-gap-2 wsms-rounded-xl wsms-border wsms-border-border wsms-bg-background wsms-px-6 wsms-text-[15px] wsms-font-semibold wsms-text-foreground hover:wsms-bg-accent"
          >
            <KeyRound className="wsms-h-4 wsms-w-4" />
            {__('Add another key', 'wp-sms')}
          </button>
        )}

        {info && (
          <p role="status" className="wsms-mt-3 wsms-flex wsms-items-center wsms-gap-2.5 wsms-text-start wsms-text-[14px] wsms-font-medium wsms-text-emerald-700 dark:wsms-text-emerald-300"><CheckCircle2 className="wsms-h-4 wsms-w-4 wsms-shrink-0" />{info}</p>
        )}

        {error && (
          <div role="alert" className="wsms-mb-2 wsms-mt-4 wsms-flex wsms-w-full wsms-items-start wsms-gap-2.5 wsms-text-start wsms-text-[14px]">
            <AlertCircle className="wsms-mt-0.5 wsms-h-4 wsms-w-4 wsms-shrink-0 wsms-text-red-600" />
            <div className="wsms-min-w-0 wsms-flex-1">
              <p className="wsms-font-semibold wsms-text-red-700 dark:wsms-text-red-300"><ServerMessage html={error.message} /></p>
              {(!hasLink(error.message) || error.supportUrl || error.renewUrl) && (
                <p className="wsms-mt-1 wsms-leading-relaxed">
                  {!hasLink(error.message) && (
                    <span className="wsms-text-muted-foreground">{__('Check that you copied the whole key from your account, with no spaces.', 'wp-sms')}</span>
                  )}
                  {error.supportUrl && (
                    <> <a href={error.supportUrl} target="_blank" rel="noopener noreferrer" className="wsms-font-semibold wsms-text-primary wsms-underline wsms-underline-offset-2 hover:wsms-no-underline">{__('Contact support', 'wp-sms')}</a></>
                  )}
                  {error.renewUrl && (
                    <> <a href={error.renewUrl} target="_blank" rel="noopener noreferrer" className="wsms-font-semibold wsms-text-primary wsms-underline wsms-underline-offset-2 hover:wsms-no-underline">{__('Renew this key', 'wp-sms')}</a></>
                  )}
                </p>
              )}
            </div>
          </div>
        )}

        {keys.length > 0 && (
          <ul className={cn('wsms-flex wsms-flex-col wsms-gap-3 wsms-text-start', compact ? 'wsms-mt-5' : 'wsms-mx-auto wsms-mt-8 wsms-max-w-[620px]')}>
            {keys.map((key) => {
              const isExpired = key.status === 'license_expired'
              const kind = key.sku === 'all-in-one' ? __('All-in-One', 'wp-sms') : key.sku === 'pro' ? __('Pro', 'wp-sms') : __('Add-on key', 'wp-sms')
              // Ticket look: notches on the sides and at both ends of the dashed tear line.
              // Two masked layers (border colour behind, card colour in front) keep the border
              // running around every notch.
              const rtl = document.documentElement.dir === 'rtl' || document.body.classList.contains('rtl')
              const notch = (r, x, y) => `radial-gradient(circle ${r}px at ${x} ${y}, #0000 97%, #000)`
              const maskOf = (r, o, cut) => [notch(r, `${-o}px`, '50%'), notch(r, `calc(100% + ${o}px)`, '50%'), notch(r, cut, `${-o}px`), notch(r, cut, `calc(100% + ${o}px)`)].join(',')
              const maskStyle = (mask) => ({ WebkitMaskImage: mask, maskImage: mask, WebkitMaskComposite: 'source-in', maskComposite: 'intersect' })
              const outerMask = maskOf(8, 0, rtl ? '112px' : 'calc(100% - 112px)')
              const innerMask = maskOf(9, 1, rtl ? '111px' : 'calc(100% - 111px)')
              return (
                <li key={key.masked} className="wsms-rounded-xl wsms-p-px" style={{ background: t.border, ...maskStyle(outerMask) }}>
                  <div className="wsms-flex wsms-items-stretch wsms-rounded-[11px] wsms-bg-card" style={maskStyle(innerMask)}>
                    <div className="wsms-flex wsms-min-w-0 wsms-flex-1 wsms-items-center wsms-gap-3 wsms-px-5 wsms-py-4">
                      <KeyRound className={cn('wsms-h-5 wsms-w-5 wsms-shrink-0', isExpired ? 'wsms-text-red-600' : 'wsms-text-emerald-600')} />
                      <div className="wsms-min-w-0 wsms-flex-1">
                        <div className="wsms-flex wsms-flex-wrap wsms-items-baseline wsms-gap-x-2">
                          <span className="wsms-text-[15px] wsms-font-semibold wsms-text-foreground">{kind}</span>
                          <span className="wsms-text-[13px] wsms-text-muted-foreground" dir="ltr">…{key.masked.slice(-4)}</span>
                          <span className={cn('wsms-text-[13px] wsms-font-semibold', isExpired ? 'wsms-text-red-600' : 'wsms-text-emerald-600')}>
                            {isExpired ? __('Expired', 'wp-sms') : __('Valid', 'wp-sms')}
                          </span>
                        </div>
                        {(() => {
                          const expiry = describeExpiry(key)
                          const sites = describeSites(key)
                          return (
                            <div className="wsms-flex wsms-flex-wrap wsms-items-center wsms-gap-x-2 wsms-text-[13px] wsms-text-muted-foreground">
                              <span>{sprintf(_n('Unlocks %d add-on', 'Unlocks %d add-ons', key.covers, 'wp-sms'), key.covers)}</span>
                              {sites && <span>· {sites}</span>}
                              {expiry && (
                                <span className={cn(expiry.tone === 'expired' && 'wsms-font-semibold wsms-text-red-600', expiry.tone === 'soon' && 'wsms-font-semibold wsms-text-amber-700')}>· {expiry.text}</span>
                              )}
                              {expiry && expiry.tone !== 'ok' && key.renewal?.renew_url && (
                                <a href={key.renewal.renew_url} target="_blank" rel="noopener noreferrer" className="wsms-font-semibold wsms-text-primary wsms-underline wsms-underline-offset-2 hover:wsms-no-underline">{__('Renew', 'wp-sms')}</a>
                              )}
                            </div>
                          )
                        })()}
                      </div>
                    </div>
                    <div className="wsms-flex wsms-w-[112px] wsms-shrink-0 wsms-items-center wsms-justify-center wsms-border-s-2 wsms-border-dashed wsms-border-border">
                      <button type="button" onClick={() => setConfirmKey(key)} className="wsms-inline-flex wsms-items-center wsms-gap-1.5 wsms-text-[13px] wsms-font-medium wsms-text-muted-foreground hover:wsms-text-red-600">
                        <TrashIcon className="wsms-h-3.5 wsms-w-3.5" />
                        {__('Remove', 'wp-sms')}
                      </button>
                    </div>
                  </div>
                </li>
              )
            })}
          </ul>
        )}

        <div className={cn('wsms-mt-4 wsms-flex wsms-flex-wrap wsms-gap-x-8 wsms-gap-y-2 wsms-pt-2 wsms-text-[14px]', compact ? 'wsms-justify-start' : 'wsms-mx-auto wsms-mt-8 wsms-max-w-[620px] wsms-justify-center')}>
          <a href="https://wsms.io/pricing/?utm_source=wp-sms&utm_medium=add-ons&utm_campaign=license-help" target="_blank" rel="noopener noreferrer" className="wsms-inline-flex wsms-items-center wsms-gap-2 wsms-font-medium wsms-text-primary wsms-no-underline hover:wsms-underline">
            <ShoppingCart className="wsms-h-4 wsms-w-4" />
            {__('No license yet? See plans', 'wp-sms')}
          </a>
          <a href="https://my.wsms.io/" target="_blank" rel="noopener noreferrer" className="wsms-inline-flex wsms-items-center wsms-gap-2 wsms-font-medium wsms-text-primary wsms-no-underline hover:wsms-underline">
            <User className="wsms-h-4 wsms-w-4" />
            {__('Already bought one? Find your key', 'wp-sms')}
          </a>
        </div>
      </div>

      <AlertDialog open={Boolean(confirmKey)} onOpenChange={(open) => { if (!open) { setConfirmKey(null); setRemoveError(null) } }}>
        <AlertDialogContent>
          <AlertDialogHeader>
            <AlertDialogTitle>{__('Remove this license?', 'wp-sms')}</AlertDialogTitle>
            <AlertDialogDescription>
              {removeError
                ? sprintf(__('%s Remove it here anyway? This site keeps using a seat on your account until you free it there.', 'wp-sms'), removeError)
                : confirmKey && (confirmKey.covers > 1
                  ? sprintf(__('It will be removed from all %d add-ons it unlocks, and this site is freed so you can use the key on another site.', 'wp-sms'), confirmKey.covers)
                  : __('It will be removed from this add-on, and this site is freed so you can use the key on another site.', 'wp-sms'))}
            </AlertDialogDescription>
          </AlertDialogHeader>
          <AlertDialogFooter>
            <AlertDialogCancel onClick={() => { setConfirmKey(null); setRemoveError(null) }}>{__('Keep it', 'wp-sms')}</AlertDialogCancel>
            <Button className="!wsms-bg-red-600 !wsms-text-white hover:!wsms-bg-red-700" onClick={() => confirmKey && handleRemove(confirmKey, Boolean(removeError))} disabled={Boolean(removingKey)}>
              {removingKey ? <Loader2 className="wsms-h-4 wsms-w-4 wsms-animate-spin" /> : null}
              {removeError ? __('Remove anyway', 'wp-sms') : __('Remove license', 'wp-sms')}
            </Button>
          </AlertDialogFooter>
        </AlertDialogContent>
      </AlertDialog>
    </section>
  )
}

// Where the "Settings" button of an add-on goes inside this app. Add-ons without a page here show no button.
const SETTINGS_PAGES = {
  'wp-sms-pro': 'gateway',
  'wp-sms-two-way': 'two-way-settings',
  'wp-sms-woocommerce-pro': 'woocommerce-pro',
}

const addonUtm = (url) => (url ? appendUtm(url, { source: 'wsms', medium: 'plugin', campaign: 'add-ons-page' }) : url)

function AddOnCard({ addon, onOpenPage }) {
  const primary = 'wsms-bg-primary wsms-text-primary-foreground hover:wsms-bg-primary/90'
  const outline = 'wsms-border wsms-border-border wsms-bg-background wsms-text-foreground hover:wsms-bg-accent'
  // "Needs License" takes the user to the key field at the top and puts the cursor in it.
  const scrollToLicense = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' })
    document.querySelector('#wpsms-settings-root .wsms-content')?.scrollTo({ top: 0, behavior: 'smooth' })
    const hero = document.getElementById('wsms-license-hero')
    const input = hero?.querySelector('input')
    hero?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    window.setTimeout(() => input?.focus({ preventScroll: true }), 350)
  }

  let action = null
  let gear = null
  // License entry and renewal live in the band above, so cards never ask for a key.
  if (addon.status === 'not_activated' && addon.needs) {
    action = { label: sprintf(__('Needs %s', 'wp-sms'), addon.needs), tone: 'wsms-bg-muted wsms-text-muted-foreground wsms-cursor-default' }
  } else if (addon.status === 'not_activated') {
    action = { label: __('Activate plugin', 'wp-sms'), tone: primary, href: 'plugins.php' }
  } else if (addon.status === 'activated' && SETTINGS_PAGES[addon.slug]) {
    gear = () => onOpenPage(SETTINGS_PAGES[addon.slug])
  }

  const actionClass = (tone) => cn('wsms-inline-flex wsms-h-8 wsms-items-center wsms-justify-center wsms-rounded-md wsms-px-3 wsms-text-[13px] wsms-font-semibold wsms-no-underline', tone)

  return (
    <div className="wsms-flex wsms-h-full wsms-flex-col wsms-rounded-xl wsms-border wsms-border-border wsms-bg-card">
      <div className="wsms-flex wsms-flex-1 wsms-flex-col wsms-p-4">
        <div className="wsms-flex wsms-items-center wsms-gap-3">
          {addon.icon ? (
            <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-shrink-0 wsms-items-center wsms-justify-center wsms-overflow-hidden wsms-rounded-lg wsms-bg-orange-500/10">
              <img src={addon.icon} alt="" className="wsms-h-7 wsms-w-7 wsms-object-contain" />
            </div>
          ) : (
            <div className="wsms-flex wsms-h-10 wsms-w-10 wsms-shrink-0 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-bg-orange-500/10">
              <Blocks className="wsms-h-4 wsms-w-4 wsms-text-primary" />
            </div>
          )}
          <div className="wsms-min-w-0 wsms-flex-1">
            <div className="wsms-flex wsms-flex-wrap wsms-items-baseline wsms-gap-x-2">
              {!addon.is_installed && addon.product_url ? (
                <a href={addonUtm(addon.product_url)} target="_blank" rel="noopener noreferrer" className="wsms-inline-flex wsms-items-center wsms-gap-1.5 wsms-text-[15px] wsms-font-semibold wsms-text-foreground wsms-no-underline hover:wsms-text-primary" title={addon.name}>
                  {addon.name}
                  <ExternalLink className="wsms-h-3.5 wsms-w-3.5 wsms-text-muted-foreground" />
                </a>
              ) : (
                <span className="wsms-text-[15px] wsms-font-semibold wsms-text-foreground" title={addon.name}>{addon.name}</span>
              )}
              {addon.is_installed && addon.version && <span className="wsms-shrink-0 wsms-text-[12px] wsms-text-muted-foreground" dir="ltr">v{addon.version}</span>}
            </div>
            {addon.is_installed && (
              <div className="wsms-mt-1 wsms-flex wsms-items-center wsms-gap-2">
                {['not_licensed', 'license_expired'].includes(addon.status) ? (
                  <button type="button" onClick={scrollToLicense} title={__('Enter your license key', 'wp-sms')} className="wsms-shrink-0 wsms-cursor-pointer wsms-rounded-full focus-visible:wsms-ring-2 focus-visible:wsms-ring-ring [&_*]:wsms-whitespace-nowrap">
                    <StatusBadge variant={STATUS_TO_VARIANT[addon.status] || 'default'} size="sm">{addon.status_label}</StatusBadge>
                  </button>
                ) : (
                  <span className="wsms-shrink-0 [&_*]:wsms-whitespace-nowrap"><StatusBadge variant={STATUS_TO_VARIANT[addon.status] || 'default'} size="sm">{addon.status_label}</StatusBadge></span>
                )}
            </div>
            )}
          </div>
          {gear && (
            <button
              type="button"
              onClick={gear}
              aria-label={__('Settings', 'wp-sms')}
              title={__('Settings', 'wp-sms')}
              className="wsms-inline-flex wsms-h-9 wsms-w-9 wsms-shrink-0 wsms-items-center wsms-justify-center wsms-rounded-lg wsms-text-muted-foreground hover:wsms-bg-accent hover:wsms-text-foreground"
            >
              <SettingsIcon className="wsms-h-4 wsms-w-4" />
            </button>
          )}
        </div>

        <p className="wsms-mt-3 wsms-line-clamp-5 wsms-text-[14px] wsms-leading-relaxed wsms-text-muted-foreground">{addon.short_description}</p>

        <div className={cn("wsms-mt-auto wsms-flex wsms-items-center wsms-gap-3", (action || addon.is_update_available) ? "wsms-h-8 wsms-pt-4 wsms-box-content" : "wsms-hidden")}>
          {action && (action.href ? (
            <a href={action.href} {...(action.href.startsWith('http') ? { target: '_blank', rel: 'noopener noreferrer' } : {})} className={actionClass(action.tone)}>{action.label}</a>
          ) : action.onClick ? (
            <button type="button" onClick={action.onClick} className={actionClass(action.tone)}>{action.label}</button>
          ) : (
            <span className={actionClass(action.tone)}>{action.label}</span>
          ))}
          {addon.is_update_available && (
            <a href="plugins.php?plugin_status=upgrade" className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[12px] wsms-font-semibold wsms-text-amber-700 wsms-no-underline hover:wsms-underline">
              <ArrowUpCircle className="wsms-h-3.5 wsms-w-3.5" />
              {__('Update available', 'wp-sms')}
            </a>
          )}
        </div>
      </div>

      {addon.is_installed && (
      <div className="wsms-flex wsms-flex-wrap wsms-items-center wsms-gap-4 wsms-border-t wsms-border-border wsms-px-4 wsms-py-3">
        {addon.product_url && (
          <a href={addonUtm(addon.product_url)} target="_blank" rel="noopener noreferrer" className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[12px] wsms-text-muted-foreground wsms-no-underline hover:wsms-text-foreground">
            <ExternalLink className="wsms-h-3 wsms-w-3" />
            {addon.is_installed ? __('Details', 'wp-sms') : __('Get Add-On', 'wp-sms')}
          </a>
        )}
        {addon.documentation_url && (
          <a href={addonUtm(addon.documentation_url)} target="_blank" rel="noopener noreferrer" className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[12px] wsms-text-muted-foreground wsms-no-underline hover:wsms-text-foreground">
            <BookOpen className="wsms-h-3 wsms-w-3" />
            {__('Docs', 'wp-sms')}
          </a>
        )}
        {addon.changelog_url && (
          <a href={addonUtm(addon.changelog_url)} target="_blank" rel="noopener noreferrer" className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[12px] wsms-text-muted-foreground wsms-no-underline hover:wsms-text-foreground">
            <FileText className="wsms-h-3 wsms-w-3" />
            {__('Changelog', 'wp-sms')}
          </a>
        )}
      </div>
      )}
    </div>
  )
}

function AddOnsLoadingSkeleton() {
  return (
    <div className="wsms-space-y-6" role="status" aria-label={__('Loading', 'wp-sms')}>
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
      <span className="wsms-sr-only">{__('Loading...', 'wp-sms')}</span>
    </div>
  )
}

export default function AddOns() {
  const { setCurrentPage } = useSettings()
  const [addons, setAddons] = useState([])
  const [summary, setSummary] = useState({})
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState('')
  const { toast } = useToast()

  const fetchAddons = useCallback(async () => {
    setLoading(true)
    setError('')
    try {
      const data = await addonsApi.getAll()
      setAddons(data.addons || [])
      setSummary(data.summary || {})
    } catch (err) {
      setError(err.message || __('Failed to load add-ons.', 'wp-sms'))
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
          title={__('Unable to load add-ons', 'wp-sms')}
          description={error}
          action={fetchAddons}
          actionLabel={__('Retry', 'wp-sms')}
        />
      </Card>
    )
  }

  if (addons.length === 0) {
    return (
      <Card className="wsms-border-dashed">
        <EmptyStateAction
          icon={Blocks}
          title={__('No add-ons available', 'wp-sms')}
          description={__('Add-ons extend WSMS with extra features. Check back later for available add-ons.', 'wp-sms')}
        />
      </Card>
    )
  }

  // WSMS on its own: no add-on installed and no key stored (add &aio-preview=1 to preview it elsewhere).
  const freeOnly = new URLSearchParams(window.location.search).has('aio-preview') || (addons.every((a) => !a.is_installed) && collectKeys(addons).length === 0)

  return (
    <div className="wsms-space-y-6 wsms-stagger-children">
      {freeOnly ? (
        <>
          {/* A site with no add-on and no key: show the value first, then a slim box for people who already have a key. */}
          <AllInOneCompare campaign="add-ons-compare" />
          <p className="wsms-mb-2 wsms-mt-2 wsms-border-b wsms-border-border wsms-px-1 wsms-pb-6 wsms-pt-2 wsms-text-[14px] wsms-text-muted-foreground">
            🔑 {__('Already bought an add-on? Download it from your account and install it like any plugin. A license box appears here once it is installed.', 'wp-sms')}{' '}
            <a href={addonUtm('https://my.wsms.io/')} target="_blank" rel="noopener noreferrer" className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-font-semibold wsms-text-primary wsms-no-underline hover:wsms-underline">
              {__('Open my account', 'wp-sms')}<ExternalLink className="wsms-h-3.5 wsms-w-3.5" />
            </a>
          </p>
        </>
      ) : (
        <LicenseHero addons={addons} summary={summary} onChanged={handleLicenseChanged} />
      )}

      <div>
        <h3 className="wsms-flex wsms-items-center wsms-gap-2 wsms-text-[17px] wsms-font-semibold wsms-text-foreground">
          <Blocks className="wsms-h-5 wsms-w-5 wsms-text-primary" />
          {freeOnly ? __('Add-ons available', 'wp-sms') : __('Your add-ons', 'wp-sms')}
        </h3>
        <p className="wsms-mt-1 wsms-text-[14px] wsms-text-muted-foreground">
          {freeOnly
            ? __('Want just one feature? Each add-on can be bought on its own, or get them all with All-in-One.', 'wp-sms')
            : __('Extend WSMS with extra gateways, integrations and tools', 'wp-sms')}
        </p>
        <div className="wsms-mt-4 wsms-grid wsms-grid-cols-1 wsms-gap-3 lg:wsms-grid-cols-2 xl:wsms-grid-cols-3">
          {addons.map((addon) => (
            <AddOnCard key={addon.slug} addon={addon} onOpenPage={setCurrentPage} />
          ))}
        </div>
      </div>
    </div>
  )
}
