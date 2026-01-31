import * as React from 'react'
import {
  Lightbulb,
  HelpCircle,
  Info,
  AlertTriangle,
  CheckCircle2,
  ArrowRight,
  Sparkles,
  BookOpen,
  ExternalLink,
  ChevronDown,
  ChevronUp,
  X,
} from 'lucide-react'
import { cn } from '@/lib/utils'
import { Button } from './button'

/**
 * Tip - Contextual help message with icon
 * Use for helpful hints, best practices, and guidance
 */
export function Tip({ children, className, variant = 'default', dismissible = false, onDismiss }) {
  const [dismissed, setDismissed] = React.useState(false)

  if (dismissed) return null

  const variants = {
    default: {
      container: 'wsms-bg-primary/5 wsms-border-primary/20',
      icon: 'wsms-text-primary',
      IconComponent: Lightbulb,
    },
    info: {
      container: 'wsms-bg-blue-500/5 wsms-border-blue-500/20',
      icon: 'wsms-text-blue-600 dark:wsms-text-blue-400',
      IconComponent: Info,
    },
    warning: {
      container: 'wsms-bg-amber-500/5 wsms-border-amber-500/20',
      icon: 'wsms-text-amber-600 dark:wsms-text-amber-400',
      IconComponent: AlertTriangle,
    },
    success: {
      container: 'wsms-bg-emerald-500/5 wsms-border-emerald-500/20',
      icon: 'wsms-text-emerald-600 dark:wsms-text-emerald-400',
      IconComponent: CheckCircle2,
    },
  }

  const { container, icon, IconComponent } = variants[variant] || variants.default

  const handleDismiss = () => {
    setDismissed(true)
    onDismiss?.()
  }

  return (
    <div
      className={cn(
        'wsms-flex wsms-items-start wsms-gap-3 wsms-p-3 wsms-rounded-lg wsms-border',
        container,
        className
      )}
    >
      <IconComponent className={cn('wsms-h-4 wsms-w-4 wsms-shrink-0 wsms-mt-0.5', icon)} />
      <p className="wsms-text-[12px] wsms-text-foreground wsms-leading-relaxed wsms-flex-1">
        {children}
      </p>
      {dismissible && (
        <button
          onClick={handleDismiss}
          className="wsms-p-1 wsms-rounded wsms-text-muted-foreground hover:wsms-text-foreground hover:wsms-bg-muted wsms-transition-colors"
        >
          <X className="wsms-h-3 wsms-w-3" />
        </button>
      )}
    </div>
  )
}

/**
 * SetupProgress - Visual progress indicator for onboarding
 */
export function SetupProgress({ steps, className }) {
  const completedSteps = steps.filter((s) => s.completed).length
  const progressPercent = (completedSteps / steps.length) * 100

  return (
    <div className={cn('wsms-space-y-4', className)}>
      {/* Progress bar */}
      <div className="wsms-space-y-2">
        <div className="wsms-flex wsms-items-center wsms-justify-between">
          <span className="wsms-text-[12px] wsms-font-medium wsms-text-foreground">
            Setup Progress
          </span>
          <span className="wsms-text-[12px] wsms-text-muted-foreground">
            {completedSteps} of {steps.length} complete
          </span>
        </div>
        <div className="wsms-h-2 wsms-rounded-full wsms-bg-muted wsms-overflow-hidden">
          <div
            className="wsms-h-full wsms-rounded-full wsms-bg-primary wsms-transition-all wsms-duration-500 wsms-ease-out"
            style={{ width: `${progressPercent}%` }}
          />
        </div>
      </div>

      {/* Steps list */}
      <div className="wsms-space-y-2">
        {steps.map((step, index) => (
          <button
            key={index}
            onClick={step.onClick}
            disabled={step.completed}
            className={cn(
              'wsms-flex wsms-items-center wsms-gap-3 wsms-w-full wsms-p-3 wsms-rounded-lg wsms-border wsms-text-left wsms-transition-all',
              step.completed
                ? 'wsms-bg-success/5 wsms-border-success/20 wsms-cursor-default'
                : 'wsms-bg-card wsms-border-border hover:wsms-border-primary/50 hover:wsms-bg-primary/5 wsms-cursor-pointer'
            )}
          >
            <div
              className={cn(
                'wsms-flex wsms-h-6 wsms-w-6 wsms-items-center wsms-justify-center wsms-rounded-full wsms-text-[11px] wsms-font-semibold',
                step.completed
                  ? 'wsms-bg-success wsms-text-white'
                  : 'wsms-bg-muted wsms-text-muted-foreground'
              )}
            >
              {step.completed ? <CheckCircle2 className="wsms-h-3.5 wsms-w-3.5" /> : index + 1}
            </div>
            <div className="wsms-flex-1 wsms-min-w-0">
              <p
                className={cn(
                  'wsms-text-[13px] wsms-font-medium',
                  step.completed ? 'wsms-text-success' : 'wsms-text-foreground'
                )}
              >
                {step.title}
              </p>
              {step.description && (
                <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-truncate">
                  {step.description}
                </p>
              )}
            </div>
            {!step.completed && (
              <ArrowRight className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
            )}
          </button>
        ))}
      </div>
    </div>
  )
}

