import React, { useState, useEffect, useCallback, useMemo } from 'react'
import * as DialogPrimitive from '@radix-ui/react-dialog'
import {
  X,
  ChevronLeft,
  ChevronRight,
  Loader2,
  Phone,
  Radio,
  Settings,
  Send,
  Crown,
  PartyPopper,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Logo } from '@/components/ui/logo'
import { useSettings } from '@/context/SettingsContext'
import { settingsApi } from '@/api/settingsApi'
import { wizardApi } from '@/api/wizardApi'
import { getWpSettings, cn, __ } from '@/lib/utils'

import WizardStepper from './WizardStepper'
import GettingStartedStep from './steps/GettingStartedStep'
import SmsGatewayStep from './steps/SmsGatewayStep'
import ConfigurationStep from './steps/ConfigurationStep'
import TestSetupStep from './steps/TestSetupStep'
import ProStep from './steps/ProStep'
import ReadyStep from './steps/ReadyStep'

/**
 * Determine if wizard should auto-open
 * Computed once at module load to avoid flash/flicker
 */
function shouldAutoOpenWizard() {
  // Check for ?wizard=open URL parameter
  const urlParams = new URLSearchParams(window.location.search)
  if (urlParams.get('wizard') === 'open') {
    return true
  }

  const { settings = {}, features = {} } = getWpSettings()
  const wizardCompleted = features?.wizardCompleted || false
  const gatewayConfigured = !!settings?.gateway_name
  return !wizardCompleted && !gatewayConfigured
}

/**
 * Full-screen setup wizard modal
 */
