"use client"

import type * as React from "react"
import {
  Settings,
  Users,
  Bell,
  MousePointer,
  Shield,
  Code,
  Mail,
  UserCheck,
  ShoppingCart,
  GraduationCap,
  Calendar,
  ChevronRight,
  Lock,
  BarChart3,
  MessageSquare,
  Home,
  Loader2,
  AlertCircle,
  Cog,
  Send,
  Zap,
  Star,
  MessageCircle,
  Newspaper,
} from "lucide-react"

import {
  Sidebar,
  SidebarContent,
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
  SidebarRail,
} from "../ui/sidebar"
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from "../ui/collapsible"
import { Alert, AlertDescription } from "../ui/alert"
import { useSchema } from "../../hooks/use-schema"

// Icon mapping for different integration types
const iconMap: Record<string, React.ComponentType<any>> = {
  contact_forms: Mail,
  community_membership: UserCheck,
  ecommerce: ShoppingCart,
  learning_events: GraduationCap,
  booking_appointments: Calendar,
  support: Bell,
  jobs: Users,
  // Default icons for specific integrations
  contact_form_7: Mail,
  gravityforms: Mail,
  quform: Mail,
  buddypress: UserCheck,
  ultimate_member: UserCheck,
  woocommerce: ShoppingCart,
  edd: ShoppingCart,
  awesome_support: Bell,
  job_manager: Users,
}

// Icon mapping for all Lucide icons
const lucideIconMap: Record<string, React.ComponentType<any>> = {
  Settings,
  Cog,
  MessageSquare,
  Send,
  Zap,
  Star,
  Bell,
  Mail,
  Users,
  MousePointer,
  MessageCircle,
  Shield,
  Lock,
  ShoppingCart,
  UserCheck,
  GraduationCap,
  Calendar,
  BarChart3,
  Newspaper,
  // Add more icons as needed
}

// Helper function to render dynamic icons
const renderIcon = (iconName: string, className: string = "size-4") => {
  const IconComponent = lucideIconMap[iconName] || Settings
  return <IconComponent className={className} />
}

interface AppSidebarProps extends React.ComponentProps<typeof Sidebar> {
  onGroupSelect?: (groupName: string) => void
  selectedGroup?: string
}

export function DynamicSidebar({ onGroupSelect, selectedGroup, ...props }: AppSidebarProps) {
  const { data, loading, error } = useSchema()

  const handleGroupClick = (groupName: string) => {
    if (onGroupSelect) {
      onGroupSelect(groupName)
    }
  }

  if (loading) {
    return (
      <Sidebar collapsible="icon" {...props}>
        <SidebarHeader>
          <SidebarMenu>
            <SidebarMenuItem>
              <SidebarMenuButton size="lg">
                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                  <Settings className="size-4" />
                </div>
                <div className="grid flex-1 text-left text-sm leading-tight">
                  <span className="truncate font-semibold">WP SMS</span>
                  <span className="truncate text-xs text-muted-foreground">Loading...</span>
                </div>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarHeader>
        <SidebarContent>
          <div className="flex items-center justify-center p-4">
            <Loader2 className="h-6 w-6 animate-spin" />
          </div>
        </SidebarContent>
      </Sidebar>
    )
  }

  if (error) {
    return (
      <Sidebar collapsible="icon" {...props}>
        <SidebarHeader>
          <SidebarMenu>
            <SidebarMenuItem>
              <SidebarMenuButton size="lg">
                <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                  <Settings className="size-4" />
                </div>
                <div className="grid flex-1 text-left text-sm leading-tight">
                  <span className="truncate font-semibold">WP SMS</span>
                  <span className="truncate text-xs text-muted-foreground">Error</span>
                </div>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarHeader>
        <SidebarContent>
          <Alert variant="destructive" className="m-4">
            <AlertCircle className="h-4 w-4" />
            <AlertDescription>
              Failed to load menu: {error}
            </AlertDescription>
          </Alert>
        </SidebarContent>
      </Sidebar>
    )
  }

  if (!data) {
    return null
  }

  return (
    <Sidebar collapsible="icon" {...props}>
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              size="lg"
              className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
            >
              <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-primary text-primary-foreground">
                <Settings className="size-4" />
              </div>
              <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-semibold">WP SMS</span>
                <span className="truncate text-xs text-muted-foreground">Admin Panel</span>
              </div>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        {/* Core Settings */}
        {data.core && Object.keys(data.core).length > 0 && (
          <SidebarGroup>
            <SidebarGroupLabel>Core Settings</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {Object.entries(data.core).map(([key, item]) => (
                  <SidebarMenuItem key={key}>
                    <SidebarMenuButton 
                      asChild 
                      tooltip={item.label}
                      onClick={() => handleGroupClick(key)}
                      className={selectedGroup === key ? "bg-sidebar-accent text-sidebar-accent-foreground" : ""}
                    >
                      <button className="flex items-center gap-2 w-full text-left">
                        {renderIcon(item.icon || 'Settings')}
                        <span>{item.label}</span>
                      </button>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                ))}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        )}

        {/* Addons */}
        {data.addons && Object.keys(data.addons).length > 0 && (
          <SidebarGroup>
            <SidebarGroupLabel>Addons</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {Object.entries(data.addons).map(([key, item]) => (
                  <SidebarMenuItem key={key}>
                    <SidebarMenuButton 
                      asChild 
                      tooltip={`${item.label} (Pro)`}
                      onClick={() => handleGroupClick(key)}
                      className={selectedGroup === key ? "bg-sidebar-accent text-sidebar-accent-foreground" : ""}
                    >
                      <button className="flex items-center gap-2 w-full text-left">
                        {renderIcon(item.icon || 'Shield')}
                        <span>{item.label}</span>
                        <Lock className="size-3 text-orange-500 ml-auto" />
                      </button>
                    </SidebarMenuButton>
                  </SidebarMenuItem>
                ))}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        )}

        {/* Integrations */}
        {data.integrations && data.integrations.children && (
          <SidebarGroup>
            <SidebarGroupLabel>{data.integrations.label}</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {Object.entries(data.integrations.children).map(([key, group]) => {
                  const IconComponent = iconMap[key] || Settings
                  
                  if ('children' in group && group.children) {
                    return (
                      <Collapsible key={key} className="group/collapsible">
                        <SidebarMenuItem>
                          <CollapsibleTrigger asChild>
                            <SidebarMenuButton
                              tooltip={group.label}
                              className="flex items-center gap-2"
                            >
                              <IconComponent className="size-4" />
                              <span>{group.label}</span>
                              <ChevronRight className="ml-auto size-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                            </SidebarMenuButton>
                          </CollapsibleTrigger>
                          <CollapsibleContent>
                            <SidebarMenuSub>
                              {Object.entries(group.children).map(([childKey, childItem]) => {
                                if ('name' in childItem) {
                                  return (
                                    <SidebarMenuSubItem key={childKey}>
                                      <SidebarMenuSubButton 
                                        asChild
                                        onClick={() => handleGroupClick(childItem.name)}
                                        className={selectedGroup === childItem.name ? "bg-sidebar-accent text-sidebar-accent-foreground" : ""}
                                      >
                                        <button className="w-full text-left">
                                          {childItem.label}
                                        </button>
                                      </SidebarMenuSubButton>
                                    </SidebarMenuSubItem>
                                  )
                                }
                                return null
                              })}
                            </SidebarMenuSub>
                          </CollapsibleContent>
                        </SidebarMenuItem>
                      </Collapsible>
                    )
                  }
                  
                  return null
                })}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        )}
      </SidebarContent>
      <SidebarRail />
    </Sidebar>
  )
} 