/**
 * EmptyStateAction - Enhanced empty state with illustration and CTA
 */
export function EmptyStateAction({
  icon: Icon,
  title,
  description,
  action,
  actionLabel,
  secondaryAction,
  secondaryLabel,
  className,
}) {
  return (
    <div
      className={cn(
        'wsms-flex wsms-flex-col wsms-items-center wsms-justify-center wsms-py-12 wsms-px-6 wsms-text-center',
        className
      )}
    >
      {/* Decorative background circles */}
      <div className="wsms-relative wsms-mb-6">
        <div className="wsms-absolute wsms-inset-0 wsms-flex wsms-items-center wsms-justify-center">
          <div className="wsms-w-24 wsms-h-24 wsms-rounded-full wsms-bg-primary/5" />
        </div>
        <div className="wsms-absolute wsms-inset-0 wsms-flex wsms-items-center wsms-justify-center">
          <div className="wsms-w-16 wsms-h-16 wsms-rounded-full wsms-bg-primary/10" />
        </div>
        <div className="wsms-relative wsms-flex wsms-h-12 wsms-w-12 wsms-items-center wsms-justify-center wsms-rounded-full wsms-bg-primary/20">
          <Icon className="wsms-h-6 wsms-w-6 wsms-text-primary" strokeWidth={1.5} />
        </div>
      </div>

      <h3 className="wsms-text-[15px] wsms-font-semibold wsms-text-foreground wsms-mb-2">{title}</h3>
      <p className="wsms-text-[13px] wsms-text-muted-foreground wsms-max-w-sm wsms-mb-6">
        {description}
      </p>

      <div className="wsms-flex wsms-items-center wsms-gap-3">
        {action && (
          <Button onClick={action} size="sm">
            <Sparkles className="wsms-h-4 wsms-w-4 wsms-mr-2" />
            {actionLabel}
          </Button>
        )}
        {secondaryAction && (
          <Button variant="outline" size="sm" onClick={secondaryAction}>
            {secondaryLabel}
          </Button>
        )}
      </div>
    </div>
  )
}

/**
 * HelpLink - Inline help link to documentation
 */
export function HelpLink({ href, children, className }) {
  return (
    <a
      href={href}
      target="_blank"
      rel="noopener noreferrer"
      className={cn(
        'wsms-inline-flex wsms-items-center wsms-gap-1 wsms-text-[11px] wsms-text-primary hover:wsms-underline',
        className
      )}
    >
      <BookOpen className="wsms-h-3 wsms-w-3" />
      {children}
      <ExternalLink className="wsms-h-2.5 wsms-w-2.5" />
    </a>
  )
}

/**
 * FeatureHighlight - Highlight a new or important feature
 */
export function FeatureHighlight({ title, description, isNew, className }) {
  return (
    <div
      className={cn(
        'wsms-flex wsms-items-start wsms-gap-3 wsms-p-4 wsms-rounded-lg wsms-border wsms-border-dashed wsms-border-primary/30 wsms-bg-gradient-to-br wsms-from-primary/5 wsms-to-transparent',
        className
      )}
    >
      <Sparkles className="wsms-h-5 wsms-w-5 wsms-text-primary wsms-shrink-0" />
      <div>
        <div className="wsms-flex wsms-items-center wsms-gap-2 wsms-mb-1">
          <span className="wsms-text-[13px] wsms-font-semibold wsms-text-foreground">{title}</span>
          {isNew && (
            <span className="wsms-px-1.5 wsms-py-0.5 wsms-text-[9px] wsms-font-bold wsms-uppercase wsms-rounded wsms-bg-primary wsms-text-primary-foreground">
              New
            </span>
          )}
        </div>
        <p className="wsms-text-[12px] wsms-text-muted-foreground">{description}</p>
      </div>
    </div>
  )
}

