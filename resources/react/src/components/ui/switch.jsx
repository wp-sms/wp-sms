import * as React from 'react'
import * as SwitchPrimitives from '@radix-ui/react-switch'
import { cn } from '@/lib/utils'

const Switch = React.forwardRef(({ className, ...props }, ref) => (
  <SwitchPrimitives.Root
    className={cn(
      'wsms-peer wsms-inline-flex wsms-h-6 wsms-w-11 wsms-shrink-0 wsms-cursor-pointer wsms-items-center wsms-rounded-full wsms-border-2 wsms-border-transparent wsms-transition-colors focus-visible:wsms-outline-none focus-visible:wsms-ring-2 focus-visible:wsms-ring-ring focus-visible:wsms-ring-offset-2 focus-visible:wsms-ring-offset-background disabled:wsms-cursor-not-allowed disabled:wsms-opacity-50 data-[state=checked]:wsms-bg-primary data-[state=unchecked]:wsms-bg-input',
      className
    )}
    {...props}
    ref={ref}
  >
    <SwitchPrimitives.Thumb
      className={cn(
        'wsms-pointer-events-none wsms-block wsms-h-5 wsms-w-5 wsms-rounded-full wsms-bg-background wsms-shadow-lg wsms-ring-0 wsms-transition-transform data-[state=checked]:wsms-translate-x-5 data-[state=unchecked]:wsms-translate-x-0'
      )}
    />
  </SwitchPrimitives.Root>
))
Switch.displayName = SwitchPrimitives.Root.displayName

export { Switch }
