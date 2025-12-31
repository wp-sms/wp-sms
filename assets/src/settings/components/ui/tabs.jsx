import * as React from 'react'
import * as TabsPrimitive from '@radix-ui/react-tabs'
import { cn } from '@/lib/utils'

const Tabs = TabsPrimitive.Root

const TabsList = React.forwardRef(({ className, ...props }, ref) => (
  <TabsPrimitive.List
    ref={ref}
    className={cn(
      'wsms-inline-flex wsms-h-10 wsms-items-center wsms-justify-center wsms-rounded-md wsms-bg-muted wsms-p-1 wsms-text-muted-foreground',
      className
    )}
    {...props}
  />
))
TabsList.displayName = TabsPrimitive.List.displayName

const TabsTrigger = React.forwardRef(({ className, ...props }, ref) => (
  <TabsPrimitive.Trigger
    ref={ref}
    className={cn(
      'wsms-inline-flex wsms-items-center wsms-justify-center wsms-whitespace-nowrap wsms-rounded-sm wsms-px-3 wsms-py-1.5 wsms-text-sm wsms-font-medium wsms-ring-offset-background wsms-transition-all focus-visible:wsms-outline-none focus-visible:wsms-ring-2 focus-visible:wsms-ring-ring focus-visible:wsms-ring-offset-2 disabled:wsms-pointer-events-none disabled:wsms-opacity-50 data-[state=active]:wsms-bg-background data-[state=active]:wsms-text-foreground data-[state=active]:wsms-shadow-sm',
      className
    )}
    {...props}
  />
))
TabsTrigger.displayName = TabsPrimitive.Trigger.displayName

const TabsContent = React.forwardRef(({ className, ...props }, ref) => (
  <TabsPrimitive.Content
    ref={ref}
    className={cn(
      'wsms-mt-2 wsms-ring-offset-background focus-visible:wsms-outline-none focus-visible:wsms-ring-2 focus-visible:wsms-ring-ring focus-visible:wsms-ring-offset-2',
      className
    )}
    {...props}
  />
))
TabsContent.displayName = TabsPrimitive.Content.displayName

export { Tabs, TabsList, TabsTrigger, TabsContent }