/**
 * CollapsibleSection - Expandable section for progressive disclosure
 */
export function CollapsibleSection({
  title,
  description,
  children,
  defaultOpen = false,
  className,
}) {
  const [isOpen, setIsOpen] = React.useState(defaultOpen)

  return (
    <div className={cn('wsms-border wsms-border-border wsms-rounded-lg', className)}>
      <button
        onClick={() => setIsOpen(!isOpen)}
        className={cn(
          'wsms-flex wsms-items-center wsms-justify-between wsms-w-full wsms-px-4 wsms-py-3 wsms-bg-muted/30 hover:wsms-bg-muted/50 wsms-transition-colors wsms-text-left',
          !isOpen && 'wsms-rounded-lg'
        )}
      >
        <div>
          <span className="wsms-text-[13px] wsms-font-medium wsms-text-foreground">{title}</span>
          {description && (
            <p className="wsms-text-[11px] wsms-text-muted-foreground wsms-mt-0.5">{description}</p>
          )}
        </div>
        {isOpen ? (
          <ChevronUp className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
        ) : (
          <ChevronDown className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-shrink-0" />
        )}
      </button>
      {isOpen && <div className="wsms-p-4 wsms-border-t wsms-border-border">{children}</div>}
    </div>
  )
}

/**
 * QuickHelp - Inline help tooltip trigger
 */
export function QuickHelp({ children, className }) {
  const [isOpen, setIsOpen] = React.useState(false)

  return (
    <span className="wsms-relative wsms-inline-flex">
      <button
        onClick={() => setIsOpen(!isOpen)}
        className={cn(
          'wsms-inline-flex wsms-items-center wsms-justify-center wsms-h-4 wsms-w-4 wsms-rounded-full wsms-text-muted-foreground hover:wsms-text-foreground hover:wsms-bg-muted wsms-transition-colors',
          className
        )}
      >
        <HelpCircle className="wsms-h-3.5 wsms-w-3.5" />
      </button>
      {isOpen && (
        <>
          <div
            className="wsms-fixed wsms-inset-0 wsms-z-40"
            onClick={() => setIsOpen(false)}
          />
          <div className="wsms-absolute wsms-left-1/2 wsms--translate-x-1/2 wsms-bottom-full wsms-mb-2 wsms-z-50 wsms-w-64 wsms-p-3 wsms-rounded-lg wsms-bg-popover wsms-border wsms-border-border wsms-shadow-lg wsms-text-[12px] wsms-text-popover-foreground wsms-animate-in wsms-fade-in wsms-zoom-in-95">
            {children}
            <div className="wsms-absolute wsms-left-1/2 wsms--translate-x-1/2 wsms-top-full wsms-border-8 wsms-border-transparent wsms-border-t-popover" />
          </div>
        </>
      )}
    </span>
  )
}

/**
 * SectionDivider - Visual separator with label
 */
export function SectionDivider({ children, className }) {
  return (
    <div className={cn('wsms-section-divider', className)}>
      {children}
    </div>
  )
}

/**
 * StatusIndicator - Live status dot with label
 */
export function StatusIndicator({ status, label, className }) {
  const statusStyles = {
    connected: 'wsms-bg-success',
    disconnected: 'wsms-bg-destructive',
    pending: 'wsms-bg-amber-500',
    unknown: 'wsms-bg-muted-foreground',
  }

  return (
    <div className={cn('wsms-flex wsms-items-center wsms-gap-2', className)}>
      <span
        className={cn(
          'wsms-h-2 wsms-w-2 wsms-rounded-full',
          statusStyles[status] || statusStyles.unknown
        )}
      />
      <span className="wsms-text-[12px] wsms-text-muted-foreground">{label}</span>
    </div>
  )
}

/**
 * ValidationMessage - Inline validation feedback
 */
export function ValidationMessage({ type = 'error', children, className }) {
  const styles = {
    error: 'wsms-text-destructive',
    warning: 'wsms-text-amber-600 dark:wsms-text-amber-400',
    success: 'wsms-text-success',
    info: 'wsms-text-blue-600 dark:wsms-text-blue-400',
  }

  const icons = {
    error: AlertTriangle,
    warning: AlertTriangle,
    success: CheckCircle2,
    info: Info,
  }

  const Icon = icons[type] || icons.error

  return (
    <p className={cn('wsms-flex wsms-items-center wsms-gap-1.5 wsms-text-[11px]', styles[type], className)}>
      <Icon className="wsms-h-3 wsms-w-3" />
      {children}
    </p>
  )
}
