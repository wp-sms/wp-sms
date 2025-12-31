import * as React from 'react'
import { Slot } from '@radix-ui/react-slot'
import { cva } from 'class-variance-authority'
import { PanelLeft } from 'lucide-react'
import { useIsMobile } from '@/hooks/use-mobile'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { Separator } from '@/components/ui/separator'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'

const SIDEBAR_COOKIE_NAME = 'sidebar_state'
const SIDEBAR_COOKIE_MAX_AGE = 60 * 60 * 24 * 7
const SIDEBAR_WIDTH = '16rem'
const SIDEBAR_WIDTH_MOBILE = '18rem'
const SIDEBAR_WIDTH_ICON = '3rem'
const SIDEBAR_KEYBOARD_SHORTCUT = 'b'

const SidebarContext = React.createContext(null)

function useSidebar() {
  const context = React.useContext(SidebarContext)
  if (!context) {
    throw new Error('useSidebar must be used within a SidebarProvider.')
  }
  return context
}

const SidebarProvider = React.forwardRef(
  (
    {
      defaultOpen = true,
      open: openProp,
      onOpenChange: setOpenProp,
      className,
      style,
      children,
      ...props
    },
    ref
  ) => {
    const isMobile = useIsMobile()
    const [openMobile, setOpenMobile] = React.useState(false)
    const [_open, _setOpen] = React.useState(defaultOpen)
    const open = openProp ?? _open

    const setOpen = React.useCallback(
      (value) => {
        const openState = typeof value === 'function' ? value(open) : value
        if (setOpenProp) {
          setOpenProp(openState)
        } else {
          _setOpen(openState)
        }
        document.cookie = `${SIDEBAR_COOKIE_NAME}=${openState}; path=/; max-age=${SIDEBAR_COOKIE_MAX_AGE}`
      },
      [setOpenProp, open]
    )

    const toggleSidebar = React.useCallback(() => {
      return isMobile ? setOpenMobile((open) => !open) : setOpen((open) => !open)
    }, [isMobile, setOpen, setOpenMobile])

    React.useEffect(() => {
      const handleKeyDown = (event) => {
        if (
          event.key === SIDEBAR_KEYBOARD_SHORTCUT &&
          (event.metaKey || event.ctrlKey)
        ) {
          event.preventDefault()
          toggleSidebar()
        }
      }
      window.addEventListener('keydown', handleKeyDown)
      return () => window.removeEventListener('keydown', handleKeyDown)
    }, [toggleSidebar])

    const state = open ? 'expanded' : 'collapsed'

    const contextValue = React.useMemo(
      () => ({
        state,
        open,
        setOpen,
        isMobile,
        openMobile,
        setOpenMobile,
        toggleSidebar,
      }),
      [state, open, setOpen, isMobile, openMobile, setOpenMobile, toggleSidebar]
    )

    return (
      <SidebarContext.Provider value={contextValue}>
        <TooltipProvider delayDuration={0}>
          <div
            ref={ref}
            style={{
              '--sidebar-width': SIDEBAR_WIDTH,
              '--sidebar-width-icon': SIDEBAR_WIDTH_ICON,
              ...style,
            }}
            className={cn(
              'wsms-group/sidebar-wrapper wsms-flex wsms-min-h-svh wsms-w-full has-[[data-variant=inset]]:wsms-bg-sidebar',
              className
            )}
            {...props}
          >
            {children}
          </div>
        </TooltipProvider>
      </SidebarContext.Provider>
    )
  }
)
SidebarProvider.displayName = 'SidebarProvider'

