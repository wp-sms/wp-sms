import { __, sprintf } from '@wordpress/i18n'
import { Link, useLocation } from '@tanstack/react-router'
import { ChevronRight, MessageSquare, PanelLeft, PanelLeftClose, Settings } from 'lucide-react'

import { useSidebar } from '@/hooks/use-sidebar'
import { useWordPressService } from '@/hooks/use-wordpress-service'
import { RenderIcon } from '@/lib/render-icon'

import { ThemeToggle } from '../theme-toggle'
import { Button } from '../ui/button'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '../ui/collapsible'
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
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from '../ui/sidebar'

export function SettingsSidebar() {
  const {
    layout: { sidebar },
    globals: { pluginVersion },
  } = useWordPressService()
  const location = useLocation()
  const currentPath = location.pathname
  const { state, toggleSidebar } = useSidebar()

  const pluginVersionLabel = sprintf(__('Plugin Version %s', 'wp-sms'), pluginVersion)

  return (
    <Sidebar variant="sidebar" collapsible="icon" className="border-r border-border">
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            {state === 'collapsed' ? (
              <div className="flex justify-center px-2">
                <button
                  onClick={toggleSidebar}
                  className="group flex aspect-square size-10 items-center justify-center rounded-lg bg-gradient-primary text-sidebar-primary-foreground hover:bg-gradient-primary/80 transition-all duration-200 cursor-pointer"
                  title={__('Expand sidebar', 'wp-sms')}
                >
                  <MessageSquare className="size-5 group-hover:hidden" />
                  <PanelLeft className="size-5 hidden group-hover:block" />
                </button>
              </div>
            ) : (
              <div className="flex items-center gap-2 px-2">
                <div className="flex items-center gap-2 flex-1">
                  <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-gradient-primary text-sidebar-primary-foreground">
                    <MessageSquare className="size-4" />
                  </div>
                  <div className="grid flex-1 text-start text-sm leading-tight">
                    <span className="truncate font-semibold">WP SMS Plugin</span>
                    <span className="truncate text-xs">{__('Settings Dashboard', 'wp-sms')}</span>
                  </div>
                </div>
                <Button
                  variant="ghost"
                  size="sm"
                  onClick={toggleSidebar}
                  className="h-8 w-8 p-0 hover:bg-sidebar-accent hover:text-sidebar-accent-foreground rtl:[&>svg]:scale-x-[-1]"
                >
                  <PanelLeftClose className="h-4 w-4" />
                  <span className="sr-only">{__('Toggle Sidebar', 'wp-sms')}</span>
                </Button>
              </div>
            )}
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        {sidebar?.core && (
          <SidebarGroup>
            <SidebarGroupLabel>{__('Core Settings', 'wp-sms')}</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {Object.entries(sidebar?.core ?? {})?.map(([_, item]) => (
                  <SidebarMenuItem key={`core-item-${item?.name}-${item.label}`}>
                    <SidebarMenuButton
                      asChild
                      isActive={currentPath === `/settings/${item.name}`}
                      tooltip={item.label}
                      className="data-[active=true]:bg-sidebar-active data-[active=true]:text-sidebar-active-foreground hover:data-[active=true]:bg-sidebar-active hover:data-[active=true]:text-sidebar-active-foreground transition-colors no-underline"
                    >
                      <Link to="/settings/$name" params={{ name: item.name }}>
                        <RenderIcon iconName={item.icon} />
                        <span>{item.label}</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                ))}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        )}

        {sidebar?.addons && (
          <SidebarGroup>
            <SidebarGroupLabel>{__('Addons', 'wp-sms')}</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {Object.entries(sidebar?.addons ?? {})?.map(([_, item]) => (
                  <SidebarMenuItem key={`addon-item-${item?.name}-${item.label}`}>
                    <SidebarMenuButton
                      asChild
                      isActive={currentPath === `/settings/${item.name}`}
                      tooltip={item.label}
                      className="data-[active=true]:bg-sidebar-active data-[active=true]:text-sidebar-active-foreground hover:data-[active=true]:bg-sidebar-active hover:data-[active=true]:text-sidebar-active-foreground transition-colors no-underline"
                    >
                      <Link to="/settings/$name" params={{ name: item.name }}>
                        <RenderIcon iconName={item.icon} />
                        <span>{item.label}</span>
                      </Link>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                ))}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        )}

        {sidebar?.integrations && state !== 'collapsed' && (
          <SidebarGroup>
            <SidebarGroupLabel>{__('Integrations', 'wp-sms')}</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {Object.entries(sidebar?.integrations.children ?? {})?.map(([key, item]) => {
                  return (
                    <Collapsible
                      key={`integrations-item-${item?.label}-${key}`}
                      asChild
                      defaultOpen={Object.entries(item?.children ?? {}).some(([_, subItem]) => {
                        return currentPath === `/settings/${subItem?.name}`
                      })}
                      className="group/collapsible"
                    >
                      <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                          <SidebarMenuButton tooltip={item.label}>
                            <span>{item.label}</span>
                            <ChevronRight className="ms-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90 rtl:rotate-180" />
                          </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                          <SidebarMenuSub>
                            {Object.entries(item.children ?? {})?.map(([_, subItem]) => (
                              <SidebarMenuSubItem key={`nested-item-${item.label}-${subItem?.name}`}>
                                <SidebarMenuSubButton
                                  asChild
                                  isActive={currentPath === `/settings/${subItem.name}`}
                                  className="data-[active=true]:bg-sidebar-active data-[active=true]:text-sidebar-active-foreground hover:data-[active=true]:bg-sidebar-active hover:data-[active=true]:text-sidebar-active-foreground transition-colors no-underline"
                                >
                                  <Link to="/settings/$name" params={{ name: subItem.name }}>
                                    <span>{subItem.label}</span>
                                  </Link>
                                </SidebarMenuSubButton>
                              </SidebarMenuSubItem>
                            ))}
                          </SidebarMenuSub>
                        </CollapsibleContent>
                      </SidebarMenuItem>
                    </Collapsible>
                  )
                })}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        )}
      </SidebarContent>

      <SidebarFooter className="sticky bottom-0 bg-sidebar">
        <SidebarMenu>
          <SidebarMenuItem>
            {state === 'collapsed' ? (
              <div className="flex flex-col items-center gap-2">
                <ThemeToggle />
                <SidebarMenuButton tooltip={pluginVersionLabel}>
                  <Settings />
                  <span>{pluginVersionLabel}</span>
                </SidebarMenuButton>
              </div>
            ) : (
              <div className="flex items-center justify-between w-full">
                <SidebarMenuButton tooltip={undefined}>
                  <Settings />
                  <span>{pluginVersionLabel}</span>
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