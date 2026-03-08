import * as React from 'react'
import * as CheckboxPrimitive from '@radix-ui/react-checkbox'
import { Check, Minus } from 'lucide-react'
import { cn } from '@/lib/utils'

const Checkbox = React.forwardRef(
  ({ className, checked, indeterminate, ...props }, ref) => (
    <CheckboxPrimitive.Root
      ref={ref}
      className={cn(
        'wsms-peer wsms-h-4 wsms-w-4 wsms-shrink-0 wsms-rounded wsms-border wsms-border-input wsms-bg-card wsms-shadow-sm',
        'focus-visible:wsms-outline-none focus-visible:wsms-ring-2 focus-visible:wsms-ring-primary/20',
        'disabled:wsms-cursor-not-allowed disabled:wsms-opacity-50',
        'data-[state=checked]:wsms-bg-primary data-[state=checked]:wsms-border-primary data-[state=checked]:wsms-text-primary-foreground',
        'data-[state=indeterminate]:wsms-bg-primary data-[state=indeterminate]:wsms-border-primary data-[state=indeterminate]:wsms-text-primary-foreground',
        'wsms-transition-all hover:wsms-border-primary/50',
        className
      )}
      checked={indeterminate ? 'indeterminate' : checked}
      {...props}
    >
      <CheckboxPrimitive.Indicator
        className={cn('wsms-flex wsms-items-center wsms-justify-center wsms-text-current')}
      >
        {indeterminate ? (
          <Minus className="wsms-h-3 wsms-w-3" strokeWidth={3} />
        ) : (
          <Check className="wsms-h-3 wsms-w-3" strokeWidth={3} />
        )}
      </CheckboxPrimitive.Indicator>
    </CheckboxPrimitive.Root>
  )
)
Checkbox.displayName = CheckboxPrimitive.Root.displayName

export { Checkbox }
