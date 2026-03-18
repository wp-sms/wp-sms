import type { ReactNode } from 'react';
import { Shield, LogIn, Paintbrush, ScrollText, ChevronRight, Plug, BarChart3, Send } from 'lucide-react';
import { Logo } from '@/components/logo';
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


type NavItemList = readonly (typeof NAV_ITEMS)[number][];

interface AppShellProps {
  activeSection: string;
  onNavigate: (section: string) => void;
  version: string;
  children: ReactNode;
  navItems: NavItemList;
}

export const NAV_ITEMS = [
  {
    id: 'messaging',
    label: 'Messaging',
    icon: Send,
    children: [
      { id: 'campaigns', label: 'Campaigns' },
      { id: 'flows', label: 'Flows' },
      { id: 'contacts', label: 'Contacts' },
      { id: 'gateways', label: 'Gateways' },
      { id: 'message-logs', label: 'Message Logs' },
    ],
  },
  {
    id: 'authentication',
    label: 'Authentication',
    icon: LogIn,
    children: [
      { id: 'channels', label: 'Channels' },
      { id: 'registration', label: 'Registration' },
      { id: 'profile-fields', label: 'Profile Fields' },
    ],
  },
  {
    id: 'security',
    label: 'Security',
    icon: Shield,
    children: [
      { id: 'mfa-policies', label: 'MFA Policies' },
      { id: 'rate-limiting', label: 'Rate Limiting' },
      { id: 'captcha', label: 'CAPTCHA' },
      { id: 'account-cleanup', label: 'Account Cleanup' },
    ],
  },
  {
    id: 'integrations',
    label: 'Integrations',
    icon: Plug,
    children: [
      { id: 'woocommerce', label: 'WooCommerce' },
      { id: 'platform', label: 'Platform' },
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
  {
    id: 'reports',
    label: 'Reports',
    icon: BarChart3,
  },
] as const;

const SECTION_DESCRIPTIONS: Record<string, string> = {
  'campaigns': 'Create and send broadcast messages to your audience segments.',
  'flows': 'Create and manage automation flows with triggers and action steps.',
  'contacts': 'Manage your contacts, tags, and segments for targeted messaging.',
  'gateways': 'Configure messaging gateways for SMS, email, webhooks, and more.',
  'message-logs': 'View sent messages, delivery status, and error details.',
  'channels': 'Configure authentication channels — Phone, Email, and Password.',
  'registration': 'Configure how new users register through the authentication pages.',
  'profile-fields': 'Manage which fields appear on registration and profile forms.',
  'mfa-policies': 'Manage MFA policies, backup codes, required roles, and enrollment timing.',
  'rate-limiting': 'Configure rate limits to prevent brute-force attacks on OTP verification.',
  'captcha': 'Add CAPTCHA verification to protect authentication endpoints from bots.',
  'account-cleanup': 'Manage automatic cleanup of unverified pending registrations.',
  'woocommerce': 'Add email and phone OTP verification to WooCommerce checkout, registration, and account pages.',
  'platform': 'Browse available triggers and actions for building automation flows.',
  'branding': 'Customize the appearance and behavior of your authentication pages.',
  'logs': 'View authentication events and configure logging preferences.',
  'reports': 'View authentication activity statistics, security insights, and usage trends.',
};

function getPageTitle(sectionId: string, items: NavItemList): string | undefined {
  for (const item of items) {
    if (item.id === sectionId) return item.label;
    if ('children' in item) {
      const child = item.children.find((c) => c.id === sectionId);
      if (child) return child.label;
    }
  }
}

/** Derive parent section from a child section ID, using NAV_ITEMS as the source of truth. */
export function getParentSection(sectionId: string, items: NavItemList): string {
  for (const item of items) {
    if ('children' in item && item.children.some((c) => c.id === sectionId)) {
      return item.id;
    }
  }
  return sectionId;
}

function getBreadcrumb(sectionId: string, onNavigate: (section: string) => void, items: NavItemList) {
  for (const item of items) {
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
  const item = items.find((i) => i.id === sectionId);
  return (
    <BreadcrumbItem>
      <BreadcrumbPage>{item?.label ?? sectionId}</BreadcrumbPage>
    </BreadcrumbItem>
  );
}

export function AppShell({ activeSection, onNavigate, version, children, navItems }: AppShellProps) {
  const pageTitle = getPageTitle(activeSection, navItems);
  const pageDescription = SECTION_DESCRIPTIONS[activeSection];

  return (
    <SidebarProvider defaultOpen={true}>
      <Sidebar collapsible="none">
        <SidebarHeader className="border-b border-sidebar-border px-4 py-3">
          <div className="flex items-center gap-3">
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary">
              <Logo className="h-6 w-6 text-primary-foreground" />
            </div>
            <div className="flex flex-col">
              <span className="text-sm font-semibold leading-none">WSMS</span>
              <span className="text-xs text-muted-foreground">v{version}</span>
            </div>
          </div>
        </SidebarHeader>
        <SidebarContent>
          <SidebarGroup>
            <SidebarGroupLabel>Settings</SidebarGroupLabel>
            <SidebarGroupContent>
              <SidebarMenu>
                {navItems.map((item) => {
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
              {getBreadcrumb(activeSection, onNavigate, navItems)}
            </BreadcrumbList>
          </Breadcrumb>
        </header>
        <div className="p-6">
          {pageTitle && (
            <h1 className="text-lg font-semibold tracking-tight">{pageTitle}</h1>
          )}
          {pageDescription && (
            <p className="mb-6 mt-1 max-w-2xl text-sm leading-relaxed text-muted-foreground">
              {pageDescription}
            </p>
          )}
          {children}
        </div>
        <footer className="mt-auto border-t border-border/60 px-6 py-4">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-primary/[0.08]">
                <Logo className="h-4 w-4 text-primary/60" />
              </div>
              <div className="flex items-center gap-2">
                <span className="text-[13px] font-medium tracking-tight text-muted-foreground/80">WSMS</span>
                <span className="rounded border border-border/80 px-1.5 py-px font-mono text-[9px] tracking-wide text-muted-foreground/50">{version}</span>
                <span className="mx-0.5 text-border">·</span>
                <span className="text-[11px] text-muted-foreground/50">
                  by{' '}
                  <a href="https://veronalabs.com" target="_blank" rel="noopener noreferrer" className="font-medium text-muted-foreground/60 transition-colors hover:text-primary">
                    VeronaLabs
                  </a>
                </span>
              </div>
            </div>
            <nav className="flex items-center gap-4 text-[11px] text-muted-foreground/50">
              <a href="https://wsms.io/docs" target="_blank" rel="noopener noreferrer" className="transition-colors hover:text-foreground">Docs</a>
              <a href="https://wsms.io/support" target="_blank" rel="noopener noreferrer" className="transition-colors hover:text-foreground">Support</a>
              <a href="https://wsms.io" target="_blank" rel="noopener noreferrer" className="transition-colors hover:text-foreground">wsms.io</a>
            </nav>
          </div>
        </footer>
      </SidebarInset>
    </SidebarProvider>
  );
}
