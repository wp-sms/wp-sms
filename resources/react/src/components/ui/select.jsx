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
      'wsms-flex wsms-h-9 wsms-w-full wsms-items-center wsms-justify-between wsms-rounded-md wsms-border wsms-border-input wsms-bg-card wsms-px-3 wsms-text-[13px] wsms-text-foreground wsms-shadow-sm hover:wsms-bg-accent/50 focus:wsms-outline-none focus:wsms-ring-2 focus:wsms-ring-primary/20 focus:wsms-border-primary disabled:wsms-cursor-not-allowed disabled:wsms-opacity-50 [&>span]:wsms-line-clamp-1',
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

const selectContentStyles = {
  zIndex: 9999999,
  backgroundColor: 'hsl(var(--popover))',
  border: '1px solid hsl(var(--border))',
  borderRadius: '6px',
  boxShadow: '0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1)',
  fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
  fontSize: '13px',
  color: 'hsl(var(--popover-foreground))',
  maxHeight: '300px',
  overflow: 'hidden',
}

const SelectContent = React.forwardRef(({ className, children, position = 'popper', style, ...props }, ref) => (
  <SelectPrimitive.Portal container={document.getElementById('wpsms-settings-root')}>
    <SelectPrimitive.Content
      ref={ref}
      className={cn(
        'wsms-relative wsms-min-w-[8rem] wsms-rounded wsms-shadow-md',
        position === 'popper' && 'wsms-translate-y-1',
        className
      )}
      position={position}
      style={{ ...selectContentStyles, ...style }}
      {...props}
    >
      <SelectPrimitive.Viewport
        className={cn(
          'wsms-p-1',
          position === 'popper' && 'wsms-w-full wsms-min-w-[var(--radix-select-trigger-width)]'
        )}
        style={{ maxHeight: '280px', overflowY: 'auto' }}
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

const selectItemBaseStyles = {
  display: 'flex',
  alignItems: 'center',
  width: '100%',
  padding: '6px 8px 6px 28px',
  borderRadius: '4px',
  fontSize: '13px',
  fontFamily: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
  color: 'hsl(var(--popover-foreground))',
  cursor: 'pointer',
  outline: 'none',
  position: 'relative',
  transition: 'background-color 0.15s ease',
}

const SelectItem = React.forwardRef(({ className, children, style, ...props }, ref) => {
  const [isHovered, setIsHovered] = React.useState(false)
  return (
    <SelectPrimitive.Item
      ref={ref}
      className={cn(
        'wsms-relative wsms-flex wsms-w-full wsms-cursor-pointer wsms-select-none wsms-items-center wsms-rounded wsms-py-1.5 wsms-pl-7 wsms-pr-2 wsms-text-[13px] wsms-outline-none data-[disabled]:wsms-pointer-events-none data-[disabled]:wsms-opacity-50',
        className
      )}
      style={{
        ...selectItemBaseStyles,
        backgroundColor: isHovered ? 'hsl(var(--accent))' : 'transparent',
        ...style,
      }}
      onMouseEnter={() => setIsHovered(true)}
      onMouseLeave={() => setIsHovered(false)}
      {...props}
    >
      <span style={{ position: 'absolute', left: '8px', display: 'flex', alignItems: 'center', justifyContent: 'center', width: '14px', height: '14px' }}>
        <SelectPrimitive.ItemIndicator>
          <Check style={{ width: '12px', height: '12px', color: '#f97316' }} />
        </SelectPrimitive.ItemIndicator>
      </span>
      <SelectPrimitive.ItemText>{children}</SelectPrimitive.ItemText>
    </SelectPrimitive.Item>
  )
})
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
