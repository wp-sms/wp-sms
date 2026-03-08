import * as React from 'react'
import { cva } from 'class-variance-authority'
import { cn } from '@/lib/utils'

const badgeVariants = cva(
  'wsms-inline-flex wsms-items-center wsms-rounded-full wsms-border wsms-px-2.5 wsms-py-0.5 wsms-text-xs wsms-font-semibold wsms-transition-colors focus:wsms-outline-none focus:wsms-ring-2 focus:wsms-ring-ring focus:wsms-ring-offset-2',
  {
    variants: {
      variant: {
        default: 'wsms-border-transparent wsms-bg-primary wsms-text-primary-foreground hover:wsms-bg-primary/80',
        secondary: 'wsms-border-transparent wsms-bg-secondary wsms-text-secondary-foreground hover:wsms-bg-secondary/80',
        destructive: 'wsms-border-transparent wsms-bg-destructive wsms-text-destructive-foreground hover:wsms-bg-destructive/80',
        success: 'wsms-border-transparent wsms-bg-success wsms-text-success-foreground hover:wsms-bg-success/80',
        warning: 'wsms-border-transparent wsms-bg-warning wsms-text-warning-foreground hover:wsms-bg-warning/80',
        outline: 'wsms-text-foreground',
      },
    },
    defaultVariants: {
      variant: 'default',
    },
  }
)

function Badge({ className, variant, ...props }) {
  return (
    <div className={cn(badgeVariants({ variant }), className)} {...props} />
  )
}

export { Badge, badgeVariants }
