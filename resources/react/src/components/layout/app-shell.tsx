import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { Shield, LogIn, Paintbrush, ScrollText, ChevronRight, Plug, BarChart3, Megaphone, Workflow, Users, Radio, Blocks, Settings2, MessageSquare, SlidersHorizontal, ClipboardList, Webhook, Bell, Sparkles, Activity } from 'lucide-react';
import { Logo } from '@/components/logo';
import { SaveBar } from '@/components/layout/save-bar';
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
  SidebarTrigger,
  useSidebar,
} from '@/components/ui/sidebar';
import { Button } from '@/components/ui/button';
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { AREA_LABELS } from '@/lib/constants';
import type { Area } from '@/lib/area-nav';

const SIDEBAR_DEFAULT_OPEN = (() => {
  const match = document.cookie.match(/(?:^|;\s*)sidebar_state=([^;]*)/);
  return match ? match[1] === 'true' : true;
})();

type NavItemList = readonly (typeof NAV_ITEMS)[number][];

interface AppShellProps {
  activeSection: string;
  onNavigate: (section: string) => void;
  version: string;
  area: Area;
  children: ReactNode;
  navItems: NavItemList;
}

export const NAV_ITEMS = [
  {
    id: 'campaigns',
    label: 'Campaigns',
    icon: Megaphone,
  },
  {
    id: 'flows',
    label: 'Flows',
    icon: Workflow,
  },
  {
    id: 'contacts',
    label: 'Contacts',
    icon: Users,
  },
  {
    id: 'gateways',
    label: 'Gateways',
    icon: Radio,
  },
  {
    id: 'apps',
    label: 'Apps',
    icon: Blocks,
  },
  {
    id: 'webhooks',
    label: 'Webhooks',
    icon: Webhook,
  },
  {
    id: 'message-logs',
    label: 'Message Logs',
    icon: ScrollText,
  },
  {
    id: 'system',
    label: 'System',
    icon: Activity,
  },
  {
    id: 'messaging-button',
    label: 'Messaging Button',
    icon: MessageSquare,
    children: [
      { id: 'mb-appearance', label: 'Appearance' },
      { id: 'mb-pages', label: 'Pages' },
      { id: 'mb-team', label: 'Team' },
      { id: 'mb-display-rules', label: 'Display Rules' },
    ],
  },
  {
    id: 'subscription-forms',
    label: 'Subscription Forms',
    icon: ClipboardList,
  },
  {
    id: 'settings',
    label: 'Settings',
    icon: Settings2,
    children: [
      { id: 'opt-out', label: 'Opt-Out' },
      { id: 'phone-restriction', label: 'Phone Restrictions' },
    ],
  },
  {
    id: 'general',
    label: 'General',
    icon: SlidersHorizontal,
  },
  {
    id: 'authentication',
    label: 'Authentication',
    icon: LogIn,
    children: [
      { id: 'channels', label: 'Channels' },
      { id: 'profile-fields', label: 'Profile Fields' },
      { id: 'registration-forms', label: 'Registration Forms' },
      { id: 'templates', label: 'Message Templates' },
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
      { id: 'phone-restriction', label: 'Phone Restrictions' },
    ],
  },
  {
    id: 'integrations',
    label: 'Integrations',
    icon: Plug,
  },
  {
    id: 'branding',
    label: 'Branding',
    icon: Paintbrush,
  },
  {
    id: 'monitoring',
    label: 'Monitoring',
    icon: BarChart3,
    children: [
      { id: 'logs', label: 'Logs' },
      { id: 'reports', label: 'Reports' },
    ],
  },
] as const;

/** Derive parent section from a child section ID, using NAV_ITEMS as the source of truth. */
export function getParentSection(sectionId: string, items: NavItemList): string {
  for (const item of items) {
    if ('children' in item && item.children.some((c) => c.id === sectionId)) {
      return item.id;
    }
  }
  return sectionId;
}

