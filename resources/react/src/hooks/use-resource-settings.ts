import { useState, useEffect, useCallback, useRef, useMemo } from 'react';
import { __ } from '@wordpress/i18n';
import { toast } from 'sonner';
import { api } from '@/lib/api';
import { deepMerge } from '@/lib/utils';
import { getApiErrorMessage, getErrorMessage } from '@/lib/error-utils';

export type SaveStatus = 'idle' | 'saving' | 'saved' | 'error';

interface ResourceSettingsConfig<T extends Record<string, unknown>, R> {
  /** REST endpoint for GET/PUT (e.g. 'auth/admin/settings'). */
  endpoint: string;
  /** Default values used as a fallback for missing keys. */
  defaults: T;
  /** Extract the settings object from the API response. */
  extractSettings: (response: R) => Partial<T>;
  /** Build the payload to send on save. Receives draft and saved state. */
  buildPayload?: (draft: T, saved: T) => Partial<T>;
}

export interface UseResourceSettingsReturn<T, R = unknown> {
  settings: T;
  updateSetting: (key: string, value: unknown) => void;
  isDirty: boolean;
  saveStatus: SaveStatus;
  save: () => Promise<void>;
  loading: boolean;
  error: string | null;
  rawResponse: R | undefined;
}

/**
 * Generic hook for loading, editing, and saving a REST-backed settings resource.
 *
 * Provides draft/saved state tracking, dirty detection, optimistic save with
 * toast notifications, and a beforeunload guard for unsaved changes.
 */
export function useResourceSettings<T extends Record<string, unknown>, R>(
  config: ResourceSettingsConfig<T, R>,
): UseResourceSettingsReturn<T, R> {
  const { endpoint, defaults, extractSettings, buildPayload } = config;

  const [savedSettings, setSavedSettings] = useState<T>(defaults);
  const [draftSettings, setDraftSettings] = useState<T>(defaults);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [saveStatus, setSaveStatus] = useState<SaveStatus>('idle');
  const [rawResponse, setRawResponse] = useState<R>();
  const statusTimer = useRef<ReturnType<typeof setTimeout>>();
  const draftRef = useRef(draftSettings);
  const savedRef = useRef(savedSettings);

  draftRef.current = draftSettings;
  savedRef.current = savedSettings;

  useEffect(() => {
    let cancelled = false;

    api.get<R>(endpoint)
      .then((res) => {
        if (cancelled) return;
        setRawResponse(res);
        const merged = deepMerge(defaults, extractSettings(res));
        setSavedSettings(merged);
        setDraftSettings(merged);
      })
      .catch((err: unknown) => {
        if (cancelled) return;
        setError(getErrorMessage(err, __('Failed to load settings', 'wp-sms')));
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });

    return () => { cancelled = true; };
  // eslint-disable-next-line react-hooks/exhaustive-deps -- config is stable at mount
  }, []);

  const updateSetting = useCallback((key: string, value: unknown) => {
    setDraftSettings((prev) => {
      const keys = key.split('.');
      if (keys.length === 1) {
        return { ...prev, [key]: value };
      }
      const result = { ...prev };
      let current: Record<string, unknown> = result;
      for (let i = 0; i < keys.length - 1; i++) {
        current[keys[i]] = { ...(current[keys[i]] as Record<string, unknown>) };
        current = current[keys[i]] as Record<string, unknown>;
      }
      current[keys[keys.length - 1]] = value;
      return result as T;
    });
    // Reset save status when the user edits after a save/error
    setSaveStatus('idle');
  }, []);

  const isDirty = useMemo(() => {
    return JSON.stringify(savedSettings) !== JSON.stringify(draftSettings);
  }, [savedSettings, draftSettings]);

  const save = useCallback(async () => {
    const draft = draftRef.current;
    const saved = savedRef.current;

    setSaveStatus('saving');
    clearTimeout(statusTimer.current);

    try {
      const payload = buildPayload ? buildPayload(draft, saved) : draft;
      const res = await api.put<R>(endpoint, payload);
      const merged = deepMerge(defaults, extractSettings(res));
      setSavedSettings(merged);
      setDraftSettings(merged);
      setSaveStatus('saved');
      toast.success(__('Settings saved', 'wp-sms'));
      statusTimer.current = setTimeout(() => setSaveStatus('idle'), 3000);
    } catch (err: unknown) {
      setSaveStatus('error');
      toast.error(getApiErrorMessage(err, __('Failed to save settings', 'wp-sms')));
      statusTimer.current = setTimeout(() => setSaveStatus('idle'), 5000);
    }
  // eslint-disable-next-line react-hooks/exhaustive-deps -- config is stable at mount
  }, []);

  useEffect(() => {
    return () => clearTimeout(statusTimer.current);
  }, []);

  useEffect(() => {
    const handler = (e: BeforeUnloadEvent) => {
      if (JSON.stringify(draftRef.current) !== JSON.stringify(savedRef.current)) {
        e.preventDefault();
      }
    };
    window.addEventListener('beforeunload', handler);
    return () => window.removeEventListener('beforeunload', handler);
  }, []);

  return { settings: draftSettings, updateSetting, isDirty, saveStatus, save, loading, error, rawResponse };
}
