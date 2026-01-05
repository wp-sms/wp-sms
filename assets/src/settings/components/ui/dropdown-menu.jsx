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

const dropdownContentStyles = {
  backgroundColor: '#ffffff',
  border: '1px solid #e5e7eb',
  boxShadow: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1)',
  borderRadius: '8px',
  padding: '6px',
  minWidth: '140px',
  zIndex: 999999,
  fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
  fontSize: '13px',
  lineHeight: '1.5',
  color: '#374151',
  outline: 'none',
}

const DropdownMenuContent = React.forwardRef(
  ({ className, sideOffset = 4, style, ...props }, ref) => (
    <DropdownMenuPrimitive.Portal container={document.getElementById('wpsms-settings-root')}>
      <DropdownMenuPrimitive.Content
        ref={ref}
        sideOffset={sideOffset}
        className={cn(
          'wsms-z-[99999] wsms-min-w-[140px] wsms-overflow-hidden wsms-rounded-lg wsms-p-1.5 wsms-shadow-lg',
          className
        )}
        style={{ ...dropdownContentStyles, ...style }}
        {...props}
      />
    </DropdownMenuPrimitive.Portal>
  )
)
DropdownMenuContent.displayName = DropdownMenuPrimitive.Content.displayName

const dropdownItemBaseStyles = {
  display: 'flex',
  alignItems: 'center',
  gap: '8px',
  padding: '8px 10px',
  borderRadius: '6px',
  fontSize: '13px',
  fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
  lineHeight: '1.5',
  color: '#374151',
  cursor: 'pointer',
  transition: 'background-color 0.15s ease',
  outline: 'none',
  border: 'none',
  width: '100%',
  textAlign: 'left',
  textDecoration: 'none',
}

const DropdownMenuItem = React.forwardRef(
  ({ className, inset, style, ...props }, ref) => {
    const [isHovered, setIsHovered] = React.useState(false)
    return (
      <DropdownMenuPrimitive.Item
        ref={ref}
        className={cn(
          'wsms-relative wsms-flex wsms-cursor-pointer wsms-select-none wsms-items-center wsms-gap-2 wsms-rounded-md wsms-outline-none wsms-transition-colors',
          'data-[disabled]:wsms-pointer-events-none data-[disabled]:wsms-opacity-50',
          inset && 'wsms-pl-8',
          className
        )}
        style={{
          ...dropdownItemBaseStyles,
          backgroundColor: isHovered ? '#f3f4f6' : 'transparent',
          ...style,
        }}
        onMouseEnter={() => setIsHovered(true)}
        onMouseLeave={() => setIsHovered(false)}
        {...props}
      />
    )
  }
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
