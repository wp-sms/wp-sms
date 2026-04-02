import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { __ } from '@wordpress/i18n';
import { LayoutDashboard, LogIn, ChevronRight, BarChart3, Megaphone, Workflow, Users, Radio, Settings2, Bell, Sparkles, type LucideIcon } from 'lucide-react';
import { Logo } from '@/components/logo';
import { SaveBar } from '@/components/layout/save-bar';
import {
  Sidebar,
  SidebarContent,
  SidebarGroup,
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
import { useIsRtl } from '@/hooks/use-is-rtl';
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

const SIDEBAR_DEFAULT_OPEN = (() => {
  const match = document.cookie.match(/(?:^|;\s*)sidebar_state=([^;]*)/);
  return match ? match[1] === 'true' : true;
})();

// --- Navigation types ---

export interface NavChild {
  id: string;
  label: string;
}

export interface NavItem {
  id: string;
  label: string;
  icon: LucideIcon;
  children?: readonly NavChild[];
}

// --- Navigation structure ---

export const NAV_ITEMS: readonly NavItem[] = [
  { id: 'dashboard', label: __('Dashboard', 'wp-sms'), icon: LayoutDashboard },
  {
    id: 'audience', label: __('Audience', 'wp-sms'), icon: Users,
    children: [
      { id: 'contacts', label: __('Contacts', 'wp-sms') },
      { id: 'tags', label: __('Tags', 'wp-sms') },
      { id: 'lists', label: __('Lists', 'wp-sms') },
      { id: 'forms', label: __('Subscription Forms', 'wp-sms') },
    ],
  },
  { id: 'campaigns', label: __('Campaigns', 'wp-sms'), icon: Megaphone },
  { id: 'automation', label: __('Automation', 'wp-sms'), icon: Workflow },
  {
    id: 'channels', label: __('Channels', 'wp-sms'), icon: Radio,
    children: [
      { id: 'gateways', label: __('Gateways', 'wp-sms') },
      { id: 'integrations', label: __('Integrations', 'wp-sms') },
      { id: 'webhooks', label: __('Webhooks', 'wp-sms') },
      { id: 'messaging-button', label: __('Messaging Button', 'wp-sms') },
    ],
  },
  {
    id: 'identity', label: __('Identity', 'wp-sms'), icon: LogIn,
    children: [
      { id: 'auth-channels', label: __('Auth Channels', 'wp-sms') },
      { id: 'registration-forms', label: __('Registration Forms', 'wp-sms') },
      { id: 'profile-fields', label: __('Profile Fields', 'wp-sms') },
      { id: 'templates', label: __('Templates', 'wp-sms') },
    ],
  },
  {
    id: 'monitoring', label: __('Monitoring', 'wp-sms'), icon: BarChart3,
    children: [
      { id: 'health', label: __('Health', 'wp-sms') },
      { id: 'auth-logs', label: __('Auth Logs', 'wp-sms') },
      { id: 'message-logs', label: __('Message Logs', 'wp-sms') },
      { id: 'reports', label: __('Reports', 'wp-sms') },
    ],
  },
  {
    id: 'settings', label: __('Settings', 'wp-sms'), icon: Settings2,
    children: [
      { id: 'general', label: __('General', 'wp-sms') },
      { id: 'security', label: __('Security', 'wp-sms') },
      { id: 'privacy', label: __('Privacy', 'wp-sms') },
      { id: 'compliance', label: __('Compliance', 'wp-sms') },
      { id: 'branding', label: __('Branding', 'wp-sms') },
      { id: 'extensions', label: __('Extensions', 'wp-sms') },
      { id: 'migration', label: __('Migration', 'wp-sms') },
    ],
  },
];

/** All valid top-level section IDs. */
export const VALID_SECTIONS: Set<string> = new Set(NAV_ITEMS.map(item => item.id));

// --- Sidebar components ---

interface AppShellProps {
  activeSection: string;
  activeSubTab?: string;
  onNavigate: (section: string) => void;
  version: string;
  children: ReactNode;
}

type NavItemWithChildren = NavItem & { children: readonly NavChild[] };

function CollapsedGroupItem({ item, activeSection, activeSubTab, isActive, onNavigate }: {
  item: NavItemWithChildren;
  activeSection: string;
  activeSubTab?: string;
  isActive: boolean;
  onNavigate: (s: string) => void;
}) {
  const [open, setOpen] = useState(false);
  const closeTimer = useRef<ReturnType<typeof setTimeout>>(undefined);
  const isRtl = useIsRtl();
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
            <ChevronRight className="ms-auto rtl:scale-x-[-1]" />
          </SidebarMenuButton>
        </DropdownMenuTrigger>
        <DropdownMenuContent
          side={isRtl ? "left" : "right"}
          align="start"
          sideOffset={4}
          onMouseEnter={scheduleOpen}
          onMouseLeave={scheduleClose}
          onCloseAutoFocus={(e) => e.preventDefault()}
        >
          {item.children.map((child) => (
            <DropdownMenuItem
              key={child.id}
              onClick={() => { setOpen(false); onNavigate(item.id + '/' + child.id); }}
              className={activeSection === item.id && activeSubTab === child.id ? 'bg-accent' : ''}
            >
              {child.label}
            </DropdownMenuItem>
          ))}
        </DropdownMenuContent>
      </DropdownMenu>
    </SidebarMenuItem>
  );
}

