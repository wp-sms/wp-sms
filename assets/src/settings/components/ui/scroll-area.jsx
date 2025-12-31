import * as React from 'react'
import * as ScrollAreaPrimitive from '@radix-ui/react-scroll-area'
import { cn } from '@/lib/utils'

const ScrollArea = React.forwardRef(({ className, children, ...props }, ref) => (
  <ScrollAreaPrimitive.Root
    ref={ref}
    className={cn('wsms-relative wsms-overflow-hidden', className)}
    {...props}
  >
    <ScrollAreaPrimitive.Viewport className="wsms-h-full wsms-w-full wsms-rounded-[inherit]">
      {children}
    </ScrollAreaPrimitive.Viewport>
    <ScrollBar />
    <ScrollAreaPrimitive.Corner />
  </ScrollAreaPrimitive.Root>
))
ScrollArea.displayName = ScrollAreaPrimitive.Root.displayName

const ScrollBar = React.forwardRef(({ className, orientation = 'vertical', ...props }, ref) => (
  <ScrollAreaPrimitive.ScrollAreaScrollbar
    ref={ref}
    orientation={orientation}
    className={cn(
      'wsms-flex wsms-touch-none wsms-select-none wsms-transition-colors',
      orientation === 'vertical' &&
        'wsms-h-full wsms-w-2.5 wsms-border-l wsms-border-l-transparent wsms-p-[1px]',
      orientation === 'horizontal' &&
        'wsms-h-2.5 wsms-flex-col wsms-border-t wsms-border-t-transparent wsms-p-[1px]',
      className
    )}
    {...props}
  >
    <ScrollAreaPrimitive.ScrollAreaThumb className="wsms-relative wsms-flex-1 wsms-rounded-full wsms-bg-border" />
  </ScrollAreaPrimitive.ScrollAreaScrollbar>
))
ScrollBar.displayName = ScrollAreaPrimitive.ScrollAreaScrollbar.displayName

export { ScrollArea, ScrollBar }