function CollapsedGroupItem({ item, activeSection, isActive, onNavigate }: {
  item: Extract<(typeof NAV_ITEMS)[number], { children: unknown }>;
  activeSection: string;
  isActive: boolean;
  onNavigate: (s: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const closeTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
  const Icon = item.icon;

  useEffect(() => () => clearTimeout(closeTimer.current), []);

  const scheduleOpen = useCallback(() => {
    clearTimeout(closeTimer.current);
    setOpen(true);
  }, []);

  const scheduleClose = useCallback(() => {
    clearTimeout(closeTimer.current);
    closeTimer.current = setTimeout(() => setOpen(false), 150);
  }, []);

  return (
    <SidebarMenuItem
      onMouseEnter={scheduleOpen}
      onMouseLeave={scheduleClose}
    >
      <DropdownMenu open={open} onOpenChange={(v) => { if (!v) scheduleClose(); }} modal={false}>
        <DropdownMenuTrigger asChild>
          <SidebarMenuButton tooltip={!open ? item.label : undefined} isActive={isActive}>
            <Icon />
            <span>{item.label}</span>
            <ChevronRight className="ml-auto" />
          </SidebarMenuButton>
        </DropdownMenuTrigger>
        <DropdownMenuContent
          side="right"
          align="start"
          sideOffset={4}
          onMouseEnter={scheduleOpen}
          onMouseLeave={scheduleClose}
          onCloseAutoFocus={(e) => e.preventDefault()}
        >
          {item.children.map((child) => (
            <DropdownMenuItem
              key={child.id}
              onClick={() => { setOpen(false); onNavigate(child.id); }}
              className={activeSection === child.id ? 'bg-accent' : ''}
            >
              {child.label}
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
    </SidebarMenuItem>
  );
}

function NavMenu({ activeSection, onNavigate, navItems }: { activeSection: string; onNavigate: (s: string) => void; navItems: NavItemList }) {
  const { state, isMobile } = useSidebar();
  const isCollapsed = !isMobile && state === 'collapsed';

  return (
    <SidebarMenu>
      {navItems.map((item) => {
        const Icon = item.icon;
        const hasChildren = 'children' in item && item.children;

        if (!hasChildren) {
          return (
            <SidebarMenuItem key={item.id}>
              <SidebarMenuButton
                tooltip={item.label}
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

        if (isCollapsed) {
          return (
            <CollapsedGroupItem
              key={item.id}
              item={item}
              activeSection={activeSection}
              isActive={isParentActive}
              onNavigate={onNavigate}
            />
          );
        }

        return (
          <Collapsible
            key={item.id}
            defaultOpen={isParentActive}
            className="group/collapsible"
          >
            <SidebarMenuItem>
              <CollapsibleTrigger asChild>
                <SidebarMenuButton tooltip={item.label}>
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
  );
}

export function AppShell({ activeSection, onNavigate, version, area, children, navItems }: AppShellProps) {
  return (
    <SidebarProvider defaultOpen={SIDEBAR_DEFAULT_OPEN}>
      <Sidebar collapsible="icon">
        <SidebarHeader className="h-14 px-5 justify-center group-data-[collapsible=icon]:px-2">
          <div className="flex items-center gap-3 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:gap-0">
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary">
              <Logo className="h-6 w-6 text-primary-foreground" />
            </div>
            <div className="flex flex-col group-data-[collapsible=icon]:hidden">
              <span className="text-lg font-extrabold tracking-tight leading-none">WSMS</span>
              <span className="text-xs text-muted-foreground">{AREA_LABELS[area]}</span>
            </div>
          </div>
        </SidebarHeader>
        <SidebarContent>
          <SidebarGroup>
            <SidebarGroupLabel>Settings</SidebarGroupLabel>
            <SidebarGroupContent>
              <NavMenu activeSection={activeSection} onNavigate={onNavigate} navItems={navItems} />
            </SidebarGroupContent>
          </SidebarGroup>
        </SidebarContent>
      </Sidebar>

      <SidebarInset>
        <header className="flex h-14 items-center justify-end gap-2 border-b-2 border-foreground bg-sidebar px-6">
          <SidebarTrigger className="-ml-1 mr-auto" />
          <Button variant="ghost" size="sm"
            className="bg-primary/10 text-xs font-medium text-primary hover:bg-primary/15 hover:text-primary">
            <Sparkles className="size-3" />
            Go Premium
          </Button>
          <Button variant="ghost" size="icon-sm" className="relative text-muted-foreground hover:text-foreground">
            <Bell className="size-4" />
            <span className="absolute top-1 right-1.5 size-2 rounded-full bg-destructive ring-2 ring-background" />
            <span className="sr-only">Notifications</span>
          </Button>
        </header>
        <div key={activeSection} className="animate-fade-up p-7">
          {children}
        </div>
        <SaveBar />
        <footer className="mt-auto border-t border-border/40 px-6 py-3">
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
            <nav className="flex items-center gap-4 text-[11px] text-muted-foreground/40">
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
