import { useMemo } from 'react';
import { getConfig } from '@/lib/api';

/** Section → capability key mapping. Must stay in sync with AccessManager::SECTION_CAPS in PHP. */
const SECTION_VIEW_CAPS: Record<string, string | null> = {
  dashboard: 'wsms_view_dashboard',
  audience: 'wsms_view_audience',
  campaigns: 'wsms_view_campaigns',
  automation: 'wsms_view_automation',
  channels: 'wsms_view_channels',
  identity: 'wsms_view_identity',
  monitoring: 'wsms_view_monitoring',
  settings: null,
};

const SECTION_MANAGE_CAPS: Record<string, string | null> = {
  dashboard: null,
  audience: 'wsms_manage_audience',
  campaigns: 'wsms_manage_campaigns',
  automation: 'wsms_manage_automation',
  channels: 'wsms_manage_channels',
  identity: 'wsms_manage_identity',
  monitoring: 'wsms_manage_monitoring',
  settings: 'wsms_manage_settings',
};

export function useCapabilities() {
  const caps = getConfig().capabilities;

  return useMemo(() => {
    function canViewSection(section: string): boolean {
      if (caps.is_admin) return true;

      const viewCap = SECTION_VIEW_CAPS[section];
      if (viewCap && caps[viewCap as keyof typeof caps]) return true;

      // manage implies view
      const manageCap = SECTION_MANAGE_CAPS[section];
      if (manageCap && caps[manageCap as keyof typeof caps]) return true;

      return false;
    }

    function canManageSection(section: string): boolean {
      if (caps.is_admin) return true;

      const manageCap = SECTION_MANAGE_CAPS[section];
      if (!manageCap) return false;

      return !!caps[manageCap as keyof typeof caps];
    }

    function hasAnyAccess(): boolean {
      if (caps.is_admin) return true;
      return Object.entries(caps).some(([key, val]) => key !== 'is_admin' && val === true);
    }

    return { caps, canViewSection, canManageSection, hasAnyAccess };
  }, [caps]);
}
