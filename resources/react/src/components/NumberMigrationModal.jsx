import React, { useState, useCallback } from 'react'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogBody,
  DialogFooter,
} from '@/components/ui/dialog'
import { Button } from '@/components/ui/button'
import {
  Loader2,
  Search,
  Eye,
  Play,
  Undo2,
  CheckCircle2,
  AlertTriangle,
  ArrowRight,
} from 'lucide-react'
import { getWpSettings, __ } from '@/lib/utils'
import { adminNoticesApi } from '@/api/adminNoticesApi'

/**
 * AJAX helper for the number migration controller
 */
async function migrationAjax(subAction, extraParams = {}) {
  const settings = getWpSettings()
  const ajaxUrl = settings.ajaxUrl || `${settings.adminUrl || '/wp-admin/'}admin-ajax.php`
  const ajaxNonce = settings.ajaxNonces?.numberMigration || ''

  const formData = new FormData()
  formData.append('action', 'wp_sms_number_migration')
  formData.append('sub_action', subAction)
  formData.append('_nonce', ajaxNonce)

  Object.entries(extraParams).forEach(([key, value]) => {
    if (value !== undefined && value !== null) {
      formData.append(key, String(value))
    }
  })

  const response = await fetch(ajaxUrl, { method: 'POST', body: formData })

  const contentType = response.headers.get('content-type')
  if (!contentType || !contentType.includes('application/json')) {
    throw new Error(__('Server returned an invalid response.'))
  }

  const data = await response.json()
  if (!data.success) {
    const err = new Error(data.data?.message || data.data || __('An error occurred.'))
    err.code = data.data?.code || 'unknown'
    throw err
  }

  return data.data
}

const STEP_SCAN = 'scan'
const STEP_PREVIEW = 'preview'
const STEP_EXECUTE = 'execute'
const STEP_DONE = 'done'

/**
 * Stat card used in the scan results grid
 */
function StatCard({ value, label, variant = 'default' }) {
  const variants = {
    default: 'wsms-bg-muted/50',
    warning: 'wsms-bg-orange-50',
    success: 'wsms-bg-green-50',
  }
  const textVariants = {
    default: 'wsms-text-muted-foreground',
    warning: 'wsms-text-orange-600',
    success: 'wsms-text-green-600',
  }
  return (
    <div className={`wsms-text-center wsms-p-2 wsms-rounded ${variants[variant]}`}>
      <div className={`wsms-text-[18px] wsms-font-semibold ${textVariants[variant]}`}>{value}</div>
      <div className={`wsms-text-[12px] ${textVariants[variant]}`}>{label}</div>
    </div>
  )
}

