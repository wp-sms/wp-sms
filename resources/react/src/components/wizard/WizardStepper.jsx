import React from 'react'
import { Check, Phone, Radio, Settings, Send, Crown, PartyPopper } from 'lucide-react'
import { cn, __ } from '@/lib/utils'

/**
 * Step configuration with icons and labels
 */
const STEPS = [
  { id: 'getting-started', label: __('Getting Started'), icon: Phone },
  { id: 'sms-gateway', label: __('SMS Gateway'), icon: Radio },
  { id: 'configuration', label: __('Configuration'), icon: Settings },
  { id: 'test-setup', label: __('Test Setup'), icon: Send },
  { id: 'all-in-one', label: __('All-in-One'), icon: Crown, conditional: true },
  { id: 'ready', label: __('Ready'), icon: PartyPopper },
]

/**
 * Wizard stepper component showing progress through setup steps
 */
export default function WizardStepper({
  currentStep,
  completedSteps = [],
  steps = STEPS,
  onStepClick,
}) {
  return (
    <div className="wsms-flex wsms-items-center wsms-justify-center wsms-gap-0 wsms-py-5">
      {steps.map((step, index) => {
        const isCompleted = completedSteps.includes(index)
        const isCurrent = currentStep === index
        const isClickable = isCompleted && onStepClick
        const Icon = step.icon

        return (
          <React.Fragment key={step.id}>
            {/* Step */}
            <button
              type="button"
              onClick={() => isClickable && onStepClick(index)}
              disabled={!isClickable}
              className={cn(
                'wsms-flex wsms-flex-col wsms-items-center wsms-gap-2 wsms-transition-all wsms-duration-200 wsms-px-2',
                isClickable && 'wsms-cursor-pointer hover:wsms-opacity-80',
                !isClickable && 'wsms-cursor-default'
              )}
            >
              <div
                className={cn(
                  'wsms-flex wsms-h-10 wsms-w-10 wsms-items-center wsms-justify-center wsms-rounded-xl wsms-border-2 wsms-transition-all wsms-duration-200',
                  isCompleted && 'wsms-border-primary wsms-bg-primary wsms-text-primary-foreground wsms-shadow-sm',
                  isCurrent && !isCompleted && 'wsms-border-primary wsms-bg-primary/10 wsms-text-primary wsms-shadow-sm',
                  !isCompleted && !isCurrent && 'wsms-border-muted-foreground/20 wsms-bg-muted/50 wsms-text-muted-foreground'
                )}
              >
                {isCompleted ? (
                  <Check className="wsms-h-5 wsms-w-5" strokeWidth={2.5} />
                ) : (
                  <Icon className="wsms-h-5 wsms-w-5" />
                )}
              </div>
              <span
                className={cn(
                  'wsms-text-[11px] wsms-font-medium wsms-transition-colors wsms-whitespace-nowrap',
                  isCurrent && 'wsms-text-primary',
                  isCompleted && 'wsms-text-foreground',
                  !isCompleted && !isCurrent && 'wsms-text-muted-foreground'
                )}
              >
                {step.label}
              </span>
            </button>

            {/* Connector line */}
            {index < steps.length - 1 && (
              <div
                className={cn(
                  'wsms-h-0.5 wsms-w-8 wsms-transition-colors wsms-duration-200 wsms-mb-6 wsms-rounded-full',
                  isCompleted ? 'wsms-bg-primary' : 'wsms-bg-muted-foreground/20'
                )}
              />
            )}
          </React.Fragment>
        )
      })}
    </div>
  )
}

export { STEPS }
