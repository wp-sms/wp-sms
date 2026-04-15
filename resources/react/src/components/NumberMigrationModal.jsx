import { __, sprintf } from '@wordpress/i18n'
import React, { useCallback, useEffect, useMemo, useRef, useState } from 'react'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogBody,
} from '@/components/ui/dialog'
import { Alert, AlertDescription } from '@/components/ui/alert'
import { Button } from '@/components/ui/button'
import WizardStepper from '@/components/wizard/WizardStepper'
import DeleteConfirmDialog from '@/components/shared/DeleteConfirmDialog'
import { Phone, Flag, ClipboardCheck, Eye, Play, CheckCircle2 } from 'lucide-react'
import { getWpSettings } from '@/lib/utils'
import { adminNoticesApi } from '@/api/adminNoticesApi'
import { useToast } from '@/components/ui/toaster'

import IntroStep from './migration/IntroStep'
import CountryStep from './migration/CountryStep'
import ReviewStep from './migration/ReviewStep'
import PreviewStep from './migration/PreviewStep'
import ExecuteStep from './migration/ExecuteStep'
import DoneStep from './migration/DoneStep'

/**
 * Number Migration Wizard. 5 steps with WizardStepper at the top; the Country step
 * is filtered out when the backend already has a country code configured.
 */

const STEP = {
  INTRO: 'intro',
  COUNTRY: 'country',
  REVIEW: 'review',
  PREVIEW: 'preview',
  EXECUTE: 'execute',
  DONE: 'done',
}

const ALL_STEPS_META = [
  { id: STEP.INTRO, label: __('Intro', 'wp-sms'), icon: Phone },
  { id: STEP.COUNTRY, label: __('Country', 'wp-sms'), icon: Flag, conditional: true },
  { id: STEP.REVIEW, label: __('Review', 'wp-sms'), icon: ClipboardCheck },
  { id: STEP.PREVIEW, label: __('Preview', 'wp-sms'), icon: Eye },
  { id: STEP.EXECUTE, label: __('Apply', 'wp-sms'), icon: Play },
  { id: STEP.DONE, label: __('Done', 'wp-sms'), icon: CheckCircle2 },
]

