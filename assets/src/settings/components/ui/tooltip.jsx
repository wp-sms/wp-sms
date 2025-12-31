import * as React from 'react'
import * as TooltipPrimitive from '@radix-ui/react-tooltip'
import { cn } from '@/lib/utils'

const TooltipProvider = TooltipPrimitive.Provider

const Tooltip = TooltipPrimitive.Root

const TooltipTrigger = TooltipPrimitive.Trigger

const TooltipContent = React.forwardRef(
  ({ className, sideOffset = 4, ...props }, ref) => (
    <TooltipPrimitive.Portal container={document.getElementById('wpsms-settings-root')}>
      <TooltipPrimitive.Content
        ref={ref}
        sideOffset={sideOffset}
        className={cn(
          'wsms-z-50 wsms-overflow-hidden wsms-rounded-md wsms-bg-primary wsms-px-3 wsms-py-1.5 wsms-text-xs wsms-text-primary-foreground wsms-shadow-md',
          'wsms-animate-in wsms-fade-in-0 wsms-zoom-in-95',
          'data-[state=closed]:wsms-animate-out data-[state=closed]:wsms-fade-out-0 data-[state=closed]:wsms-zoom-out-95',
          'data-[side=bottom]:wsms-slide-in-from-top-2 data-[side=left]:wsms-slide-in-from-right-2 data-[side=right]:wsms-slide-in-from-left-2 data-[side=top]:wsms-slide-in-from-bottom-2',
          className
        )}
        {...props}
      />
    </TooltipPrimitive.Portal>
  )
)
TooltipContent.displayName = TooltipPrimitive.Content.displayName

export { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider }
