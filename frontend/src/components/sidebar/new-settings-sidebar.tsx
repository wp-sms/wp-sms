import { Link, useLocation } from '@tanstack/react-router'
import { ChevronRight, MessageSquare, PanelLeft, PanelLeftClose, Settings } from 'lucide-react'

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
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from '@/components/ui/sidebar'
import { useSidebar } from '@/hooks/use-sidebar'
import { RenderIcon } from '@/lib/render-icon'
import { useGetSettingSchemaList } from '@/services/settings/use-get-setting-schema-list'

import { ThemeToggle } from '../theme-toggle'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '../ui/collapsible'

export function AppSidebar() {
  const { data: settingSchemaList } = useGetSettingSchemaList()

  const location = useLocation()
  const currentPath = location.pathname
  const { state, toggleSidebar } = useSidebar()

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
                    <span className="truncate text-xs">Settings Dashboard</span>
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
        {settingSchemaList?.data?.core && (
          <SidebarGroup>
            <SidebarGroupLabel>Core Settings</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {Object.entries(settingSchemaList?.data?.core ?? {})?.map(([_, item]) => (
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

        {settingSchemaList?.data?.addons && (
          <SidebarGroup>
            <SidebarGroupLabel>Addons</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {Object.entries(settingSchemaList?.data?.addons ?? {})?.map(([_, item]) => (
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

        {settingSchemaList?.data?.integrations && state !== 'collapsed' && (
          <SidebarGroup>
            <SidebarGroupLabel>Integrations</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {Object.entries(settingSchemaList?.data?.integrations.children ?? {})?.map(([key, item]) => {
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
                            <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
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