const Sidebar = React.forwardRef(
  (
    {
      side = 'left',
      variant = 'sidebar',
      collapsible = 'offcanvas',
      className,
      children,
      ...props
    },
    ref
  ) => {
    const { isMobile, state, openMobile, setOpenMobile } = useSidebar()

    if (collapsible === 'none') {
      return (
        <div
          ref={ref}
          className={cn(
            'wsms-flex wsms-h-full wsms-w-[--sidebar-width] wsms-flex-col wsms-bg-sidebar wsms-text-sidebar-foreground',
            className
          )}
          {...props}
        >
          {children}
        </div>
      )
    }

    if (isMobile) {
      return (
        <>
          {openMobile && (
            <div
              className="wsms-fixed wsms-inset-0 wsms-z-40 wsms-bg-black/50"
              onClick={() => setOpenMobile(false)}
            />
          )}
          <div
            ref={ref}
            data-state={openMobile ? 'open' : 'closed'}
            data-side={side}
            className={cn(
              'wsms-fixed wsms-inset-y-0 wsms-z-50 wsms-flex wsms-h-svh wsms-w-[--sidebar-width] wsms-flex-col wsms-bg-sidebar wsms-text-sidebar-foreground wsms-transition-transform wsms-duration-200',
              side === 'left'
                ? 'wsms-left-0 data-[state=closed]:wsms--translate-x-full'
                : 'wsms-right-0 data-[state=closed]:wsms-translate-x-full',
              className
            )}
            style={{ '--sidebar-width': SIDEBAR_WIDTH_MOBILE }}
            {...props}
          >
            {children}
          </div>
        </>
      )
    }

    return (
      <div
        ref={ref}
        data-state={state}
        data-collapsible={state === 'collapsed' ? collapsible : ''}
        data-variant={variant}
        data-side={side}
        className="wsms-group wsms-peer wsms-hidden md:wsms-block wsms-text-sidebar-foreground"
      >
        <div
          className={cn(
            'wsms-relative wsms-h-svh wsms-w-[--sidebar-width] wsms-bg-transparent wsms-transition-[width] wsms-duration-200 wsms-ease-linear',
            'group-data-[collapsible=offcanvas]:wsms-w-0',
            'group-data-[side=right]:wsms-rotate-180',
            variant === 'floating' || variant === 'inset'
              ? 'group-data-[collapsible=icon]:wsms-w-[calc(var(--sidebar-width-icon)_+_theme(spacing.4))]'
              : 'group-data-[collapsible=icon]:wsms-w-[--sidebar-width-icon]'
          )}
        />
        <div
          className={cn(
            'wsms-fixed wsms-inset-y-0 wsms-z-10 wsms-hidden wsms-h-svh wsms-w-[--sidebar-width] wsms-transition-[left,right,width] wsms-duration-200 wsms-ease-linear md:wsms-flex',
            side === 'left'
              ? 'wsms-left-0 group-data-[collapsible=offcanvas]:wsms-left-[calc(var(--sidebar-width)*-1)]'
              : 'wsms-right-0 group-data-[collapsible=offcanvas]:wsms-right-[calc(var(--sidebar-width)*-1)]',
            variant === 'floating' || variant === 'inset'
              ? 'wsms-p-2 group-data-[collapsible=icon]:wsms-w-[calc(var(--sidebar-width-icon)_+_theme(spacing.4)_+2px)]'
              : 'group-data-[collapsible=icon]:wsms-w-[--sidebar-width-icon] group-data-[side=left]:wsms-border-r group-data-[side=right]:wsms-border-l',
            className
          )}
          {...props}
        >
          <div
            data-sidebar="sidebar"
            className={cn(
              'wsms-flex wsms-h-full wsms-w-full wsms-flex-col wsms-bg-sidebar',
              variant === 'floating' &&
                'wsms-overflow-hidden wsms-rounded-lg wsms-border wsms-border-sidebar-border wsms-shadow',
              variant === 'inset' && 'group-data-[side=left]:wsms-border-r'
            )}
          >
            {children}
          </div>
        </div>
      </div>
    )
  }
)
Sidebar.displayName = 'Sidebar'

const SidebarTrigger = React.forwardRef(({ className, onClick, ...props }, ref) => {
  const { toggleSidebar } = useSidebar()

  return (
    <Button
      ref={ref}
      data-sidebar="trigger"
      variant="ghost"
      size="icon"
      className={cn('wsms-h-7 wsms-w-7', className)}
      onClick={(event) => {
        onClick?.(event)
        toggleSidebar()
      }}
      {...props}
    >
      <PanelLeft />
      <span className="wsms-sr-only">Toggle Sidebar</span>
    </Button>
  )
})
SidebarTrigger.displayName = 'SidebarTrigger'

