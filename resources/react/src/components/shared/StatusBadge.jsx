import * as React from 'react'
import { cva } from 'class-variance-authority'
import { CheckCircle, XCircle, Clock, AlertCircle, Send, Ban } from 'lucide-react'
import { cn } from '@/lib/utils'

const statusBadgeVariants = cva(
  'wsms-inline-flex wsms-items-center wsms-gap-1.5 wsms-rounded-full wsms-px-2.5 wsms-py-0.5 wsms-text-[11px] wsms-font-medium wsms-transition-colors',
  {
    variants: {
      variant: {
        success: 'wsms-bg-emerald-500/10 wsms-text-emerald-700 dark:wsms-text-emerald-400',
        failed: 'wsms-bg-red-500/10 wsms-text-red-700 dark:wsms-text-red-400',
        pending: 'wsms-bg-amber-500/10 wsms-text-amber-700 dark:wsms-text-amber-400',
        sent: 'wsms-bg-blue-500/10 wsms-text-blue-700 dark:wsms-text-blue-400',
        inactive: 'wsms-bg-gray-500/10 wsms-text-gray-700 dark:wsms-text-gray-400',
        active: 'wsms-bg-emerald-500/10 wsms-text-emerald-700 dark:wsms-text-emerald-400',
        warning: 'wsms-bg-orange-500/10 wsms-text-orange-700 dark:wsms-text-orange-400',
        default: 'wsms-bg-gray-500/10 wsms-text-gray-700 dark:wsms-text-gray-400',
      },
      size: {
        sm: 'wsms-text-[10px] wsms-px-2 wsms-py-0.5',
        default: 'wsms-text-[11px] wsms-px-2.5 wsms-py-0.5',
        lg: 'wsms-text-[12px] wsms-px-3 wsms-py-1',
      },
    },
    defaultVariants: {
      variant: 'pending',
      size: 'default',
    },
  }
)

const statusIcons = {
  success: CheckCircle,
  failed: XCircle,
  pending: Clock,
  sent: Send,
  inactive: Ban,
  active: CheckCircle,
  warning: AlertCircle,
  default: AlertCircle,
}

const StatusBadge = React.forwardRef(
  ({ className, variant, size, showIcon = true, children, ...props }, ref) => {
    const Icon = statusIcons[variant] || Clock

    return (
      <span
        ref={ref}
        className={cn(statusBadgeVariants({ variant, size }), className)}
        {...props}
      >
        {showIcon && <Icon className="wsms-h-3 wsms-w-3" />}
        {children}
      </span>
    )
  }
)
StatusBadge.displayName = 'StatusBadge'

export { StatusBadge, statusBadgeVariants }