export default function SetupWizard() {
  const { updateSetting, setCurrentPage } = useSettings()

  // Initialize with correct value to avoid flash
  const [isOpen, setIsOpen] = useState(shouldAutoOpenWizard)
  const [currentStep, setCurrentStep] = useState(0)
  const [completedSteps, setCompletedSteps] = useState([])
  const [saving, setSaving] = useState(false)

  // Form data state
  const [phoneNumber, setPhoneNumber] = useState('')
  const [countryCode, setCountryCode] = useState('')
  const [isPhoneValid, setIsPhoneValid] = useState(false)
  const [selectedGateway, setSelectedGateway] = useState('')
  const [credentials, setCredentials] = useState({})
  const [connectionTested, setConnectionTested] = useState(false)
  const [testSmsSent, setTestSmsSent] = useState(false)

  // Determine if Pro step should be shown
  const { addons = {} } = getWpSettings()
  const showProStep = !addons.pro

  // Build steps array based on conditions
  const steps = useMemo(() => {
    const baseSteps = [
      { id: 'getting-started', label: __('Getting Started') },
      { id: 'sms-gateway', label: __('SMS Gateway') },
      { id: 'configuration', label: __('Configuration') },
      { id: 'test-setup', label: __('Test Setup') },
    ]

    if (showProStep) {
      baseSteps.push({ id: 'pro', label: __('Pro') })
    }

    baseSteps.push({ id: 'ready', label: __('Ready') })

    return baseSteps
  }, [showProStep])

  // Expose open function globally for "Re-run Wizard" button
  useEffect(() => {
    window.wpSmsOpenWizard = () => {
      setIsOpen(true)
      setCurrentStep(0)
      setCompletedSteps([])
    }
    return () => {
      delete window.wpSmsOpenWizard
    }
  }, [])

  // Handle phone change
  const handlePhoneChange = useCallback((number, code) => {
    setPhoneNumber(number)
    setCountryCode(code)
  }, [])

  // Save current step data
  const saveStepData = useCallback(async () => {
    setSaving(true)
    try {
      const settingsToSave = {}

      switch (steps[currentStep].id) {
        case 'getting-started':
          settingsToSave.admin_mobile_number = phoneNumber
          settingsToSave.admin_mobile_number_country_prefix = countryCode
          break
        case 'sms-gateway':
          settingsToSave.gateway_name = selectedGateway
          break
        case 'configuration':
          // Save all credential fields
          Object.assign(settingsToSave, credentials)
          break
      }

      if (Object.keys(settingsToSave).length > 0) {
        await settingsApi.updateSettings({ settings: settingsToSave })

        // Update local settings context
        Object.entries(settingsToSave).forEach(([key, value]) => {
          updateSetting(key, value)
        })
      }

      return true
    } catch (error) {
      console.error('Failed to save step data:', error)
      return false
    } finally {
      setSaving(false)
    }
  }, [currentStep, steps, phoneNumber, countryCode, selectedGateway, credentials, updateSetting])

  // Check if current step can proceed
  const canProceed = useMemo(() => {
    const stepId = steps[currentStep]?.id

    switch (stepId) {
      case 'getting-started':
        return isPhoneValid && phoneNumber
      case 'sms-gateway':
        return !!selectedGateway
      case 'configuration':
        return connectionTested
      case 'test-setup':
        return true // Can always skip
      case 'pro':
        return true // Can always skip
      case 'ready':
        return true
      default:
        return true
    }
  }, [currentStep, steps, isPhoneValid, phoneNumber, selectedGateway, connectionTested])

  // Go to next step
  const handleNext = useCallback(async () => {
    // Save current step data first
    const saved = await saveStepData()
    if (!saved) return

    // Mark current step as completed
    if (!completedSteps.includes(currentStep)) {
      setCompletedSteps((prev) => [...prev, currentStep])
    }

    // Move to next step
    if (currentStep < steps.length - 1) {
      setCurrentStep((prev) => prev + 1)
    }
  }, [currentStep, steps.length, completedSteps, saveStepData])

  // Go to previous step
  const handlePrev = useCallback(() => {
    if (currentStep > 0) {
      setCurrentStep((prev) => prev - 1)
    }
  }, [currentStep])

  // Handle wizard close
  const handleClose = useCallback(async (navigateToPage = null) => {
    // Mark wizard as completed if on last step
    if (steps[currentStep]?.id === 'ready') {
      try {
        await wizardApi.markWizardComplete()
      } catch (error) {
        console.error('Failed to mark wizard complete:', error)
      }
      // Refresh the page to reload settings from server
      // This prevents "unsaved changes" warning since settings are already saved via API
      const baseUrl = window.location.href.split('#')[0]
      if (navigateToPage) {
        window.location.href = `${baseUrl}#/${navigateToPage}`
      } else {
        window.location.reload()
      }
      return
    }
    setIsOpen(false)
  }, [currentStep, steps])

  // Navigate to a page and close wizard (triggers page refresh)
  const handleNavigate = useCallback((pageId) => {
    handleClose(pageId)
  }, [handleClose])

  // Render current step content
  const renderStepContent = () => {
    const stepId = steps[currentStep]?.id

    switch (stepId) {
      case 'getting-started':
        return (
          <GettingStartedStep
            phoneNumber={phoneNumber}
            countryCode={countryCode}
            onPhoneChange={handlePhoneChange}
            isValid={isPhoneValid}
            onValidChange={setIsPhoneValid}
          />
        )
      case 'sms-gateway':
        return (
          <SmsGatewayStep
            selectedGateway={selectedGateway}
            onGatewaySelect={setSelectedGateway}
          />
        )
      case 'configuration':
        return (
          <ConfigurationStep
            gatewayName={selectedGateway}
            credentials={credentials}
            onCredentialChange={setCredentials}
            onTestSuccess={() => setConnectionTested(true)}
          />
        )
      case 'test-setup':
        return (
          <TestSetupStep
            phoneNumber={phoneNumber}
            onTestComplete={(success) => setTestSmsSent(success)}
          />
        )
      case 'pro':
        return <ProStep onSkip={handleNext} />
      case 'ready':
        return (
          <ReadyStep
            gatewayName={selectedGateway}
            onNavigate={handleNavigate}
            onClose={handleClose}
          />
        )
      default:
        return null
    }
  }

  // Get button labels based on current step
  const getNextButtonLabel = () => {
    const stepId = steps[currentStep]?.id
    if (stepId === 'ready') return __('Finish')
    if (stepId === 'test-setup' && !testSmsSent) return __('Skip')
    return __('Continue')
  }

  if (!isOpen) return null

  return (
    <DialogPrimitive.Root open={isOpen} onOpenChange={setIsOpen} modal={false}>
      <DialogPrimitive.Portal container={document.getElementById('wpsms-settings-root')}>
        {/* Overlay - manually handle click to prevent close */}
        <div
          className="wsms-fixed wsms-inset-0 wsms-bg-black/70 wsms-backdrop-blur-sm"
          style={{ zIndex: 999998 }}
          onClick={(e) => e.stopPropagation()}
        />

        {/* Content */}
        <DialogPrimitive.Content
          className={cn(
            'wsms-fixed wsms-inset-4 lg:wsms-inset-8 wsms-rounded-2xl wsms-bg-background wsms-shadow-2xl',
            'wsms-flex wsms-flex-col wsms-overflow-hidden'
          )}
          style={{ zIndex: 999999 }}
          onInteractOutside={(e) => e.preventDefault()}
        >
          {/* Header */}
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-px-6 wsms-py-4 wsms-border-b wsms-border-border">
            <div className="wsms-flex wsms-items-center wsms-gap-3">
              <Logo className="wsms-h-8 wsms-w-auto" />
              <div>
                <h1 className="wsms-text-[15px] wsms-font-semibold wsms-text-foreground">
                  {__('WP SMS Setup Wizard')}
                </h1>
                <p className="wsms-text-[11px] wsms-text-muted-foreground">
                  {__('Step')} {currentStep + 1} {__('of')} {steps.length}
                </p>
              </div>
            </div>

            <DialogPrimitive.Close asChild>
              <Button
                variant="ghost"
                size="sm"
                onClick={handleClose}
                className="wsms-text-muted-foreground hover:wsms-text-foreground"
              >
                <X className="wsms-h-4 wsms-w-4" />
              </Button>
            </DialogPrimitive.Close>
          </div>

          {/* Stepper */}
          <div className="wsms-px-6 wsms-border-b wsms-border-border wsms-bg-muted/20">
            <WizardStepper
              currentStep={currentStep}
              completedSteps={completedSteps}
              steps={steps.map((s, i) => ({
                ...s,
                icon: getStepIcon(s.id),
              }))}
              onStepClick={(index) => {
                if (completedSteps.includes(index)) {
                  setCurrentStep(index)
                }
              }}
            />
          </div>

          {/* Content */}
          <div className="wsms-flex-1 wsms-overflow-y-auto wsms-p-6 lg:wsms-p-8">
            {renderStepContent()}
          </div>

          {/* Footer Navigation */}
          {steps[currentStep]?.id !== 'ready' && (
            <div className="wsms-flex wsms-items-center wsms-justify-between wsms-px-6 wsms-py-4 wsms-border-t wsms-border-border wsms-bg-muted/20">
              <Button
                variant="ghost"
                onClick={handlePrev}
                disabled={currentStep === 0}
                className={currentStep === 0 ? 'wsms-invisible' : ''}
              >
                <ChevronLeft className="wsms-h-4 wsms-w-4 wsms-mr-1" />
                {__('Back')}
              </Button>

              <Button onClick={handleNext} disabled={!canProceed || saving}>
                {saving ? (
                  <>
                    <Loader2 className="wsms-h-4 wsms-w-4 wsms-mr-1.5 wsms-animate-spin" />
                    {__('Saving...')}
                  </>
                ) : (
                  <>
                    {getNextButtonLabel()}
                    <ChevronRight className="wsms-h-4 wsms-w-4 wsms-ml-1" />
                  </>
                )}
              </Button>
            </div>
          )}
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  )
}

// Helper to get step icons
function getStepIcon(stepId) {
  const icons = {
    'getting-started': Phone,
    'sms-gateway': Radio,
    configuration: Settings,
    'test-setup': Send,
    pro: Crown,
    ready: PartyPopper,
  }
  return icons[stepId] || Settings
}
