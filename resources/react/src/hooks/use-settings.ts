import { useState, useEffect, useCallback, useRef, useMemo } from 'react';
import { toast } from 'sonner';
import { api, type AuthSettings, type SettingsResponse } from '@/lib/api';
import { DEFAULTS } from '@/lib/constants';

export type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

export interface UseSettingsReturn {
  settings: Required<AuthSettings>;
  updateSetting: <K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => void;
  isDirty: boolean;
  saveStatus: SaveStatus;
  save: (keys?: (keyof AuthSettings)[]) => Promise<void>;
  loading: boolean;
  error: string | null;
}

export function useSettings(): UseSettingsReturn {
  const [savedSettings, setSavedSettings] = useState<Required<AuthSettings>>(DEFAULTS);
  const [draftSettings, setDraftSettings] = useState<Required<AuthSettings>>(DEFAULTS);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
  const statusTimer = useRef<ReturnType<typeof setTimeout>>();
  const draftRef = useRef(draftSettings);
  const savedRef = useRef(savedSettings);

  draftRef.current = draftSettings;
  savedRef.current = savedSettings;

  useEffect(() => {
    let cancelled = false;

    api.get<SettingsResponse>('auth/admin/settings')
      .then((res) => {
        if (cancelled) return;
        const merged = { ...DEFAULTS, ...res.settings };
        setSavedSettings(merged);
        setDraftSettings(merged);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        const message = err instanceof Error ? err.message : 'Failed to load settings';
        setError(message);
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => { cancelled = true; };
  }, []);

  const updateSetting = useCallback(<K extends keyof AuthSettings>(key: K, value: AuthSettings[K]) => {
    setDraftSettings((prev) => ({ ...prev, [key]: value }));
    setSaveStatus((prev) => prev === 'idle' ? prev : 'idle');
  }, []);

  const isDirty = useMemo(() => {
    for (const key of Object.keys(savedSettings) as (keyof AuthSettings)[]) {
      if (savedSettings[key] !== draftSettings[key]) return true;
    }
    return false;
  }, [savedSettings, draftSettings]);

  const save = useCallback(async (keys?: (keyof AuthSettings)[]) => {
    const draft = draftRef.current;
    const saved = savedRef.current;

    setSaveStatus('saving');
    clearTimeout(statusTimer.current);

    try {
      const payload: Partial<AuthSettings> = {};

      if (keys) {
        for (const key of keys) {
          payload[key] = draft[key] as AuthSettings[typeof key];
        }
      } else {
        for (const key of Object.keys(draft) as (keyof AuthSettings)[]) {
          if (JSON.stringify(draft[key]) !== JSON.stringify(saved[key])) {
            payload[key] = draft[key] as AuthSettings[typeof key];
          }
        }
      }

      const res = await api.put<SettingsResponse>('auth/admin/settings', payload);
      const merged = { ...DEFAULTS, ...res.settings };
      setSavedSettings(merged);
      setDraftSettings(merged);
      setSaveStatus('saved');
      toast.success('Settings saved');

      statusTimer.current = setTimeout(() => setSaveStatus('idle'), 3000);
    } catch {
      setSaveStatus('error');
      toast.error('Failed to save settings');
      statusTimer.current = setTimeout(() => setSaveStatus('idle'), 5000);
    }
  }, []);

  useEffect(() => {
    return () => clearTimeout(statusTimer.current);
  }, []);

  return { settings: draftSettings, updateSetting, isDirty, saveStatus, save, loading, error };
}
