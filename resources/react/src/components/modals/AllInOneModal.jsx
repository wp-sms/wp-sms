import React, { useState, useEffect, useCallback, useRef } from 'react'
import * as DialogPrimitive from '@radix-ui/react-dialog'
import {
  X,
  ChevronLeft,
  ChevronRight,
  Check,
  AlertTriangle,
  ExternalLink,
  Crown,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { useAllInOneModal } from '@/hooks/useAllInOneModal'
import { useRootZIndex } from '@/hooks/useRootZIndex'
import { getWpSettings, cn, __ } from '@/lib/utils'

const WP_SMS_SITE = 'https://wp-sms-pro.com'
const PRICING_URL = `${WP_SMS_SITE}/pricing/?utm_source=wp-sms&utm_medium=link&utm_campaign=pop-up-aio`
const AUTO_SLIDE_INTERVAL = 5000

/**
 * Step definitions matching the legacy modal
 */
const STEPS = [
  {
    id: 'first-step',
    slug: 'first-step',
    addonName: null,
    title: null, // Dynamic — set at render based on license state
    description: null, // Dynamic
    learnMoreSlug: null,
  },
  {
    id: 'wp-sms-pro',
    slug: 'wp-sms-pro',
    addonName: 'WSMS Pro',
    title: __('Key SMS Tools for Your Site'),
    description: __('WSMS Pro offers phone logins, two-factor authentication, scheduled and repeating messages, shorter Bitly URLs, and a Gutenberg block. It also integrates with WooCommerce, BuddyPress, Quform, Gravity Forms, Easy Digital Downloads, WP Job Manager, and WP Awesome Support.'),
    learnMoreSlug: 'wp-sms-pro',
  },
  {
    id: 'wp-sms-woocommerce-pro',
    slug: 'wp-sms-woocommerce-pro',
    addonName: 'WSMS WooCommerce Pro',
    title: __('Advanced WooCommerce SMS Features'),
    description: __('WooCommerce Pro boosts sales and support with SMS campaigns, abandoned cart reminders, phone verification at checkout, SMS login and registration, and local shipping notifications.'),
    learnMoreSlug: 'wp-sms-woocommerce-pro',
  },
  {
    id: 'wp-sms-two-way',
    slug: 'wp-sms-two-way',
    addonName: 'WSMS Two-Way',
    title: __('Send and Receive Messages'),
    description: __('Two-Way lets you view incoming texts in your dashboard, set keywords to trigger replies, allow customers to update orders via SMS, and let subscribers join or leave newsletters by texting.'),
    learnMoreSlug: 'wp-sms-two-way',
  },
  {
    id: 'wp-sms-elementor-form',
    slug: 'wp-sms-elementor-form',
    addonName: 'WSMS Elementor Form',
    title: __('Elementor Form SMS Alerts'),
    description: __('Link your Elementor Pro forms to WSMS and send text message alerts to you and your users whenever a form is submitted.'),
    learnMoreSlug: 'wp-sms-elementor-form',
  },
  {
    id: 'wp-sms-membership-integrations',
    slug: 'wp-sms-membership-integrations',
    addonName: 'WSMS Membership Integrations',
    title: __('Keep Members Informed'),
    description: __('Send automatic text messages whenever important membership events happen, like new signups, payment confirmations, or membership cancellations, so everyone stays in the loop.'),
    learnMoreSlug: 'wp-sms-membership-integrations',
  },
  {
    id: 'wp-sms-booking-integrations',
    slug: 'wp-sms-booking-integrations',
    addonName: 'WSMS Booking Integrations',
    title: __('Booking SMS Notifications'),
    description: __('Send SMS messages whenever important booking events happen. Automatically notify users for new, approved, canceled, or rescheduled appointments.'),
    learnMoreSlug: 'wp-sms-booking-integrations',
  },
  {
    id: 'wp-sms-fluent-integrations',
    slug: 'wp-sms-fluent-integrations',
    addonName: 'WSMS Fluent Integrations',
    title: __('Connect with Fluent'),
    description: __('Connect WSMS with Fluent CRM, Fluent Forms, and Fluent Support. Get real-time SMS notifications for new subscribers, form submissions, or support tickets.'),
    learnMoreSlug: 'wp-sms-fluent-integrations',
  },
]

/**
 * Get the screenshot image URL for a step
 */
function getStepImageUrl(slug) {
  const { pluginUrl } = getWpSettings()
  const baseUrl = pluginUrl || '/wp-content/plugins/wp-sms/'
  return `${baseUrl}assets/images/premium-modal/${slug}.png`
}

/**
 * Get dynamic first-step content based on license state
 */
function getFirstStepContent(isPremium, hasAnyLicense) {
  if (isPremium) {
    return {
      title: __("You're All Set with WSMS All-in-One"),
      description: __("You already have the complete bundle! Enjoy every premium feature and integration with no extra steps. Thanks for your support — have fun exploring everything!"),
    }
  }
  if (hasAnyLicense) {
    return {
      title: __("You're Already Enjoying Add-Ons!"),
      description: __("Looks like you have a few premium features active. Upgrade to All-in-One to unlock every tool and integration. Get the most out of WSMS and boost your site's performance."),
    }
  }
  return {
    title: __('All Premium SMS Features in One Package'),
    description: __('All-in-One includes Pro, WooCommerce Pro, Two-Way, and more. Send better SMS, handle two-way messaging, secure logins, and manage everything in one place.'),
  }
}

/**
 * Get addon status for a specific slug from the addons array
 */
function getAddonStatus(addons, slug) {
  return addons.find((a) => a.slug === slug) || { isActive: false, isInstalled: false, hasLicense: false }
}

/**
 * Sidebar addon status badge
 */
function AddonBadge({ addon }) {
  if (addon.hasLicense && addon.isActive) {
    return (
      <span className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[10px] wsms-font-medium wsms-text-emerald-600 wsms-bg-emerald-50 wsms-px-1.5 wsms-py-0.5 wsms-rounded-full">
        <Check className="wsms-h-2.5 wsms-w-2.5" />
        {__('Active')}
      </span>
    )
  }
  if (addon.hasLicense && !addon.isInstalled) {
    return (
      <span className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[10px] wsms-font-medium wsms-text-amber-600 wsms-bg-amber-50 wsms-px-1.5 wsms-py-0.5 wsms-rounded-full">
        {__('Not Installed')}
      </span>
    )
  }
  if (addon.hasLicense && !addon.isActive) {
    return (
      <span className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[10px] wsms-font-medium wsms-text-amber-600 wsms-bg-amber-50 wsms-px-1.5 wsms-py-0.5 wsms-rounded-full">
        {__('Not Activated')}
      </span>
    )
  }
  if (!addon.hasLicense && addon.isActive) {
    return (
      <span className="wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[10px] wsms-font-medium wsms-text-red-600 wsms-bg-red-50 wsms-px-1.5 wsms-py-0.5 wsms-rounded-full">
        {__('No License')}
      </span>
    )
  }
  return null
}

/**
 * Per-step notice banners (matching legacy notices)
 */
function StepNotice({ addon, step }) {
  if (step.slug === 'first-step') return null

  if (addon.hasLicense && !addon.isInstalled) {
    return (
      <div className="wsms-flex wsms-items-start wsms-gap-2.5 wsms-p-3 wsms-rounded-lg wsms-bg-amber-50 wsms-border wsms-border-amber-200 wsms-mt-4">
        <AlertTriangle className="wsms-h-4 wsms-w-4 wsms-text-amber-500 wsms-mt-0.5 wsms-shrink-0" />
        <p className="wsms-text-[12px] wsms-text-amber-800 wsms-leading-relaxed">
          {__('Your license includes the')}{' '}
          <strong>{step.addonName}</strong>
          {__(', but it\'s not installed yet. Go to the Add-Ons page to install and')}{' '}
          <strong>{__('activate')}</strong>{' '}
          {__('it, so you can start using all its features.')}
        </p>
      </div>
    )
  }

  if (!addon.hasLicense && addon.isActive) {
    return (
      <div className="wsms-flex wsms-items-start wsms-gap-2.5 wsms-p-3 wsms-rounded-lg wsms-bg-red-50 wsms-border wsms-border-red-200 wsms-mt-4">
        <AlertTriangle className="wsms-h-4 wsms-w-4 wsms-text-red-500 wsms-mt-0.5 wsms-shrink-0" />
        <p className="wsms-text-[12px] wsms-text-red-800 wsms-leading-relaxed">
          {__('This add-on does')}{' '}
          <strong>{__('not have an active license')}</strong>
          {__(', which means it cannot receive updates, including important security updates. For uninterrupted access to updates and to keep your site secure, we strongly recommend activating a license.')}
        </p>
      </div>
    )
  }

  return null
}

/**
 * Action buttons per step (matching legacy logic)
 */
function StepActions({ step, addon, isPremium, onClose }) {
  const { adminUrl } = getWpSettings()
  const addOnsPageUrl = `${adminUrl}admin.php?page=wp-sms-add-ons`

  // First step actions
  if (step.slug === 'first-step') {
    if (isPremium) {
      return (
        <Button disabled className="wsms-gap-1.5">
          <Crown className="wsms-h-4 wsms-w-4" />
          {__('All-in-One Activated')}
        </Button>
      )
    }
    return (
      <div className="wsms-flex wsms-items-center wsms-gap-3">
        <Button asChild>
          <a href={PRICING_URL} target="_blank" rel="noopener noreferrer">
            {__('Upgrade Now')}
            <ExternalLink className="wsms-h-3.5 wsms-w-3.5" />
          </a>
        </Button>
        <Button variant="ghost" onClick={onClose}>
          {__('Maybe Later')}
        </Button>
      </div>
    )
  }

  // Per-addon step actions
  if (!addon.hasLicense && !addon.isInstalled) {
    return (
      <div className="wsms-flex wsms-items-center wsms-gap-3">
        <Button asChild>
          <a href={PRICING_URL} target="_blank" rel="noopener noreferrer">
            {__('Upgrade to All-in-One')}
            <ExternalLink className="wsms-h-3.5 wsms-w-3.5" />
          </a>
        </Button>
        <Button variant="ghost" onClick={onClose}>
          {__('Maybe Later')}
        </Button>
      </div>
    )
  }

  if ((addon.hasLicense && !addon.isActive) || (!addon.hasLicense && addon.isInstalled)) {
    return (
      <Button asChild variant="outline">
        <a href={addOnsPageUrl}>
          {__('Go to Add-Ons Page')}
        </a>
      </Button>
    )
  }

  if (addon.hasLicense && addon.isActive) {
    return (
      <Button disabled className="wsms-gap-1.5">
        <Check className="wsms-h-4 wsms-w-4" />
        {__('Add-on Activated')}
      </Button>
    )
  }

  // Fallback: upgrade button
  return (
    <div className="wsms-flex wsms-items-center wsms-gap-3">
      <Button asChild>
        <a href={PRICING_URL} target="_blank" rel="noopener noreferrer">
          {__('Upgrade to All-in-One')}
          <ExternalLink className="wsms-h-3.5 wsms-w-3.5" />
        </a>
      </Button>
      <Button variant="ghost" onClick={onClose}>
        {__('Maybe Later')}
      </Button>
    </div>
  )
}

/**
 * All-in-One upgrade modal — full recreation of the legacy premium modal
 */
export default function AllInOneModal() {
  const { isOpen, close, open, addons, isPremium } = useAllInOneModal()
  const [currentStep, setCurrentStep] = useState(0)
  const [autoSlideActive, setAutoSlideActive] = useState(true)
  const intervalRef = useRef(null)

  const hasAnyLicense = addons.some((a) => a.hasLicense)

  // Shared ref-counted z-index boost — won't conflict with SetupWizard
  useRootZIndex(isOpen)

  // Expose global open function for manual triggers
  useEffect(() => {
    window.wpSmsOpenAioModal = () => {
      setCurrentStep(0)
      setAutoSlideActive(true)
      open()
    }
    return () => {
      delete window.wpSmsOpenAioModal
    }
  }, [open])

  // Auto-slide
  useEffect(() => {
    if (!isOpen || !autoSlideActive) {
      if (intervalRef.current) {
        clearInterval(intervalRef.current)
        intervalRef.current = null
      }
      return
    }

    intervalRef.current = setInterval(() => {
      setCurrentStep((prev) => (prev + 1) % STEPS.length)
    }, AUTO_SLIDE_INTERVAL)

    return () => {
      if (intervalRef.current) {
        clearInterval(intervalRef.current)
        intervalRef.current = null
      }
    }
  }, [isOpen, autoSlideActive])

  const stopAutoSlide = useCallback(() => {
    setAutoSlideActive(false)
  }, [])

  const goToStep = useCallback(
    (index) => {
      stopAutoSlide()
      setCurrentStep(index)
    },
    [stopAutoSlide]
  )

  const goNext = useCallback(() => {
    stopAutoSlide()
    setCurrentStep((prev) => (prev + 1) % STEPS.length)
  }, [stopAutoSlide])

  const goPrev = useCallback(() => {
    stopAutoSlide()
    setCurrentStep((prev) => (prev - 1 + STEPS.length) % STEPS.length)
  }, [stopAutoSlide])

  if (!isOpen) return null

  const step = STEPS[currentStep]
  const addonStatus = step.slug !== 'first-step' ? getAddonStatus(addons, step.slug) : null

  // Get dynamic first-step content
  const firstStepContent = getFirstStepContent(isPremium, hasAnyLicense)
  const stepTitle = step.slug === 'first-step' ? firstStepContent.title : step.title
  const stepDescription = step.slug === 'first-step' ? firstStepContent.description : step.description

  return (
    <DialogPrimitive.Root open={isOpen} onOpenChange={() => {}} modal={false}>
      <DialogPrimitive.Portal container={document.getElementById('wpsms-settings-root')}>
        {/* Overlay */}
        <div
          className="wsms-fixed wsms-inset-0 wsms-bg-black/70 wsms-backdrop-blur-sm"
          style={{ zIndex: 999998 }}
          onClick={(e) => e.stopPropagation()}
        />

        {/* Content */}
        <DialogPrimitive.Content
          className={cn(
            'wsms-fixed wsms-inset-4 lg:wsms-inset-8',
            'wsms-rounded-2xl wsms-bg-background wsms-shadow-2xl',
            'wsms-flex wsms-flex-col wsms-overflow-hidden'
          )}
          style={{ zIndex: 999999 }}
          onInteractOutside={(e) => e.preventDefault()}
          aria-describedby={undefined}
        >
          {/* Header */}
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-px-5 wsms-py-3.5 wsms-border-b wsms-border-border wsms-bg-muted/30">
            <div className="wsms-flex wsms-items-center wsms-gap-2.5">
              <div className="wsms-flex wsms-items-center wsms-justify-center wsms-h-8 wsms-w-8 wsms-rounded-lg wsms-bg-primary/10">
                <Crown className="wsms-h-4 wsms-w-4 wsms-text-primary" />
              </div>
              <div>
                <DialogPrimitive.Title className="wsms-text-[14px] wsms-font-semibold wsms-text-foreground wsms-leading-tight">
                  {__('WSMS All-in-One')}
                </DialogPrimitive.Title>
                <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-leading-tight wsms-mt-0.5">
                  {stepTitle}
                </p>
              </div>
            </div>

            <button
              onClick={close}
              className="wsms-flex wsms-items-center wsms-justify-center wsms-h-8 wsms-w-8 wsms-rounded-lg wsms-text-muted-foreground hover:wsms-text-foreground hover:wsms-bg-accent wsms-transition-colors"
            >
              <X className="wsms-h-4 wsms-w-4" />
            </button>
          </div>

          {/* Body */}
          <div className="wsms-flex-1 wsms-flex wsms-flex-col lg:wsms-flex-row wsms-overflow-hidden">
            {/* Main Content */}
            <div className="wsms-flex-1 wsms-flex wsms-flex-col wsms-overflow-y-auto wsms-p-5 lg:wsms-p-6">
              {/* Step progress dots */}
              <div className="wsms-flex wsms-items-center wsms-justify-center wsms-gap-1.5 wsms-mb-5">
                {STEPS.map((s, i) => (
                  <button
                    key={s.id}
                    onClick={() => goToStep(i)}
                    className={cn(
                      'wsms-h-1.5 wsms-rounded-full wsms-transition-all wsms-duration-300',
                      i === currentStep
                        ? 'wsms-w-6 wsms-bg-primary'
                        : 'wsms-w-1.5 wsms-bg-border hover:wsms-bg-muted-foreground/40'
                    )}
                    aria-label={`${__('Go to step')} ${i + 1}`}
                  />
                ))}
              </div>

              {/* Step title & description */}
              <div className="wsms-mb-4">
                <h2 className="wsms-text-lg wsms-font-semibold wsms-text-foreground wsms-leading-tight">
                  {stepTitle}
                </h2>
                <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-leading-relaxed wsms-mt-2">
                  {stepDescription}
                  {step.learnMoreSlug && (
                    <>
                      {' '}
                      <a
                        href={`${WP_SMS_SITE}/product/${step.learnMoreSlug}/?utm_source=wp-sms&utm_medium=link&utm_campaign=pop-up-aio`}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="wsms-text-primary hover:wsms-underline wsms-font-medium"
                      >
                        {__('Learn more')}
                      </a>
                    </>
                  )}
                </p>
              </div>

              {/* Screenshot */}
              <div className="wsms-flex-1 wsms-flex wsms-items-center wsms-justify-center wsms-min-h-0">
                <div className="wsms-w-full wsms-max-w-[520px] wsms-rounded-xl wsms-overflow-hidden wsms-border wsms-border-border wsms-bg-muted/20">
                  <img
                    src={getStepImageUrl(step.slug)}
                    alt={stepTitle}
                    className="wsms-w-full wsms-h-auto wsms-block"
                    loading={currentStep === 0 ? 'eager' : 'lazy'}
                  />
                </div>
              </div>

              {/* Per-step notice */}
              {addonStatus && <StepNotice addon={addonStatus} step={step} />}

              {/* Navigation arrows (mobile: below content) */}
              <div className="wsms-flex wsms-items-center wsms-justify-between wsms-mt-4 lg:wsms-hidden">
                <button
                  onClick={goPrev}
                  className="wsms-flex wsms-items-center wsms-justify-center wsms-h-9 wsms-w-9 wsms-rounded-lg wsms-border wsms-border-border wsms-bg-card wsms-text-muted-foreground hover:wsms-text-foreground hover:wsms-bg-accent wsms-transition-colors"
                >
                  <ChevronLeft className="wsms-h-4 wsms-w-4" />
                </button>
                <span className="wsms-text-[12px] wsms-text-muted-foreground">
                  {currentStep + 1} / {STEPS.length}
                </span>
                <button
                  onClick={goNext}
                  className="wsms-flex wsms-items-center wsms-justify-center wsms-h-9 wsms-w-9 wsms-rounded-lg wsms-border wsms-border-border wsms-bg-card wsms-text-muted-foreground hover:wsms-text-foreground hover:wsms-bg-accent wsms-transition-colors"
                >
                  <ChevronRight className="wsms-h-4 wsms-w-4" />
                </button>
              </div>
            </div>

            {/* Sidebar */}
            <div className="wsms-hidden lg:wsms-flex wsms-flex-col wsms-w-[260px] wsms-border-s wsms-border-border wsms-bg-muted/20">
              {/* Sidebar header */}
              <div className="wsms-px-4 wsms-py-3 wsms-border-b wsms-border-border">
                <p className="wsms-text-[12px] wsms-font-semibold wsms-text-foreground wsms-uppercase wsms-tracking-wide">
                  {__('All-in-One Includes')}
                </p>
              </div>

              {/* Addon list */}
              <div className="wsms-flex-1 wsms-overflow-y-auto wsms-py-1">
                {addons.filter((addon) => STEPS.some((s) => s.slug === addon.slug)).map((addon, i) => {
                  const stepIndex = STEPS.findIndex((s) => s.slug === addon.slug)
                  const isActive = stepIndex === currentStep

                  return (
                    <button
                      key={addon.slug}
                      onClick={() => goToStep(stepIndex)}
                      className={cn(
                        'wsms-w-full wsms-flex wsms-items-center wsms-justify-between wsms-gap-2 wsms-px-4 wsms-py-2.5 wsms-text-start wsms-transition-colors',
                        isActive
                          ? 'wsms-bg-primary/5 wsms-border-s-2 wsms-border-s-primary'
                          : 'wsms-border-s-2 wsms-border-s-transparent hover:wsms-bg-accent/50'
                      )}
                    >
                      <span
                        className={cn(
                          'wsms-text-[12.5px] wsms-leading-tight',
                          isActive ? 'wsms-font-semibold wsms-text-primary' : 'wsms-text-foreground'
                        )}
                      >
                        {addon.title}
                      </span>
                      <AddonBadge addon={addon} />
                    </button>
                  )
                })}
              </div>

              {/* Sidebar navigation arrows */}
              <div className="wsms-flex wsms-items-center wsms-justify-between wsms-px-4 wsms-py-2.5 wsms-border-t wsms-border-border">
                <button
                  onClick={goPrev}
                  className="wsms-flex wsms-items-center wsms-justify-center wsms-h-8 wsms-w-8 wsms-rounded-lg wsms-border wsms-border-border wsms-bg-card wsms-text-muted-foreground hover:wsms-text-foreground hover:wsms-bg-accent wsms-transition-colors"
                >
                  <ChevronLeft className="wsms-h-3.5 wsms-w-3.5" />
                </button>
                <span className="wsms-text-[11px] wsms-text-muted-foreground">
                  {currentStep + 1} / {STEPS.length}
                </span>
                <button
                  onClick={goNext}
                  className="wsms-flex wsms-items-center wsms-justify-center wsms-h-8 wsms-w-8 wsms-rounded-lg wsms-border wsms-border-border wsms-bg-card wsms-text-muted-foreground hover:wsms-text-foreground hover:wsms-bg-accent wsms-transition-colors"
                >
                  <ChevronRight className="wsms-h-3.5 wsms-w-3.5" />
                </button>
              </div>
            </div>
          </div>

          {/* Footer */}
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-px-5 wsms-py-3.5 wsms-border-t wsms-border-border wsms-bg-muted/30">
            <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-text-[11px] wsms-text-muted-foreground">
              <Crown className="wsms-h-3.5 wsms-w-3.5" />
              <span>{__('Unlock all premium features with one license')}</span>
            </div>
            <StepActions
              step={step}
              addon={addonStatus || { isActive: false, isInstalled: false, hasLicense: false }}
              isPremium={isPremium}
              onClose={close}
            />
          </div>
        </DialogPrimitive.Content>
      </DialogPrimitive.Portal>
    </DialogPrimitive.Root>
  )
}