/**
 * Returns the `data` payload from wp_send_json_success, or throws an Error with
 * a `.code` property mirroring the wp_send_json_error code.
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

  // Special-case 403 (nonce expired) — we want a distinct error code so the UI
  // can show a reload-page affordance instead of a generic error.
  if (response.status === 403) {
    const err = new Error(__('Your session has expired. Please reload the page to continue.', 'wp-sms'))
    err.code = 'nonce_expired'
    throw err
  }

  const contentType = response.headers.get('content-type') || ''
  if (!contentType.includes('application/json')) {
    const err = new Error(__('Server returned an invalid response.', 'wp-sms'))
    err.code = 'invalid_response'
    err.status = response.status
    throw err
  }

  const data = await response.json()
  if (!data.success) {
    const err = new Error(data.data?.message || data.data || __('An error occurred.', 'wp-sms'))
    err.code = data.data?.code || 'unknown'
    err.status = response.status
    err.payload = data.data
    throw err
  }

  return data.data
}

export default function NumberMigrationModal({ open, onOpenChange }) {
  const { toast } = useToast()

  const [step, setStep] = useState(STEP.INTRO)
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState(null)
  const [scanData, setScanData] = useState(null)
  const [previewData, setPreviewData] = useState(null)
  const [previewPage, setPreviewPage] = useState(1)
  const [executeData, setExecuteData] = useState(null)
  const [needsCC, setNeedsCC] = useState(false)
  const [ccMode, setCcMode] = useState('default')
  const [overrideCC, setOverrideCC] = useState('')
  const [polling, setPolling] = useState(false)
  const [revertOpen, setRevertOpen] = useState(false)
  const [revertBusy, setRevertBusy] = useState(false)

  // Rough per-row cost driving the simulated progress bar — see ProgressBar.jsx.
  const estimatedMs = useMemo(() => {
    const needFix = scanData?.total_need_fix || 0
    return Math.max(2000, needFix * 6)
  }, [scanData])

  const headlineRef = useRef(null)

  const resetState = useCallback(() => {
    setStep(STEP.INTRO)
    setLoading(false)
    setError(null)
    setScanData(null)
    setPreviewData(null)
    setPreviewPage(1)
    setExecuteData(null)
    setNeedsCC(false)
    setCcMode('default')
    setOverrideCC('')
    setPolling(false)
    setRevertOpen(false)
    setRevertBusy(false)
  }, [])

  // Dismiss is locked during execute: walking away from an in-flight write would
  // leave no recovery path.
  const handleOpenChange = useCallback(
    (isOpen) => {
      if (!isOpen && step === STEP.EXECUTE) {
        return
      }
      if (!isOpen) {
        resetState()
      }
      onOpenChange(isOpen)
    },
    [onOpenChange, resetState, step]
  )

  // overrideCC is non-empty only when the user picked a CC in the wizard's Country
  // step (i.e. when the backend didn't have one configured).
  const getExtraParams = useCallback(
    () => (overrideCC ? { country_code: overrideCC } : {}),
    [overrideCC]
  )

  const handleScan = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      const data = await migrationAjax('scan', getExtraParams())
      setScanData(data)
      setNeedsCC(false)
      setStep(STEP.REVIEW)
    } catch (err) {
      if (err.code === 'missing_country_code') {
        setNeedsCC(true)
        setCcMode(err.payload?.mode || 'default')
        setStep(STEP.COUNTRY)
      } else {
        setError(err.message)
      }
    } finally {
      setLoading(false)
    }
  }, [getExtraParams])

  const handlePreviewLoad = useCallback(
    async (page = 1, advance = true) => {
      setLoading(true)
      setError(null)
      try {
        const data = await migrationAjax('preview', {
          page,
          per_page: 20,
          ...getExtraParams(),
        })
        setPreviewData(data)
        setPreviewPage(page)
        if (advance) {
          setStep(STEP.PREVIEW)
        }
      } catch (err) {
        setError(err.message)
      } finally {
        setLoading(false)
      }
    },
    [getExtraParams]
  )

  const handleExecute = useCallback(async () => {
    setLoading(true)
    setError(null)
    setStep(STEP.EXECUTE)
    try {
      const data = await migrationAjax('execute', getExtraParams())
      setExecuteData(data)
      setStep(STEP.DONE)
      try {
        await adminNoticesApi.dismiss('number_migration', 'handler')
      } catch (_) {
        /* non-fatal — the notice will re-show on next page load if anything */
      }
      window.dispatchEvent(new CustomEvent('wpsms:number-migration-done'))
    } catch (err) {
      if (err.code === 'migration_in_progress') {
        setPolling(true)
      } else if (err.code === 'nonce_expired') {
        setError(err.message)
        setStep(STEP.REVIEW)
      } else {
        // Don't know whether the write landed — surface a recovery state with
        // explicit Refresh / Re-scan affordances.
        setError(
          __(
            "We couldn't confirm the update finished. The change may or may not have been applied."
          , 'wp-sms')
        )
      }
    } finally {
      setLoading(false)
    }
  }, [getExtraParams])

  // Auto-poll status when another admin already grabbed the lock. Track the
  // latest setTimeout id explicitly so cleanup cancels the next scheduled tick.
  useEffect(() => {
    if (!polling) return
    const startedAt = Date.now()
    const POLL_INTERVAL = 3_000
    const POLL_TIMEOUT = 30_000
    let cancelled = false
    let timerId = null

    const tick = async () => {
      if (cancelled) return
      try {
        const status = await migrationAjax('status')
        if (cancelled) return
        if (status.status === 'completed' && !status.running) {
          const counts = status.counts || {}
          setExecuteData({
            total_migrated: status.total_migrated || 0,
            sources_touched: Object.values(counts).filter((c) => c > 0).length,
            counts,
            errors: status.errors || [],
            backup_timestamp: status.backup_timestamp || '',
          })
          setPolling(false)
          setStep(STEP.DONE)
          return
        }
      } catch (_) {
        /* swallow transient errors — we'll retry on the next tick */
      }
      if (Date.now() - startedAt > POLL_TIMEOUT) {
        setPolling(false)
        setError(
          __(
            "We couldn't confirm the update finished. Please refresh this page and run the check again."
          , 'wp-sms')
        )
        setStep(STEP.REVIEW)
        return
      }
      timerId = setTimeout(tick, POLL_INTERVAL)
    }

    timerId = setTimeout(tick, POLL_INTERVAL)
    return () => {
      cancelled = true
      if (timerId) clearTimeout(timerId)
    }
  }, [polling])

  const handleForceRefresh = useCallback(async () => {
    if (polling) return
    try {
      const status = await migrationAjax('status')
      if (status.status === 'completed' && !status.running) {
        const counts = status.counts || {}
        setExecuteData({
          total_migrated: status.total_migrated || 0,
          sources_touched: Object.values(counts).filter((c) => c > 0).length,
          counts,
          errors: status.errors || [],
          backup_timestamp: status.backup_timestamp || '',
        })
        setStep(STEP.DONE)
      }
    } catch (err) {
      setError(err.message)
    }
  }, [polling])

  const openRevert = useCallback(() => {
    setError(null)
    setRevertOpen(true)
  }, [])

  const handleRevert = useCallback(async () => {
    setRevertBusy(true)
    try {
      const data = await migrationAjax('revert')
      setRevertOpen(false)
      resetState()
      handleOpenChange(false)
      toast({
        variant: 'success',
        title: __('Restored', 'wp-sms'),
        description: sprintf(
          __('%d numbers restored to their original format.', 'wp-sms'),
          data.total_reverted || 0
        ),
      })
      window.dispatchEvent(new CustomEvent('wpsms:number-migration-done'))
    } catch (err) {
      setError(err.message)
    } finally {
      setRevertBusy(false)
    }
  }, [resetState, handleOpenChange, toast])

  const handleClearBackup = useCallback(async () => {
    setLoading(true)
    setError(null)
    try {
      await migrationAjax('clear_backup')
      setScanData((prev) =>
        prev
          ? { ...prev, backup_exists: false, backup_timestamp: null, last_run_had_errors: false }
          : prev
      )
      toast({
        variant: 'success',
        title: __('Cleared', 'wp-sms'),
        description: __('The old backup was cleared.', 'wp-sms'),
      })
    } catch (err) {
      setError(err.message)
    } finally {
      setLoading(false)
    }
  }, [toast])

  const handleWrongCountry = useCallback(() => {
    setNeedsCC(true)
    setCcMode('default')
    setStep(STEP.COUNTRY)
  }, [])

  const handleRescan = useCallback(async () => {
    setExecuteData(null)
    setPreviewData(null)
    setPreviewPage(1)
    await handleScan()
  }, [handleScan])

  const handleNavigate = useCallback(
    (dest) => {
      window.dispatchEvent(new CustomEvent('wpsms:navigate', { detail: { dest } }))
      handleOpenChange(false)
    },
    [handleOpenChange]
  )

  const visibleSteps = useMemo(
    () => ALL_STEPS_META.filter((s) => (s.conditional ? needsCC : true)),
    [needsCC]
  )

  const currentStepIndex = useMemo(() => {
    const idx = visibleSteps.findIndex((s) => s.id === step)
    return idx === -1 ? 0 : idx
  }, [visibleSteps, step])

  const completedSteps = useMemo(
    () => Array.from({ length: currentStepIndex }, (_, i) => i),
    [currentStepIndex]
  )

  // Move focus to the active step's headline on every step change so screen
  // reader users don't lose their place between steps.
  useEffect(() => {
    if (!open) return
    const t = setTimeout(() => {
      headlineRef.current?.focus?.()
    }, 50)
    return () => clearTimeout(t)
  }, [step, open])

  return (
    <>
      <Dialog open={open} onOpenChange={handleOpenChange}>
        <DialogContent
          size="xl"
          showClose={step !== STEP.EXECUTE}
          onInteractOutside={(e) => {
            if (step === STEP.EXECUTE) e.preventDefault()
          }}
          onEscapeKeyDown={(e) => {
            if (step === STEP.EXECUTE) e.preventDefault()
          }}
        >
          <DialogHeader>
            <DialogTitle>{__('Phone number check', 'wp-sms')}</DialogTitle>
            <DialogDescription>
              {__('Get your numbers ready for reliable SMS delivery.', 'wp-sms')}
            </DialogDescription>
          </DialogHeader>

          <DialogBody>
            <WizardStepper
              steps={visibleSteps}
              currentStep={currentStepIndex}
              completedSteps={completedSteps}
            />

            {error && (
              <Alert variant="destructive" className="wsms-mb-4" aria-live="assertive">
                <AlertDescription>
                  <div className="wsms-flex wsms-items-center wsms-justify-between wsms-gap-2">
                    <span>{error}</span>
                    <div className="wsms-flex wsms-gap-2">
                      <Button
                        variant="outline"
                        size="sm"
                        onClick={() => {
                          setError(null)
                          if (step === STEP.EXECUTE) setStep(STEP.REVIEW)
                        }}
                      >
                        {__('Dismiss', 'wp-sms')}
                      </Button>
                    </div>
                  </div>
                </AlertDescription>
              </Alert>
            )}

            {polling && (
              <Alert variant="info" className="wsms-mb-4">
                <AlertDescription>
                  {__(
                    "Another administrator started this update a few seconds ago. We'll check on it."
                  , 'wp-sms')}
                </AlertDescription>
              </Alert>
            )}

            {step === STEP.INTRO && (
              <IntroStep
                headlineRef={headlineRef}
                loading={loading}
                onStart={handleScan}
                onCancel={() => handleOpenChange(false)}
              />
            )}

            {step === STEP.COUNTRY && (
              <CountryStep
                headlineRef={headlineRef}
                mode={ccMode}
                value={overrideCC}
                onChange={setOverrideCC}
                loading={loading}
                onContinue={handleScan}
                onBack={() => setStep(STEP.INTRO)}
              />
            )}

            {step === STEP.REVIEW && scanData && (
              <ReviewStep
                headlineRef={headlineRef}
                scanData={scanData}
                loading={loading}
                onNext={() => handlePreviewLoad(1)}
                onBack={() => setStep(STEP.INTRO)}
                onClose={() => handleOpenChange(false)}
                onRevertOldBackup={openRevert}
                onClearOldBackup={handleClearBackup}
                onWrongCountry={handleWrongCountry}
              />
            )}

            {step === STEP.PREVIEW && previewData && (
              <PreviewStep
                headlineRef={headlineRef}
                previewData={previewData}
                previewPage={previewPage}
                loading={loading}
                onApply={handleExecute}
                onBack={() => setStep(STEP.REVIEW)}
                onPageChange={(page) => handlePreviewLoad(page, false)}
                onWrongCountry={handleWrongCountry}
              />
            )}

            {step === STEP.EXECUTE && (
              <ExecuteStep
                headlineRef={headlineRef}
                estimatedMs={estimatedMs}
                isDone={false}
                onForceRefresh={handleForceRefresh}
              />
            )}

            {step === STEP.DONE && executeData && (
              <DoneStep
                headlineRef={headlineRef}
                executeData={executeData}
                onClose={() => handleOpenChange(false)}
                onRequestRevert={openRevert}
                onNavigate={handleNavigate}
                onRescan={handleRescan}
              />
            )}
          </DialogBody>
        </DialogContent>
      </Dialog>

      <DeleteConfirmDialog
        isOpen={revertOpen}
        onClose={() => setRevertOpen(false)}
        onConfirm={handleRevert}
        isSaving={revertBusy}
        title={__('Undo this update?', 'wp-sms')}
        description={sprintf(
          __(
            "We'll restore all %1$d numbers to their original format using the backup from %2$s. This replaces the current values — any changes made to those numbers since the update will be overwritten."
          , 'wp-sms'),
          executeData?.total_migrated || scanData?.total_need_fix || 0,
          executeData?.backup_timestamp || scanData?.backup_timestamp || __('the previous run', 'wp-sms')
        )}
        confirmLabel={__('Yes, undo', 'wp-sms')}
      />
    </>
  )
}
