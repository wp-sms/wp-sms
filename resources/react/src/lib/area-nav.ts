import { NAV_ITEMS } from '@/components/layout/app-shell';

export type NavItem = (typeof NAV_ITEMS)[number];
export type Area = 'auth' | 'messaging';

interface AreaConfig {
  sections: string[];
  defaultSection: string;
}

const AREA_CONFIG: Record<Area, AreaConfig> = {
  auth: {
    sections: ['general', 'authentication', 'security', 'integrations', 'branding', 'monitoring'],
    defaultSection: 'channels',
  },
  messaging: {
    sections: ['campaigns', 'flows', 'contacts', 'gateways', 'apps', 'message-logs', 'messaging-button', 'subscription-forms', 'settings'],
    defaultSection: 'flows',
  },
};

export function getNavItemsForArea(area: Area): NavItem[] {
  const config = AREA_CONFIG[area];
  return NAV_ITEMS.filter((item) => config.sections.includes(item.id));
}

export function getDefaultSection(area: Area): string {
  return AREA_CONFIG[area].defaultSection;
}

export function getValidSections(items: NavItem[]): Set<string> {
  return new Set(
    items.flatMap((item) =>
      'children' in item ? item.children.map((c) => c.id) : [item.id]
    )
  );
}