const SidebarRail = React.forwardRef(({ className, ...props }, ref) => {
  const { toggleSidebar } = useSidebar()

  return (
    <button
      ref={ref}
      data-sidebar="rail"
      aria-label="Toggle Sidebar"
      tabIndex={-1}
      onClick={toggleSidebar}
      title="Toggle Sidebar"
      className={cn(
        'wsms-absolute wsms-inset-y-0 wsms-z-20 wsms-hidden wsms-w-4 wsms--translate-x-1/2 wsms-transition-all wsms-ease-linear after:wsms-absolute after:wsms-inset-y-0 after:wsms-left-1/2 after:wsms-w-[2px] hover:after:wsms-bg-sidebar-border group-data-[side=left]:wsms--right-4 group-data-[side=right]:wsms-left-0 sm:wsms-flex',
        '[[data-side=left]_&]:wsms-cursor-w-resize [[data-side=right]_&]:wsms-cursor-e-resize',
        '[[data-side=left][data-state=collapsed]_&]:wsms-cursor-e-resize [[data-side=right][data-state=collapsed]_&]:wsms-cursor-w-resize',
        'group-data-[collapsible=offcanvas]:wsms-translate-x-0 group-data-[collapsible=offcanvas]:after:wsms-left-full group-data-[collapsible=offcanvas]:hover:wsms-bg-sidebar',
        '[[data-side=left][data-collapsible=offcanvas]_&]:wsms--right-2',
        '[[data-side=right][data-collapsible=offcanvas]_&]:wsms--left-2',
        className
      )}
      {...props}
    />
  )
})
SidebarRail.displayName = 'SidebarRail'

const SidebarInset = React.forwardRef(({ className, ...props }, ref) => {
  return (
    <main
      ref={ref}
      className={cn(
        'wsms-relative wsms-flex wsms-min-h-svh wsms-flex-1 wsms-flex-col wsms-bg-background',
        'peer-data-[variant=inset]:wsms-min-h-[calc(100svh-theme(spacing.4))] md:peer-data-[variant=inset]:wsms-m-2 md:peer-data-[state=collapsed]:peer-data-[variant=inset]:wsms-ml-2 md:peer-data-[variant=inset]:wsms-ml-0 md:peer-data-[variant=inset]:wsms-rounded-xl md:peer-data-[variant=inset]:wsms-shadow',
        className
      )}
      {...props}
    />
  )
})
SidebarInset.displayName = 'SidebarInset'

const SidebarHeader = React.forwardRef(({ className, ...props }, ref) => {
  return (
    <div
      ref={ref}
      data-sidebar="header"
      className={cn('wsms-flex wsms-flex-col wsms-gap-2 wsms-p-2', className)}
      {...props}
    />
  )
})
SidebarHeader.displayName = 'SidebarHeader'

const SidebarFooter = React.forwardRef(({ className, ...props }, ref) => {
  return (
    <div
      ref={ref}
      data-sidebar="footer"
      className={cn('wsms-flex wsms-flex-col wsms-gap-2 wsms-p-2', className)}
      {...props}
    />
  )
})
SidebarFooter.displayName = 'SidebarFooter'

const SidebarSeparator = React.forwardRef(({ className, ...props }, ref) => {
  return (
    <Separator
      ref={ref}
      data-sidebar="separator"
      className={cn('wsms-mx-2 wsms-w-auto wsms-bg-sidebar-border', className)}
      {...props}
    />
  )
})
SidebarSeparator.displayName = 'SidebarSeparator'

const SidebarContent = React.forwardRef(({ className, ...props }, ref) => {
  return (
    <div
      ref={ref}
      data-sidebar="content"
      className={cn(
        'wsms-flex wsms-min-h-0 wsms-flex-1 wsms-flex-col wsms-gap-2 wsms-overflow-auto group-data-[collapsible=icon]:wsms-overflow-hidden',
        className
      )}
      {...props}
    />
  )
})
SidebarContent.displayName = 'SidebarContent'

