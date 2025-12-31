import * as React from 'react'
import * as SelectPrimitive from '@radix-ui/react-select'
import { Check, ChevronDown } from 'lucide-react'
import { cn } from '@/lib/utils'

const Select = SelectPrimitive.Root
const SelectGroup = SelectPrimitive.Group
const SelectValue = SelectPrimitive.Value

const SelectTrigger = React.forwardRef(({ className, children, ...props }, ref) => (
  <SelectPrimitive.Trigger
    ref={ref}
    className={cn(
      'wsms-flex wsms-h-9 wsms-w-full wsms-items-center wsms-justify-between wsms-rounded-md wsms-border wsms-border-input wsms-bg-card wsms-px-3 wsms-text-[13px] wsms-shadow-sm hover:wsms-bg-accent/50 focus:wsms-outline-none focus:wsms-ring-2 focus:wsms-ring-primary/20 focus:wsms-border-primary disabled:wsms-cursor-not-allowed disabled:wsms-opacity-50 [&>span]:wsms-line-clamp-1',
      className
    )}
    {...props}
  >
    {children}
    <SelectPrimitive.Icon asChild>
      <ChevronDown className="wsms-h-4 wsms-w-4 wsms-text-muted-foreground wsms-transition-transform" />
    </SelectPrimitive.Icon>
  </SelectPrimitive.Trigger>
))
SelectTrigger.displayName = SelectPrimitive.Trigger.displayName

const SelectContent = React.forwardRef(({ className, children, position = 'popper', ...props }, ref) => (
  <SelectPrimitive.Portal container={document.getElementById('wpsms-settings-root')}>
    <SelectPrimitive.Content
      ref={ref}
      className={cn(
        'wsms-relative wsms-z-50 wsms-max-h-80 wsms-min-w-[8rem] wsms-overflow-hidden wsms-rounded wsms-border wsms-border-border wsms-bg-popover wsms-text-popover-foreground wsms-shadow-md',
        position === 'popper' && 'wsms-translate-y-1',
        className
      )}
      position={position}
      {...props}
    >
      <SelectPrimitive.Viewport
        className={cn(
          'wsms-p-1',
          position === 'popper' && 'wsms-w-full wsms-min-w-[var(--radix-select-trigger-width)]'
        )}
      >
        {children}
      </SelectPrimitive.Viewport>
    </SelectPrimitive.Content>
  </SelectPrimitive.Portal>
))
SelectContent.displayName = SelectPrimitive.Content.displayName

const SelectLabel = React.forwardRef(({ className, ...props }, ref) => (
  <SelectPrimitive.Label
    ref={ref}
    className={cn('wsms-py-1.5 wsms-pl-7 wsms-pr-2 wsms-text-[12px] wsms-font-medium wsms-text-muted-foreground', className)}
    {...props}
  />
))
SelectLabel.displayName = SelectPrimitive.Label.displayName

const SelectItem = React.forwardRef(({ className, children, ...props }, ref) => (
  <SelectPrimitive.Item
    ref={ref}
    className={cn(
      'wsms-relative wsms-flex wsms-w-full wsms-cursor-pointer wsms-select-none wsms-items-center wsms-rounded wsms-py-1.5 wsms-pl-7 wsms-pr-2 wsms-text-[13px] wsms-outline-none focus:wsms-bg-accent data-[disabled]:wsms-pointer-events-none data-[disabled]:wsms-opacity-50',
      className
    )}
    {...props}
  >
    <span className="wsms-absolute wsms-left-2 wsms-flex wsms-h-3.5 wsms-w-3.5 wsms-items-center wsms-justify-center">
      <SelectPrimitive.ItemIndicator>
        <Check className="wsms-h-3 wsms-w-3 wsms-text-primary" />
      </SelectPrimitive.ItemIndicator>
    </span>
    <SelectPrimitive.ItemText>{children}</SelectPrimitive.ItemText>
  </SelectPrimitive.Item>
))
SelectItem.displayName = SelectPrimitive.Item.displayName

const SelectSeparator = React.forwardRef(({ className, ...props }, ref) => (
  <SelectPrimitive.Separator
    ref={ref}
    className={cn('wsms-my-1 wsms-h-px wsms-bg-border', className)}
    {...props}
  />
))
SelectSeparator.displayName = SelectPrimitive.Separator.displayName

export {
  Select,
  SelectGroup,
  SelectValue,
  SelectTrigger,
  SelectContent,
  SelectLabel,
  SelectItem,
  SelectSeparator,
}
