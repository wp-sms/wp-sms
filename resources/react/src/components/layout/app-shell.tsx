import type { ReactNode } from 'react';
import { Shield, LogIn, Paintbrush, ScrollText, ChevronRight, MessageSquare } from 'lucide-react';
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
  SidebarGroupLabel,
  SidebarGroupContent,
  SidebarHeader,
  SidebarInset,
  SidebarMenu,
  SidebarMenuItem,
  SidebarMenuButton,
  SidebarMenuSub,
  SidebarMenuSubItem,
  SidebarMenuSubButton,
  SidebarProvider,
} from '@/components/ui/sidebar';
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb';


interface AppShellProps {
  activeSection: string;
  onNavigate: (section: string) => void;
  version: string;
  children: ReactNode;
}

const NAV_ITEMS = [
  {
    id: 'authentication',
    label: 'Authentication',
    icon: LogIn,
    children: [
      { id: 'channels', label: 'Channels' },
      { id: 'registration', label: 'Registration' },
    ],
  },
  {
    id: 'security',
    label: 'Security',
    icon: Shield,
    children: [
      { id: 'mfa-policies', label: 'MFA Policies' },
      { id: 'rate-limiting', label: 'Rate Limiting' },
    ],
  },
  {
    id: 'branding',
    label: 'Branding',
    icon: Paintbrush,
  },
  {
    id: 'logs',
    label: 'Logs',
    icon: ScrollText,
  },
] as const;

const SECTION_DESCRIPTIONS: Record<string, string> = {
  'channels': 'Configure authentication channels — Phone, Email, and Password.',
  'registration': 'Configure how new users register through the authentication pages.',
  'mfa-policies': 'Manage MFA policies, backup codes, required roles, and enrollment timing.',
  'rate-limiting': 'Configure rate limits to prevent brute-force attacks on OTP verification.',
  'branding': 'Customize the appearance and behavior of your authentication pages.',
  'logs': 'View authentication events and configure logging preferences.',
};

function getPageTitle(sectionId: string): string | undefined {
  for (const item of NAV_ITEMS) {
    if (item.id === sectionId) return item.label;
    if ('children' in item) {
      const child = item.children.find((c) => c.id === sectionId);
      if (child) return child.label;
    }
  }
}

/** Derive parent section from a child section ID, using NAV_ITEMS as the source of truth. */
export function getParentSection(sectionId: string): string {
  for (const item of NAV_ITEMS) {
    if ('children' in item && item.children.some((c) => c.id === sectionId)) {
      return item.id;
    }
  }
  return sectionId;
}

function getBreadcrumb(sectionId: string, onNavigate: (section: string) => void) {
  for (const item of NAV_ITEMS) {
    if ('children' in item && item.children) {
      const child = item.children.find((c) => c.id === sectionId);
      if (child) {
        return (
          <>
            <BreadcrumbItem>
              <BreadcrumbLink asChild>
                <button onClick={() => onNavigate(item.children[0].id)}>
                  {item.label}
                </button>
              </BreadcrumbLink>
            </BreadcrumbItem>
            <BreadcrumbSeparator />
            <BreadcrumbItem>
              <BreadcrumbPage>{child.label}</BreadcrumbPage>
            </BreadcrumbItem>
          </>
        );
      }
    }
  }

  // Leaf section (no parent)
  const item = NAV_ITEMS.find((i) => i.id === sectionId);
  return (
    <BreadcrumbItem>
      <BreadcrumbPage>{item?.label ?? sectionId}</BreadcrumbPage>
    </BreadcrumbItem>
  );
}

export function AppShell({ activeSection, onNavigate, version, children }: AppShellProps) {
  return (
    <SidebarProvider defaultOpen={true}>
      <Sidebar collapsible="none">
        <SidebarHeader className="border-b border-sidebar-border px-4 py-3">
          <div className="flex items-center gap-3">
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary">
              <MessageSquare className="h-4 w-4 text-primary-foreground" />
            </div>
            <div className="flex flex-col">
              <span className="text-sm font-semibold leading-none">WP SMS</span>
              <span className="text-xs text-muted-foreground">v{version}</span>
            </div>
          </div>
        </SidebarHeader>
        <SidebarContent>
          <SidebarGroup>
            <SidebarGroupLabel>Settings</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {NAV_ITEMS.map((item) => {
                  const Icon = item.icon;
                  const hasChildren = 'children' in item && item.children;

                  if (!hasChildren) {
                    return (
                      <SidebarMenuItem key={item.id}>
                        <SidebarMenuButton
                          isActive={activeSection === item.id}
                          onClick={() => onNavigate(item.id)}
                        >
                          <Icon />
                          <span>{item.label}</span>
                        </SidebarMenuButton>
                      </SidebarMenuItem>
                    );
                  }

                  const isParentActive =
                    activeSection === item.id ||
                    item.children.some((c) => c.id === activeSection);

                  return (
                    <Collapsible
                      key={item.id}
                      defaultOpen={isParentActive}
                      className="group/collapsible"
                    >
                      <SidebarMenuItem>
                        <CollapsibleTrigger asChild>
                          <SidebarMenuButton>
                            <Icon />
                            <span>{item.label}</span>
                            <ChevronRight className="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-90" />
                          </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                          <SidebarMenuSub>
                            {item.children.map((child) => (
                              <SidebarMenuSubItem key={child.id}>
                                <SidebarMenuSubButton
                                  isActive={activeSection === child.id}
                                  onClick={() => onNavigate(child.id)}
                                >
                                  <span>{child.label}</span>
                                </SidebarMenuSubButton>
                              </SidebarMenuSubItem>
                            ))}
                          </SidebarMenuSub>
                        </CollapsibleContent>
                      </SidebarMenuItem>
                    </Collapsible>
                  );
                })}
              </SidebarMenu>
            </SidebarGroupContent>
          </SidebarGroup>
        </SidebarContent>
      </Sidebar>

      <SidebarInset>
        <header className="flex h-14 items-center gap-2 border-b px-6">
          <Breadcrumb>
            <BreadcrumbList>
              {getBreadcrumb(activeSection, onNavigate)}
            </BreadcrumbList>
          </Breadcrumb>
        </header>
        <div className="p-6">
          {getPageTitle(activeSection) && (
            <h1 className="text-lg font-semibold tracking-tight">{getPageTitle(activeSection)}</h1>
          )}
          {SECTION_DESCRIPTIONS[activeSection] && (
            <p className="mb-6 mt-1 max-w-2xl text-sm leading-relaxed text-muted-foreground">
              {SECTION_DESCRIPTIONS[activeSection]}
            </p>
          )}
          {children}
        </div>
      </SidebarInset>
    </SidebarProvider>
  );
}