const SidebarGroup = React.forwardRef(({ className, ...props }, ref) => {
  return (
    <div
      ref={ref}
      data-sidebar="group"
      className={cn(
        'wsms-relative wsms-flex wsms-w-full wsms-min-w-0 wsms-flex-col wsms-p-2',
        className
      )}
      {...props}
    />
  )
})
SidebarGroup.displayName = 'SidebarGroup'

const SidebarGroupLabel = React.forwardRef(
  ({ className, asChild = false, ...props }, ref) => {
    const Comp = asChild ? Slot : 'div'

    return (
      <Comp
        ref={ref}
        data-sidebar="group-label"
        className={cn(
          'wsms-flex wsms-h-8 wsms-shrink-0 wsms-items-center wsms-rounded-md wsms-px-2 wsms-text-xs wsms-font-medium wsms-text-sidebar-foreground/70 wsms-outline-none wsms-ring-sidebar-ring wsms-transition-[margin,opacity] wsms-duration-200 wsms-ease-linear focus-visible:wsms-ring-2 [&>svg]:wsms-size-4 [&>svg]:wsms-shrink-0',
          'group-data-[collapsible=icon]:wsms--mt-8 group-data-[collapsible=icon]:wsms-opacity-0',
          className
        )}
        {...props}
      />
    )
  }
)
SidebarGroupLabel.displayName = 'SidebarGroupLabel'

const SidebarGroupContent = React.forwardRef(({ className, ...props }, ref) => (
  <div
    ref={ref}
    data-sidebar="group-content"
    className={cn('wsms-w-full wsms-text-sm', className)}
    {...props}
  />
))
SidebarGroupContent.displayName = 'SidebarGroupContent'

const SidebarMenu = React.forwardRef(({ className, ...props }, ref) => (
  <ul
    ref={ref}
    data-sidebar="menu"
    className={cn(
      'wsms-flex wsms-w-full wsms-min-w-0 wsms-flex-col wsms-gap-1',
      className
    )}
    {...props}
  />
))
SidebarMenu.displayName = 'SidebarMenu'

const SidebarMenuItem = React.forwardRef(({ className, ...props }, ref) => (
  <li
    ref={ref}
    data-sidebar="menu-item"
    className={cn('wsms-group/menu-item wsms-relative', className)}
    {...props}
  />
))
SidebarMenuItem.displayName = 'SidebarMenuItem'

const sidebarMenuButtonVariants = cva(
  'wsms-peer/menu-button wsms-flex wsms-w-full wsms-items-center wsms-gap-2 wsms-overflow-hidden wsms-rounded-md wsms-p-2 wsms-text-left wsms-text-sm wsms-outline-none wsms-ring-sidebar-ring wsms-transition-[width,height,padding] hover:wsms-bg-sidebar-accent hover:wsms-text-sidebar-accent-foreground focus-visible:wsms-ring-2 active:wsms-bg-sidebar-accent active:wsms-text-sidebar-accent-foreground disabled:wsms-pointer-events-none disabled:wsms-opacity-50 group-has-[[data-sidebar=menu-action]]/menu-item:pr-8 aria-disabled:wsms-pointer-events-none aria-disabled:wsms-opacity-50 data-[active=true]:wsms-bg-sidebar-accent data-[active=true]:wsms-font-medium data-[active=true]:wsms-text-sidebar-accent-foreground data-[state=open]:hover:wsms-bg-sidebar-accent data-[state=open]:hover:wsms-text-sidebar-accent-foreground group-data-[collapsible=icon]:wsms-!size-8 group-data-[collapsible=icon]:wsms-!p-2 [&>span:last-child]:wsms-truncate [&>svg]:wsms-size-4 [&>svg]:wsms-shrink-0',
  {
    variants: {
      variant: {
        default:
          'hover:wsms-bg-sidebar-accent hover:wsms-text-sidebar-accent-foreground',
        outline:
          'wsms-bg-background wsms-shadow-[0_0_0_1px_hsl(var(--sidebar-border))] hover:wsms-bg-sidebar-accent hover:wsms-text-sidebar-accent-foreground hover:wsms-shadow-[0_0_0_1px_hsl(var(--sidebar-accent))]',
      },
      size: {
        default: 'wsms-h-8 wsms-text-sm',
        sm: 'wsms-h-7 wsms-text-xs',
        lg: 'wsms-h-12 wsms-text-sm group-data-[collapsible=icon]:wsms-!p-0',
      },
    },
    defaultVariants: {
      variant: 'default',
      size: 'default',
    },
  }
)