function NavMenu({ activeSection, activeSubTab, onNavigate, items }: {
  activeSection: string;
  activeSubTab?: string;
  onNavigate: (s: string) => void;
  items: readonly NavItem[];
}) {
  const { state, isMobile } = useSidebar();
  const isCollapsed = !isMobile && state === 'collapsed';

  return (
    <SidebarMenu>
      {items.map((item) => {
        const Icon = item.icon;
        const hasChildren = item.children && item.children.length > 0;

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

        const isParentActive = activeSection === item.id;

        if (isCollapsed) {
          return (
            <CollapsedGroupItem
              key={item.id}
              item={item as NavItemWithChildren}
              activeSection={activeSection}
              activeSubTab={activeSubTab}
              isActive={isParentActive}
              onNavigate={onNavigate}
            />
          );
        }

        return (
          <SidebarMenuItem key={item.id}>
            <Collapsible
              defaultOpen={isParentActive}
              className="group/collapsible"
            >
              <CollapsibleTrigger asChild>
                <SidebarMenuButton tooltip={item.label} isActive={isParentActive && !activeSubTab}>
                  <Icon />
                  <span>{item.label}</span>
                  <ChevronRight className="ms-auto transition-transform rtl:rotate-180 group-data-[state=open]/collapsible:rotate-90" />
                </SidebarMenuButton>
              </CollapsibleTrigger>
              <CollapsibleContent>
                <SidebarMenuSub>
                  {item.children!.map((child) => (
                    <SidebarMenuSubItem key={child.id}>
                      <SidebarMenuSubButton
                        isActive={isParentActive && activeSubTab === child.id}
                        onClick={() => onNavigate(item.id + '/' + child.id)}
                      >
                        <span>{child.label}</span>
                      </SidebarMenuSubButton>
                    </SidebarMenuSubItem>
                  ))}
                </SidebarMenuSub>
              </CollapsibleContent>
            </Collapsible>
          </SidebarMenuItem>
        );
      })}
    </SidebarMenu>
  );
}

export function AppShell({ activeSection, activeSubTab, onNavigate, version, children }: AppShellProps) {
  return (
    <SidebarProvider defaultOpen={SIDEBAR_DEFAULT_OPEN}>
      <Sidebar collapsible="icon">
        <SidebarHeader className="h-14 px-5 justify-center group-data-[collapsible=icon]:px-2">
          <div className="flex items-center gap-3 group-data-[collapsible=icon]:justify-center group-data-[collapsible=icon]:gap-0">
            <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-md bg-primary">
              <Logo className="h-6 w-6 text-primary-foreground" />
            </div>
            <div className="flex flex-col group-data-[collapsible=icon]:hidden">
              <span className="text-lg font-extrabold tracking-tight leading-none">{'WSMS'}</span>
            </div>
          </div>
        </SidebarHeader>
        <SidebarContent>
          <SidebarGroup>
            <SidebarGroupContent>
              <NavMenu activeSection={activeSection} activeSubTab={activeSubTab} onNavigate={onNavigate} items={NAV_ITEMS} />
            </SidebarGroupContent>
          </SidebarGroup>
        </SidebarContent>
      </Sidebar>

      <SidebarInset>
        <header className="flex h-14 items-center justify-end gap-2 border-b-2 border-foreground bg-sidebar px-6">
          <SidebarTrigger className="-ms-1 me-auto" />
          <Button variant="ghost" size="sm"
            className="bg-primary/10 text-xs font-medium text-primary hover:bg-primary/15 hover:text-primary">
            <Sparkles className="size-3" />
            {__('Go Premium', 'wp-sms')}
          </Button>
          <Button variant="ghost" size="icon-sm" className="relative text-muted-foreground hover:text-foreground">
            <Bell className="size-4" />
            <span className="absolute top-1 end-1.5 size-2 rounded-full bg-destructive ring-2 ring-background" />
            <span className="sr-only">{__('Notifications', 'wp-sms')}</span>
          </Button>
        </header>
        <div key={`${activeSection}/${activeSubTab ?? ''}`} className="animate-fade-up p-7">
          {children}
        </div>
        <SaveBar />
        <footer className="mt-auto border-t-2 border-border/50 px-6 py-3">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="flex h-7 w-7 shrink-0 items-center justify-center rounded-md bg-primary/[0.08]">
                <Logo className="h-4 w-4 text-primary/60" />
              </div>
              <div className="flex items-center gap-2">
                <span className="text-[13px] font-medium tracking-tight text-muted-foreground">{'WSMS'}</span>
                <span className="rounded border border-border px-1.5 py-px font-mono text-[9px] tracking-wide text-muted-foreground">{version}</span>
                <span className="mx-0.5 text-muted-foreground" aria-hidden="true">·</span>
                <span className="text-[11px] text-muted-foreground">
                  {__('by', 'wp-sms')}{' '}
                  <a href="https://veronalabs.com" target="_blank" rel="noopener noreferrer" className="font-medium text-muted-foreground transition-colors hover:text-primary">
                    {'VeronaLabs'}
                  </a>
                </span>
              </div>
            </div>
            <nav className="flex items-center gap-4 text-[11px] text-muted-foreground">
              <a href="https://wsms.io/docs" target="_blank" rel="noopener noreferrer" className="transition-colors hover:text-foreground">{__('Docs', 'wp-sms')}</a>
              <a href="https://wsms.io/support" target="_blank" rel="noopener noreferrer" className="transition-colors hover:text-foreground">{__('Support', 'wp-sms')}</a>
              <a href="https://wsms.io" target="_blank" rel="noopener noreferrer" className="transition-colors hover:text-foreground">{'wsms.io'}</a>
            </nav>
          </div>
        </footer>
      </SidebarInset>
    </SidebarProvider>
  );
}
