import * as React from 'react'
import { Slot } from '@radix-ui/react-slot'
import { cva } from 'class-variance-authority'
import { cn } from '@/lib/utils'

const buttonVariants = cva(
  'wsms-inline-flex wsms-items-center wsms-justify-center wsms-whitespace-nowrap wsms-rounded-md wsms-text-[13px] wsms-font-medium wsms-transition-all focus-visible:wsms-outline-none focus-visible:wsms-ring-2 focus-visible:wsms-ring-primary/20 disabled:wsms-pointer-events-none disabled:wsms-opacity-50',
  {
    variants: {
      variant: {
        default:
          'wsms-bg-primary wsms-text-primary-foreground wsms-shadow-sm hover:wsms-bg-primary/90 active:wsms-scale-[0.98]',
        destructive:
          'wsms-bg-destructive wsms-text-destructive-foreground wsms-shadow-sm hover:wsms-bg-destructive/90',
        outline:
          'wsms-border wsms-border-input wsms-bg-card wsms-shadow-sm hover:wsms-bg-accent/50 hover:wsms-border-primary/50',
        secondary:
          'wsms-bg-secondary wsms-text-secondary-foreground wsms-shadow-sm hover:wsms-bg-secondary/80',
        ghost:
          'hover:wsms-bg-accent',
        link:
          'wsms-text-primary wsms-underline-offset-4 hover:wsms-underline',
      },
      size: {
        default: 'wsms-h-9 wsms-px-4',
        sm: 'wsms-h-8 wsms-px-3 wsms-text-[12px]',
        lg: 'wsms-h-10 wsms-px-5',
        icon: 'wsms-h-9 wsms-w-9',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  }
)

const Button = React.forwardRef(
  ({ className, variant, size, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : 'button'
    return (
      <Comp
        className={cn(buttonVariants({ variant, size, className }))}
        ref={ref}
        {...props}
      />
    )
  }
)
Button.displayName = 'Button'

export { Button, buttonVariants }
