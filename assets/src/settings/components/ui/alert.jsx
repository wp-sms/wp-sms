import * as React from 'react'
import { cva } from 'class-variance-authority'
import { AlertCircle, CheckCircle2, Info, AlertTriangle } from 'lucide-react'
import { cn } from '@/lib/utils'

const alertVariants = cva(
  'wsms-relative wsms-w-full wsms-rounded-lg wsms-border wsms-p-4 [&>svg~*]:wsms-pl-7 [&>svg+div]:wsms-translate-y-[-3px] [&>svg]:wsms-absolute [&>svg]:wsms-left-4 [&>svg]:wsms-top-4 [&>svg]:wsms-text-foreground',
  {
    variants: {
      variant: {
        default: 'wsms-bg-background wsms-text-foreground',
        destructive:
          'wsms-border-destructive/50 wsms-text-destructive [&>svg]:wsms-text-destructive wsms-bg-destructive/10',
        success:
          'wsms-border-success/50 wsms-text-success [&>svg]:wsms-text-success wsms-bg-success/10',
        warning:
          'wsms-border-warning/50 wsms-text-warning [&>svg]:wsms-text-warning wsms-bg-warning/10',
        info: 'wsms-border-primary/50 wsms-text-primary [&>svg]:wsms-text-primary wsms-bg-primary/10',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  }
)

const alertIcons = {
  default: Info,
  destructive: AlertCircle,
  success: CheckCircle2,
  warning: AlertTriangle,
  info: Info,
}

const Alert = React.forwardRef(
  ({ className, variant = 'default', showIcon = true, children, ...props }, ref) => {
    const Icon = alertIcons[variant]

    return (
      <div
        ref={ref}
        role="alert"
        className={cn(alertVariants({ variant }), className)}
        {...props}
      >
        {showIcon && <Icon className="wsms-h-4 wsms-w-4" />}
        {children}
      </div>
    )
  }
)
Alert.displayName = 'Alert'

const AlertTitle = React.forwardRef(({ className, ...props }, ref) => (
  <h5
    ref={ref}
    className={cn(
      'wsms-mb-1 wsms-font-medium wsms-leading-none wsms-tracking-tight',
      className
    )}
    {...props}
  />
))
AlertTitle.displayName = 'AlertTitle'

const AlertDescription = React.forwardRef(({ className, ...props }, ref) => (
  <div
    ref={ref}
    className={cn('wsms-text-sm [&_p]:wsms-leading-relaxed', className)}
    {...props}
  />
))
AlertDescription.displayName = 'AlertDescription'

export { Alert, AlertTitle, AlertDescription }
