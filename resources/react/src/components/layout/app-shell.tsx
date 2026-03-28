import { useCallback, useEffect, useRef, useState, type ReactNode } from 'react';
import { LogIn, Paintbrush, ChevronRight, Plug, BarChart3, Megaphone, Workflow, Users, Radio, Settings2, MessageSquare, ClipboardList, Webhook, Bell, Sparkles, Contact, FileText, type LucideIcon } from 'lucide-react';
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

export interface NavSection {
  label: string;
  items: readonly NavItem[];
}

// --- Navigation structure ---

export const NAV_SECTIONS: readonly NavSection[] = [
  {
    label: 'Messaging',
    items: [
      { id: 'campaigns', label: 'Campaigns', icon: Megaphone },
      { id: 'flows', label: 'Flows', icon: Workflow },
      { id: 'contacts', label: 'Contacts', icon: Users },
      {
        id: 'messaging-button', label: 'Messaging Button', icon: MessageSquare,
        children: [
          { id: 'mb-appearance', label: 'Appearance' },
          { id: 'mb-pages', label: 'Pages' },
          { id: 'mb-team', label: 'Team' },
          { id: 'mb-display-rules', label: 'Display Rules' },
        ],
      },
    ],
  },
  {
    label: 'Authentication',
    items: [
      { id: 'channels', label: 'Channels', icon: LogIn },
      { id: 'registration-forms', label: 'Registration Forms', icon: ClipboardList },
      { id: 'profile-fields', label: 'Profile Fields', icon: Contact },
      { id: 'templates', label: 'Templates', icon: FileText },
    ],
  },
  {
    label: 'Platform',
    items: [
      { id: 'gateways', label: 'Gateways', icon: Radio },
      { id: 'integrations', label: 'Integrations', icon: Plug },
      { id: 'webhooks', label: 'Webhooks', icon: Webhook },
      { id: 'branding', label: 'Branding', icon: Paintbrush },
      { id: 'monitoring', label: 'Monitoring', icon: BarChart3 },
      {
        id: 'settings', label: 'Settings', icon: Settings2,
        children: [
          { id: 's-general', label: 'General' },
          { id: 's-security', label: 'Security' },
        ],
      },
    ],
  },
];

/** Flat list of all nav items across all sections. */
export const NAV_ITEMS: readonly NavItem[] = NAV_SECTIONS.flatMap(s => s.items);

/** Derive parent section from a child section ID, using NAV_ITEMS as the source of truth. */
export function getParentSection(sectionId: string): string {
  for (const item of NAV_ITEMS) {
    if (item.children?.some((c) => c.id === sectionId)) {
      return item.id;
    }
  }
  return sectionId;
}

/** All valid section IDs (both parent-level and child-level). */
export const VALID_SECTIONS: Set<string> = new Set(
  NAV_ITEMS.flatMap((item) =>
    item.children ? item.children.map((c) => c.id) : [item.id]
  )
);

// --- Sidebar components ---

interface AppShellProps {
  activeSection: string;
  onNavigate: (section: string) => void;
  version: string;
  children: ReactNode;
}

type NavItemWithChildren = NavItem & { children: readonly NavChild[] };

function CollapsedGroupItem({ item, activeSection, isActive, onNavigate }: {
  item: NavItemWithChildren;
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

function NavMenu({ activeSection, onNavigate, items }: { activeSection: string; onNavigate: (s: string) => void; items: readonly NavItem[] }) {
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

        const isParentActive =
          activeSection === item.id ||
          item.children!.some((c) => c.id === activeSection);

        if (isCollapsed) {
          return (
            <CollapsedGroupItem
              key={item.id}
              item={item as NavItemWithChildren}
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
                  {item.children!.map((child) => (
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

export function AppShell({ activeSection, onNavigate, version, children }: AppShellProps) {
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
            </div>
          </div>
        </SidebarHeader>
        <SidebarContent>
          {NAV_SECTIONS.map((section) => (
            <SidebarGroup key={section.label}>
              <SidebarGroupLabel>{section.label}</SidebarGroupLabel>
              <SidebarGroupContent>
                <NavMenu activeSection={activeSection} onNavigate={onNavigate} items={section.items} />
              </SidebarGroupContent>
            </SidebarGroup>
          ))}
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
        <footer className="mt-auto border-t-2 border-border/40 px-6 py-3">
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
