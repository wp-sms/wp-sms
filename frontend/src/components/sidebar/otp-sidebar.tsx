import { Link, useLocation } from '@tanstack/react-router'
import { MessageSquare, PanelLeft, PanelLeftClose, Settings } from 'lucide-react'

import { Button } from '@/components/ui/button'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from '@/components/ui/sidebar'
import { useSidebar } from '@/hooks/use-sidebar'
import { RenderIcon } from '@/lib/render-icon'

import { ThemeToggle } from '../theme-toggle'

export const OTPSidebar = () => {
  const location = useLocation()
  const currentPath = location.pathname
  const { state, toggleSidebar } = useSidebar()

  const menuItems = [
    {
      key: 'otp-activity',
      href: '/otp/activity',
      icon: 'Activity',
      title: 'Activity',
    },
    {
      key: 'otp-logs',
      href: '/otp/logs',
      icon: 'Logs',
      title: 'Logs',
    },
    {
      key: 'otp-authentication-channels',
      href: '/otp/authentication-channels',
      icon: 'IdCard',
      title: 'Authentication Channels',
    },
    {
      key: 'otp-branding',
      href: '/otp/branding',
      icon: 'Puzzle',
      title: 'Branding',
    },
    {
      key: 'otp-settings',
      href: '/otp/settings',
      icon: 'Settings',
      title: 'Settings',
    },
  ]

  return (
    <Sidebar variant="sidebar" collapsible="icon" className="border-r border-border">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            {state === 'collapsed' ? (
              <div className="flex justify-center p-2">
                <button
                  onClick={toggleSidebar}
                  className="group flex aspect-square size-10 items-center justify-center rounded-lg bg-gradient-primary text-sidebar-primary-foreground hover:bg-gradient-primary/80 transition-all duration-200 cursor-pointer"
                  title="Expand sidebar"
                >
                  <MessageSquare className="size-5 group-hover:hidden" />
                  <PanelLeft className="size-5 hidden group-hover:block" />
                </button>
              </div>
            ) : (
              <SidebarMenuButton size="lg" className="hover:bg-transparent hover:text-inherit justify-between">
                <div className="flex items-center gap-2">
                  <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-gradient-primary text-sidebar-primary-foreground">
                    <MessageSquare className="size-4" />
                  </div>
                  <div className="grid flex-1 text-left text-sm leading-tight">
                    <span className="truncate font-semibold">WP SMS Plugin</span>
                    <span className="truncate text-xs">OTP</span>
                  </div>
                </div>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={toggleSidebar}
                  className="h-8 w-8 p-0 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground"
                >
                  <PanelLeftClose className="h-4 w-4" />
                  <span className="sr-only">Toggle Sidebar</span>
                </Button>
              </SidebarMenuButton>
            )}
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel>Core Settings</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {menuItems.map((item) => (
                <SidebarMenuItem key={item.key}>
                  <SidebarMenuButton
                    asChild
                    isActive={currentPath === `${item.href}`}
                    tooltip={item.title}
                    className="data-[active=true]:bg-sidebar-active data-[active=true]:text-sidebar-active-foreground hover:data-[active=true]:bg-sidebar-active hover:data-[active=true]:text-sidebar-active-foreground transition-colors no-underline"
                  >
                    <Link to={item.href}>
                      <RenderIcon iconName={item.icon} />
                      <span>{item.title}</span>
                    </Link>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>

      <SidebarFooter className="sticky bottom-0">
        <SidebarMenu>
          <SidebarMenuItem>
            {state === 'collapsed' ? (
              <div className="flex flex-col items-center gap-2">
                <ThemeToggle />
                <SidebarMenuButton tooltip="Plugin Version 2.1.0">
                  <Settings />
                  <span>Plugin Version -</span>
                </SidebarMenuButton>
              </div>
            ) : (
              <div className="flex items-center justify-between w-full">
                <SidebarMenuButton tooltip={undefined}>
                  <Settings />
                  <span>Plugin Version -</span>
                </SidebarMenuButton>
                <ThemeToggle />
              </div>
            )}
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>
    </Sidebar>
  )
}
