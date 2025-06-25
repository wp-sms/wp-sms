"use client"

import type * as React from "react"
import {
  Settings,
  Wifi,
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

// Menu data structure with icons
const menuData = {
  main: [
    { title: "Dashboard", url: "/dashboard", icon: Home },
    { title: "Settings", url: "/settings", icon: Settings },
    { title: "Messages", url: "/messages", icon: MessageSquare },
    { title: "Subscribers", url: "/subscribers", icon: Users },
    { title: "Reports", url: "/reports", icon: BarChart3 },
  ],
  settings: [
    { title: "General", url: "/settings", icon: Settings },
    { title: "Gateway", url: "/settings?tab=gateway", icon: Wifi },
    { title: "Notifications", url: "/settings/notifications", icon: Bell },
    { title: "SMS Button", url: "/settings/sms-button", icon: MousePointer },
    { title: "Security", url: "/settings/security", icon: Shield, isPro: true },
    { title: "Developer", url: "/settings/developer", icon: Code },
  ],
  integrations: [
    {
      title: "Contact Forms",
      icon: Mail,
      items: [
        { title: "Contact Form 7", url: "/settings/integrations/contact-form-7" },
        { title: "GravityForms", url: "/settings/integrations/gravity-forms" },
        { title: "Fluent Form", url: "/settings/integrations/fluent-form" },
      ],
    },
    {
      title: "Community & Membership",
      icon: UserCheck,
      items: [
        { title: "BuddyPress", url: "/settings/integrations/buddypress" },
        { title: "Ultimate Member", url: "/settings/integrations/ultimate-member" },
      ],
    },
    {
      title: "E-commerce",
      icon: ShoppingCart,
      items: [
        { title: "WooCommerce", url: "/settings/integrations/woocommerce" },
        { title: "Easy Digital Downloads", url: "/settings/integrations/edd" },
      ],
    },
    {
      title: "Learning & Events",
      icon: GraduationCap,
      items: [
        { title: "LearnDash", url: "/settings/integrations/learndash" },
        { title: "The Events Calendar", url: "/settings/integrations/events-calendar" },
      ],
    },
    {
      title: "Booking & Appointments",
      icon: Calendar,
      items: [
        { title: "Bookly", url: "/settings/integrations/bookly" },
        { title: "Amelia", url: "/settings/integrations/amelia" },
      ],
    },
  ],
}

interface AppSidebarProps extends React.ComponentProps<typeof Sidebar> {}

export function AppSidebar({ ...props }: AppSidebarProps) {
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
        {/* Main Navigation */}
        <SidebarGroup>
          <SidebarGroupLabel>Main</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {menuData.main.map((item) => (
                <SidebarMenuItem key={item.title}>
                  <SidebarMenuButton asChild tooltip={item.title}>
                    <a href={item.url} className="flex items-center gap-2">
                      <item.icon className="size-4" />
                      <span>{item.title}</span>
                    </a>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        {/* Settings Menu */}
        <SidebarGroup>
          <SidebarGroupLabel>Settings</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {menuData.settings.map((item) => (
                <SidebarMenuItem key={item.title}>
                  <SidebarMenuButton asChild tooltip={`${item.title}${item.isPro ? " (Pro)" : ""}`}>
                    <a href={item.url} className="flex items-center gap-2">
                      <item.icon className="size-4" />
                      <span>{item.title}</span>
                      {item.isPro && <Lock className="size-3 text-orange-500 ml-auto" />}
                    </a>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>

        {/* Integrations Menu */}
        <SidebarGroup>
          <SidebarGroupLabel>Integrations</SidebarGroupLabel>
          <SidebarGroupContent>
            <SidebarMenu>
              {menuData.integrations.map((item) => (
                <Collapsible key={item.title} className="group/collapsible">
                  <SidebarMenuItem>
                    <CollapsibleTrigger asChild>
                      <SidebarMenuButton
                        tooltip={`${item.title} - ${item.items.map((sub) => sub.title).join(", ")}`}
                        className="flex items-center gap-2"
                      >
                        <item.icon className="size-4" />
                        <span>{item.title}</span>
                        <ChevronRight className="ml-auto size-4 transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                      </SidebarMenuButton>
                    </CollapsibleTrigger>
                    <CollapsibleContent>
                      <SidebarMenuSub>
                        {item.items.map((subItem) => (
                          <SidebarMenuSubItem key={subItem.title}>
                            <SidebarMenuSubButton asChild>
                              <a href={subItem.url}>{subItem.title}</a>
                            </SidebarMenuSubButton>
                          </SidebarMenuSubItem>
                        ))}
                      </SidebarMenuSub>
                    </CollapsibleContent>
                  </SidebarMenuItem>
                </Collapsible>
              ))}
            </SidebarMenu>
          </SidebarGroupContent>
        </SidebarGroup>
      </SidebarContent>
      <SidebarRail />
    </Sidebar>
  )
}
