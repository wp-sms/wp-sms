import * as React from 'react'
import { cn } from '@/lib/utils'

const Textarea = React.forwardRef(({ className, ...props }, ref) => {
  return (
    <textarea
      className={cn(
        'wsms-flex wsms-min-h-[80px] wsms-w-full wsms-rounded-md wsms-border wsms-border-input wsms-bg-card wsms-px-3 wsms-py-2 wsms-text-[13px] wsms-shadow-sm placeholder:wsms-text-muted-foreground hover:wsms-border-primary/50 focus:wsms-outline-none focus:wsms-ring-2 focus:wsms-ring-primary/20 focus:wsms-border-primary disabled:wsms-cursor-not-allowed disabled:wsms-opacity-50',
        className
      )}
      ref={ref}
      {...props}
    />
  )
})
Textarea.displayName = 'Textarea'

export { Textarea }