const SidebarMenuButton = React.forwardRef(
  (
    {
      asChild = false,
      isActive = false,
      variant = 'default',
      size = 'default',
      tooltip,
      className,
      ...props
    },
    ref
  ) => {
    const Comp = asChild ? Slot : 'button'
    const { isMobile, state } = useSidebar()

    const button = (
      <Comp
        ref={ref}
        data-sidebar="menu-button"
        data-size={size}
        data-active={isActive}
        className={cn(sidebarMenuButtonVariants({ variant, size }), className)}
        {...props}
      />
    )

    if (!tooltip) {
      return button
    }

    if (typeof tooltip === 'string') {
      tooltip = { children: tooltip }
    }

    return (
      <Tooltip>
        <TooltipTrigger asChild>{button}</TooltipTrigger>
        <TooltipContent
          side="right"
          align="center"
          hidden={state !== 'collapsed' || isMobile}
          {...tooltip}
        />
      </Tooltip>
    )
  }
)
SidebarMenuButton.displayName = 'SidebarMenuButton'

const SidebarMenuSub = React.forwardRef(({ className, ...props }, ref) => (
  <ul
    ref={ref}
    data-sidebar="menu-sub"
    className={cn(
      'wsms-mx-3.5 wsms-flex wsms-min-w-0 wsms-translate-x-px wsms-flex-col wsms-gap-1 wsms-border-l wsms-border-sidebar-border wsms-px-2.5 wsms-py-0.5',
      'group-data-[collapsible=icon]:wsms-hidden',
      className
    )}
    {...props}
  />
))
SidebarMenuSub.displayName = 'SidebarMenuSub'

const SidebarMenuSubItem = React.forwardRef(({ className, ...props }, ref) => (
  <li ref={ref} className={cn('', className)} {...props} />
))
SidebarMenuSubItem.displayName = 'SidebarMenuSubItem'

const SidebarMenuSubButton = React.forwardRef(
  ({ asChild = false, size = 'md', isActive, className, ...props }, ref) => {
    const Comp = asChild ? Slot : 'a'

    return (
      <Comp
        ref={ref}
        data-sidebar="menu-sub-button"
        data-size={size}
        data-active={isActive}
        className={cn(
          'wsms-flex wsms-h-7 wsms-min-w-0 wsms--translate-x-px wsms-items-center wsms-gap-2 wsms-overflow-hidden wsms-rounded-md wsms-px-2 wsms-text-sidebar-foreground wsms-outline-none wsms-ring-sidebar-ring hover:wsms-bg-sidebar-accent hover:wsms-text-sidebar-accent-foreground focus-visible:wsms-ring-2 active:wsms-bg-sidebar-accent active:wsms-text-sidebar-accent-foreground disabled:wsms-pointer-events-none disabled:wsms-opacity-50 aria-disabled:wsms-pointer-events-none aria-disabled:wsms-opacity-50 [&>span:last-child]:wsms-truncate [&>svg]:wsms-size-4 [&>svg]:wsms-shrink-0 [&>svg]:wsms-text-sidebar-accent-foreground',
          'data-[active=true]:wsms-bg-sidebar-accent data-[active=true]:wsms-text-sidebar-accent-foreground',
          size === 'sm' && 'wsms-text-xs',
          size === 'md' && 'wsms-text-sm',
          className
        )}
        {...props}
      />
    )
  }
)
SidebarMenuSubButton.displayName = 'SidebarMenuSubButton'

export {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarInset,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
  SidebarProvider,
  SidebarRail,
  SidebarSeparator,
  SidebarTrigger,
  useSidebar,
}
