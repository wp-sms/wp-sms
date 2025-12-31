import * as React from 'react'
import * as DropdownMenuPrimitive from '@radix-ui/react-dropdown-menu'
import { Check, ChevronRight, Circle } from 'lucide-react'
import { cn } from '@/lib/utils'

const DropdownMenu = DropdownMenuPrimitive.Root
const DropdownMenuTrigger = DropdownMenuPrimitive.Trigger
const DropdownMenuGroup = DropdownMenuPrimitive.Group
const DropdownMenuPortal = DropdownMenuPrimitive.Portal
const DropdownMenuSub = DropdownMenuPrimitive.Sub
const DropdownMenuRadioGroup = DropdownMenuPrimitive.RadioGroup

const DropdownMenuSubTrigger = React.forwardRef(
  ({ className, inset, children, ...props }, ref) => (
    <DropdownMenuPrimitive.SubTrigger
      ref={ref}
      className={cn(
        'wsms-flex wsms-cursor-default wsms-select-none wsms-items-center wsms-rounded-sm wsms-px-2 wsms-py-1.5 wsms-text-sm wsms-outline-none',
        'focus:wsms-bg-accent',
        'data-[state=open]:wsms-bg-accent',
        inset && 'wsms-pl-8',
        className
      )}
      {...props}
    >
      {children}
      <ChevronRight className="wsms-ml-auto wsms-h-4 wsms-w-4" />
    </DropdownMenuPrimitive.SubTrigger>
  )
)
DropdownMenuSubTrigger.displayName = DropdownMenuPrimitive.SubTrigger.displayName

const DropdownMenuSubContent = React.forwardRef(
  ({ className, ...props }, ref) => (
    <DropdownMenuPrimitive.SubContent
      ref={ref}
      className={cn(
        'wsms-z-50 wsms-min-w-[8rem] wsms-overflow-hidden wsms-rounded-md wsms-border wsms-border-border wsms-bg-popover wsms-p-1 wsms-text-popover-foreground wsms-shadow-lg',
        'data-[state=open]:wsms-animate-in data-[state=closed]:wsms-animate-out',
        'data-[state=closed]:wsms-fade-out-0 data-[state=open]:wsms-fade-in-0',
        'data-[state=closed]:wsms-zoom-out-95 data-[state=open]:wsms-zoom-in-95',
        'data-[side=bottom]:wsms-slide-in-from-top-2 data-[side=left]:wsms-slide-in-from-right-2',
        'data-[side=right]:wsms-slide-in-from-left-2 data-[side=top]:wsms-slide-in-from-bottom-2',
        className
      )}
      {...props}
    />
  )
)
DropdownMenuSubContent.displayName = DropdownMenuPrimitive.SubContent.displayName

const DropdownMenuContent = React.forwardRef(
  ({ className, sideOffset = 4, ...props }, ref) => (
    <DropdownMenuPrimitive.Portal>
      <DropdownMenuPrimitive.Content
        ref={ref}
        sideOffset={sideOffset}
        className={cn(
          'wsms-z-[99999] wsms-min-w-[8rem] wsms-overflow-hidden wsms-rounded-md wsms-border wsms-border-border wsms-bg-popover wsms-p-1 wsms-text-popover-foreground wsms-shadow-md',
          'data-[state=open]:wsms-animate-in data-[state=closed]:wsms-animate-out',
          'data-[state=closed]:wsms-fade-out-0 data-[state=open]:wsms-fade-in-0',
          'data-[state=closed]:wsms-zoom-out-95 data-[state=open]:wsms-zoom-in-95',
          'data-[side=bottom]:wsms-slide-in-from-top-2 data-[side=left]:wsms-slide-in-from-right-2',
          'data-[side=right]:wsms-slide-in-from-left-2 data-[side=top]:wsms-slide-in-from-bottom-2',
          className
        )}
        {...props}
      />
    </DropdownMenuPrimitive.Portal>
  )
)
DropdownMenuContent.displayName = DropdownMenuPrimitive.Content.displayName

const DropdownMenuItem = React.forwardRef(
  ({ className, inset, ...props }, ref) => (
    <DropdownMenuPrimitive.Item
      ref={ref}
      className={cn(
        'wsms-relative wsms-flex wsms-cursor-pointer wsms-select-none wsms-items-center wsms-rounded-sm wsms-px-2 wsms-py-1.5 wsms-text-[13px] wsms-outline-none wsms-transition-colors',
        'focus:wsms-bg-accent focus:wsms-text-accent-foreground',
        'data-[disabled]:wsms-pointer-events-none data-[disabled]:wsms-opacity-50',
        inset && 'wsms-pl-8',
        className
      )}
      {...props}
    />
  )
)
DropdownMenuItem.displayName = DropdownMenuPrimitive.Item.displayName

