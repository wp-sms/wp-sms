import { type AuthSettings, type SettingsResponse } from '@/lib/api';
import { DEFAULTS } from '@/lib/constants';
import { useResourceSettings, type SaveStatus } from '@/hooks/use-resource-settings';

export type { SaveStatus };

export interface UseSettingsReturn {
  settings: Required<AuthSettings>;
  updateSetting: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
  isDirty: boolean;
  saveStatus: SaveStatus;
  save: () => Promise<void>;
  loading: boolean;
  error: string | null;
}

function buildDiffPayload(draft: Required<AuthSettings>, saved: Required<AuthSettings>): Partial<AuthSettings> {
  const payload: Partial<AuthSettings> = {};
  for (const key of Object.keys(draft) as (keyof AuthSettings)[]) {
    if (JSON.stringify(draft[key]) !== JSON.stringify(saved[key])) {
      payload[key] = draft[key] as AuthSettings[typeof key];
    }
  }
  return payload;
}

export function useSettings(): UseSettingsReturn {
  const result = useResourceSettings<Required<AuthSettings>, SettingsResponse>({
    endpoint: 'auth/admin/settings',
    defaults: DEFAULTS,
    extractSettings: (res) => res.settings,
    buildPayload: buildDiffPayload,
  });

  return {
    settings: result.settings,
    updateSetting: result.updateSetting as <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void,
    isDirty: result.isDirty,
    saveStatus: result.saveStatus,
    save: result.save,
    loading: result.loading,
    error: result.error,
  };
}