export default function NumberMigrationModal({ open, onOpenChange }) {
  const { countriesByDialCode = {} } = getWpSettings()
  const [step, setStep] = useState(STEP_SCAN)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const [scanData, setScanData] = useState(null)
  const [previewData, setPreviewData] = useState(null)
  const [previewPage, setPreviewPage] = useState(1)
  const [executeData, setExecuteData] = useState(null)
  const [needsCC, setNeedsCC] = useState(false)
  const [overrideCC, setOverrideCC] = useState('')

  const handleOpenChange = useCallback((isOpen) => {
    if (!isOpen) {
      setStep(STEP_SCAN)
      setError(null)
      setScanData(null)
      setPreviewData(null)
      setExecuteData(null)
      setPreviewPage(1)
      setNeedsCC(false)
      setOverrideCC('')
    }
    onOpenChange(isOpen)
  }, [onOpenChange])

  // Build extra params for AJAX calls — pass country_code if user selected one in the modal
  const getExtraParams = useCallback(() => {
    return overrideCC ? { country_code: overrideCC } : {}
  }, [overrideCC])

  const handleScan = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await migrationAjax('scan', getExtraParams())
      setScanData(data)
      setNeedsCC(false)
    } catch (err) {
      if (err.code === 'missing_country_code') {
        setNeedsCC(true)
      } else {
        setError(err.message)
      }
    } finally {
      setLoading(false)
    }
  }, [getExtraParams])

  const handlePreview = useCallback(async (page = 1) => {
    setLoading(true)
    setError(null)
    try {
      const data = await migrationAjax('preview', { page, per_page: 20, ...getExtraParams() })
      setPreviewData(data)
      setPreviewPage(page)
      setStep(STEP_PREVIEW)
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [getExtraParams])

  const handleExecute = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await migrationAjax('execute', getExtraParams())
      setExecuteData(data)
      setStep(STEP_DONE)
      // Dismiss the admin notice banner after successful migration
      try { await adminNoticesApi.dismiss('number_migration', 'handler') } catch (_) { /* ignore */ }
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [getExtraParams])

  const handleRevert = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await migrationAjax('revert')
      setExecuteData(null)
      setScanData(null)
      setStep(STEP_SCAN)
      setError(null)
      alert(__('Successfully reverted') + `: ${data.total_reverted} ` + __('numbers restored.'))
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [])

  return (
    <Dialog open={open} onOpenChange={handleOpenChange}>
      <DialogContent size="xl">
        <DialogHeader>
          <DialogTitle>{__('Phone Number Migration Wizard')}</DialogTitle>
          <DialogDescription>
            {__('Convert all stored phone numbers to international format (E.164) with country code prefix.')}
          </DialogDescription>
        </DialogHeader>

        <DialogBody>
          {error && (
            <div className="wsms-flex wsms-items-start wsms-gap-2 wsms-p-3 wsms-rounded-lg wsms-bg-destructive/10 wsms-text-destructive wsms-text-[13px] wsms-mb-4">
              <AlertTriangle className="wsms-h-4 wsms-w-4 wsms-mt-0.5 wsms-shrink-0" />
              <span>{error}</span>
            </div>
          )}

          {/* ========== STEP: SCAN ========== */}
          {step === STEP_SCAN && (
            <div className="wsms-space-y-4">
              {!scanData || needsCC ? (
                <div className="wsms-text-center wsms-py-8">
                  <Search className="wsms-h-10 wsms-w-10 wsms-text-muted-foreground wsms-mx-auto wsms-mb-3" />
                  <p className="wsms-text-[14px] wsms-text-foreground wsms-font-medium wsms-mb-1">
                    {__('Scan your database')}
                  </p>
                  <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-mb-4">
                    {__('Check all phone numbers across subscribers, users, OTP records, campaigns, and scheduled messages.')}
                  </p>

                  {/* Country code selector — shown when CC is not configured */}
                  {needsCC && (
                    <div className="wsms-mb-4 wsms-mx-auto wsms-max-w-xs wsms-text-start">
                      <label className="wsms-block wsms-text-[13px] wsms-font-medium wsms-mb-1">
                        {__('Select a country code for the migration')}
                      </label>
                      <select
                        className="wsms-w-full wsms-border wsms-rounded-md wsms-px-3 wsms-py-2 wsms-text-[13px]"
                        value={overrideCC}
                        onChange={(e) => setOverrideCC(e.target.value)}
                      >
                        <option value="">{__('Select country code...')}</option>
                        {Object.entries(countriesByDialCode).map(([dialCode, label]) => (
                          <option key={dialCode} value={dialCode}>{label}</option>
                        ))}
                      </select>
                    </div>
                  )}

                  <Button onClick={handleScan} disabled={loading || (needsCC && !overrideCC)}>
                    {loading ? (
                      <>
                        <Loader2 className="wsms-h-4 wsms-w-4 wsms-me-1.5 wsms-animate-spin" />
                        {__('Scanning...')}
                      </>
                    ) : (
                      <>
                        <Search className="wsms-h-4 wsms-w-4 wsms-me-1.5" />
                        {needsCC ? __('Set & Start Scan') : __('Start Scan')}
                      </>
                    )}
                  </Button>
                </div>
              ) : (
                <div className="wsms-space-y-4">
                  <div className="wsms-p-3 wsms-rounded-lg wsms-bg-muted/50 wsms-text-[13px]">
                    <span className="wsms-font-medium">{__('Country Code')}:</span>{' '}
                    <code className="wsms-bg-background wsms-px-1.5 wsms-py-0.5 wsms-rounded wsms-font-mono">{scanData.country_code}</code>
                  </div>

                  {/* Dynamic source cards */}
                  {scanData.sources && Object.entries(scanData.sources).map(([key, source]) => (
                    source.total > 0 && (
                      <div key={key} className="wsms-border wsms-rounded-lg wsms-p-4">
                        <h4 className="wsms-text-[14px] wsms-font-medium wsms-mb-2">{source.label}</h4>
                        <div className="wsms-grid wsms-grid-cols-3 wsms-gap-3 wsms-text-[13px]">
                          <StatCard value={source.total} label={__('Total')} />
                          <StatCard value={source.need_fix} label={__('Need Fix')} variant="warning" />
                          <StatCard value={source.already_intl} label={__('Already OK')} variant="success" />
                        </div>
                      </div>
                    )
                  ))}

                  {scanData.total_need_fix === 0 ? (
                    <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-p-3 wsms-rounded-lg wsms-bg-green-50 wsms-text-green-700 wsms-text-[13px]">
                      <CheckCircle2 className="wsms-h-4 wsms-w-4" />
                      {__('All numbers are already in international format. No migration needed.')}
                    </div>
                  ) : (
                    <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-p-3 wsms-rounded-lg wsms-bg-orange-50 wsms-text-orange-700 wsms-text-[13px]">
                      <AlertTriangle className="wsms-h-4 wsms-w-4" />
                      {scanData.total_need_fix} {__('numbers need to be migrated.')}
                    </div>
                  )}

                  {scanData.backup_exists && (
                    <div className="wsms-flex wsms-items-center wsms-justify-between wsms-p-3 wsms-rounded-lg wsms-bg-blue-50 wsms-text-blue-700 wsms-text-[13px]">
                      <span>{__('A previous migration backup exists.')}</span>
                      <Button variant="outline" size="sm" onClick={handleRevert} disabled={loading}>
                        <Undo2 className="wsms-h-3.5 wsms-w-3.5 wsms-me-1" />
                        {__('Revert')}
                      </Button>
                    </div>
                  )}
                </div>
              )}
            </div>
          )}

          {/* ========== STEP: PREVIEW ========== */}
          {step === STEP_PREVIEW && previewData && (
            <div className="wsms-space-y-4">
              <p className="wsms-text-[13px] wsms-text-muted-foreground">
                {__('Review the changes below. Numbers will be converted using country code')}{' '}
                <code className="wsms-bg-muted wsms-px-1 wsms-rounded wsms-font-mono">{previewData.country_code}</code>
              </p>

              <div className="wsms-border wsms-rounded-lg wsms-overflow-hidden">
                <table className="wsms-w-full wsms-text-[13px]">
                  <thead>
                    <tr className="wsms-bg-muted/50">
                      <th className="wsms-text-start wsms-px-3 wsms-py-2 wsms-font-medium">{__('Source')}</th>
                      <th className="wsms-text-start wsms-px-3 wsms-py-2 wsms-font-medium">{__('Name')}</th>
                      <th className="wsms-text-start wsms-px-3 wsms-py-2 wsms-font-medium">{__('Current')}</th>
                      <th className="wsms-text-center wsms-px-1 wsms-py-2"></th>
                      <th className="wsms-text-start wsms-px-3 wsms-py-2 wsms-font-medium">{__('After Migration')}</th>
                    </tr>
                  </thead>
                  <tbody>
                    {previewData.preview.map((item, index) => (
                      <tr key={`${item.source}-${item.id}-${index}`} className="wsms-border-t">
                        <td className="wsms-px-3 wsms-py-2">
                          <span className="wsms-inline-flex wsms-px-1.5 wsms-py-0.5 wsms-rounded wsms-text-[11px] wsms-font-medium wsms-bg-blue-100 wsms-text-blue-700">
                            {item.label}
                          </span>
                        </td>
                        <td className="wsms-px-3 wsms-py-2 wsms-text-muted-foreground">{item.name || '—'}</td>
                        <td className="wsms-px-3 wsms-py-2">
                          <code className="wsms-font-mono wsms-text-orange-600 wsms-text-[12px] wsms-break-all">{item.original}</code>
                        </td>
                        <td className="wsms-px-1 wsms-py-2 wsms-text-center">
                          <ArrowRight className="wsms-h-3.5 wsms-w-3.5 wsms-text-muted-foreground wsms-inline" />
                        </td>
                        <td className="wsms-px-3 wsms-py-2">
                          <code className="wsms-font-mono wsms-text-green-600 wsms-text-[12px] wsms-break-all">{item.migrated}</code>
                        </td>
                      </tr>
                    ))}
                    {previewData.preview.length === 0 && (
                      <tr>
                        <td colSpan={5} className="wsms-px-3 wsms-py-6 wsms-text-center wsms-text-muted-foreground">
                          {__('No numbers to preview on this page.')}
                        </td>
                      </tr>
                    )}
                  </tbody>
                </table>
              </div>

              <div className="wsms-flex wsms-items-center wsms-justify-between wsms-text-[13px]">
                <span className="wsms-text-muted-foreground">
                  {__('Page')} {previewPage}
                </span>
                <div className="wsms-flex wsms-gap-2">
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={previewPage <= 1 || loading}
                    onClick={() => handlePreview(previewPage - 1)}
                  >
                    {__('Previous')}
                  </Button>
                  <Button
                    variant="outline"
                    size="sm"
                    disabled={previewData.preview.length < 20 || loading}
                    onClick={() => handlePreview(previewPage + 1)}
                  >
                    {__('Next')}
                  </Button>
                </div>
              </div>
            </div>
          )}

          {/* ========== STEP: DONE ========== */}
          {step === STEP_DONE && executeData && (
            <div className="wsms-space-y-4">
              <div className="wsms-text-center wsms-py-4">
                <CheckCircle2 className="wsms-h-12 wsms-w-12 wsms-text-green-500 wsms-mx-auto wsms-mb-3" />
                <h3 className="wsms-text-[16px] wsms-font-semibold wsms-mb-1">{__('Migration Complete')}</h3>
                <p className="wsms-text-[13px] wsms-text-muted-foreground">
                  {__('Successfully migrated')} {executeData.total_migrated} {__('phone numbers.')}
                </p>
              </div>

              {/* Per-source counts */}
              {executeData.counts && (
                <div className="wsms-grid wsms-grid-cols-2 sm:wsms-grid-cols-3 wsms-gap-2 wsms-text-[13px]">
                  {Object.entries(executeData.counts).map(([key, count]) => (
                    count > 0 && (
                      <StatCard key={key} value={count} label={key.replace(/_/g, ' ')} variant="success" />
                    )
                  ))}
                </div>
              )}

              {executeData.errors && executeData.errors.length > 0 && (
                <div className="wsms-p-3 wsms-rounded-lg wsms-bg-orange-50 wsms-text-orange-700 wsms-text-[13px]">
                  <p className="wsms-font-medium wsms-mb-1">{executeData.errors.length} {__('errors occurred')}:</p>
                  <ul className="wsms-list-disc wsms-ps-4 wsms-space-y-0.5">
                    {executeData.errors.slice(0, 5).map((err, i) => (
                      <li key={i}>{err}</li>
                    ))}
                    {executeData.errors.length > 5 && (
                      <li>{__('and')} {executeData.errors.length - 5} {__('more...')}</li>
                    )}
                  </ul>
                </div>
              )}

              <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-p-3 wsms-rounded-lg wsms-bg-blue-50 wsms-text-blue-700 wsms-text-[13px]">
                <span>{__('A backup has been created. You can revert all changes if needed.')}</span>
              </div>
            </div>
          )}
        </DialogBody>

        <DialogFooter>
          {/* Scan step footer */}
          {step === STEP_SCAN && scanData && scanData.total_need_fix > 0 && (
            <>
              <Button variant="outline" onClick={() => handleOpenChange(false)}>
                {__('Cancel')}
              </Button>
              <Button onClick={() => handlePreview(1)} disabled={loading}>
                {loading ? (
                  <>
                    <Loader2 className="wsms-h-4 wsms-w-4 wsms-me-1.5 wsms-animate-spin" />
                    {__('Loading...')}
                  </>
                ) : (
                  <>
                    <Eye className="wsms-h-4 wsms-w-4 wsms-me-1.5" />
                    {__('Preview Changes')}
                  </>
                )}
              </Button>
            </>
          )}

          {/* Preview step footer */}
          {step === STEP_PREVIEW && (
            <>
              <Button variant="outline" onClick={() => setStep(STEP_SCAN)}>
                {__('Back')}
              </Button>
              <Button onClick={handleExecute} disabled={loading}>
                {loading ? (
                  <>
                    <Loader2 className="wsms-h-4 wsms-w-4 wsms-me-1.5 wsms-animate-spin" />
                    {__('Migrating...')}
                  </>
                ) : (
                  <>
                    <Play className="wsms-h-4 wsms-w-4 wsms-me-1.5" />
                    {__('Execute Migration')}
                  </>
                )}
              </Button>
            </>
          )}

          {/* Done step footer */}
          {step === STEP_DONE && (
            <>
              <Button variant="outline" onClick={handleRevert} disabled={loading}>
                {loading ? (
                  <Loader2 className="wsms-h-4 wsms-w-4 wsms-me-1.5 wsms-animate-spin" />
                ) : (
                  <Undo2 className="wsms-h-4 wsms-w-4 wsms-me-1.5" />
                )}
                {__('Revert All Changes')}
              </Button>
              <Button onClick={() => handleOpenChange(false)}>
                {__('Done')}
              </Button>
            </>
          )}
        </DialogFooter>
      </DialogContent>
    </Dialog>
  )
}