const DropdownMenuCheckboxItem = React.forwardRef(
  ({ className, children, checked, ...props }, ref) => (
    <DropdownMenuPrimitive.CheckboxItem
      ref={ref}
      className={cn(
        'wsms-relative wsms-flex wsms-cursor-pointer wsms-select-none wsms-items-center wsms-rounded-sm wsms-py-1.5 wsms-pl-8 wsms-pr-2 wsms-text-sm wsms-outline-none wsms-transition-colors',
        'focus:wsms-bg-accent focus:wsms-text-accent-foreground',
        'data-[disabled]:wsms-pointer-events-none data-[disabled]:wsms-opacity-50',
        className
      )}
      checked={checked}
      {...props}
    >
      <span className="wsms-absolute wsms-left-2 wsms-flex wsms-h-3.5 wsms-w-3.5 wsms-items-center wsms-justify-center">
        <DropdownMenuPrimitive.ItemIndicator>
          <Check className="wsms-h-4 wsms-w-4" />
        </DropdownMenuPrimitive.ItemIndicator>
      </span>
      {children}
    </DropdownMenuPrimitive.CheckboxItem>
  )
)
DropdownMenuCheckboxItem.displayName = DropdownMenuPrimitive.CheckboxItem.displayName

const DropdownMenuRadioItem = React.forwardRef(
  ({ className, children, ...props }, ref) => (
    <DropdownMenuPrimitive.RadioItem
      ref={ref}
      className={cn(
        'wsms-relative wsms-flex wsms-cursor-pointer wsms-select-none wsms-items-center wsms-rounded-sm wsms-py-1.5 wsms-pl-8 wsms-pr-2 wsms-text-sm wsms-outline-none wsms-transition-colors',
        'focus:wsms-bg-accent focus:wsms-text-accent-foreground',
        'data-[disabled]:wsms-pointer-events-none data-[disabled]:wsms-opacity-50',
        className
      )}
      {...props}
    >
      <span className="wsms-absolute wsms-left-2 wsms-flex wsms-h-3.5 wsms-w-3.5 wsms-items-center wsms-justify-center">
        <DropdownMenuPrimitive.ItemIndicator>
          <Circle className="wsms-h-2 wsms-w-2 wsms-fill-current" />
        </DropdownMenuPrimitive.ItemIndicator>
      </span>
      {children}
    </DropdownMenuPrimitive.RadioItem>
  )
)
DropdownMenuRadioItem.displayName = DropdownMenuPrimitive.RadioItem.displayName

const DropdownMenuLabel = React.forwardRef(
  ({ className, inset, ...props }, ref) => (
    <DropdownMenuPrimitive.Label
      ref={ref}
      className={cn(
        'wsms-px-2 wsms-py-1.5 wsms-text-sm wsms-font-semibold',
        inset && 'wsms-pl-8',
        className
      )}
      {...props}
    />
  )
)
DropdownMenuLabel.displayName = DropdownMenuPrimitive.Label.displayName

const DropdownMenuSeparator = React.forwardRef(
  ({ className, ...props }, ref) => (
    <DropdownMenuPrimitive.Separator
      ref={ref}
      className={cn('wsms--mx-1 wsms-my-1 wsms-h-px wsms-bg-muted', className)}
      {...props}
    />
  )
)
DropdownMenuSeparator.displayName = DropdownMenuPrimitive.Separator.displayName

const DropdownMenuShortcut = ({ className, ...props }) => {
  return (
    <span
      className={cn('wsms-ml-auto wsms-text-xs wsms-tracking-widest wsms-opacity-60', className)}
      {...props}
    />
  )
}
DropdownMenuShortcut.displayName = 'DropdownMenuShortcut'

export {
  DropdownMenu,
  DropdownMenuTrigger,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuCheckboxItem,
  DropdownMenuRadioItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuGroup,
  DropdownMenuPortal,
  DropdownMenuSub,
  DropdownMenuSubContent,
  DropdownMenuSubTrigger,
  DropdownMenuRadioGroup,
}
