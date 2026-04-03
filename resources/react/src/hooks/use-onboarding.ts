import { useState, useEffect, useCallback, useRef } from 'react';
import {
  api,
  getConfig,
  type OnboardingState,
  type OnboardingGoal,
  type ChecklistItem,
  type OnboardingResponse,
} from '@/lib/api';
import { isAbortError } from '@/lib/error-utils';

export interface UseOnboardingReturn {
  state: OnboardingState | null;
  checklist: ChecklistItem[];
  loading: boolean;
  updating: boolean;
  updateState: (data: Partial<OnboardingState>) => Promise<void>;
  resetWizard: () => Promise<void>;
  refetch: () => void;
}

export function useOnboarding(): UseOnboardingReturn {
  const config = getConfig();
  const [state, setState] = useState<OnboardingState | null>(config.onboarding);
  const [checklist, setChecklist] = useState<ChecklistItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [updating, setUpdating] = useState(false);
  const abortRef = useRef<AbortController>();

  const fetchState = useCallback(async () => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setLoading(true);
    try {
      const res = await api.get<OnboardingResponse>('onboarding', { signal: controller.signal });
      if (!controller.signal.aborted) {
        setState(res.data.state);
        setChecklist(res.data.checklist);
      }
    } catch (e) {
      if (isAbortError(e)) return;
    } finally {
      if (!controller.signal.aborted) {
        setLoading(false);
      }
    }
  }, []);

  const updateState = useCallback(async (data: Partial<OnboardingState>) => {
    setUpdating(true);
    try {
      const res = await api.put<OnboardingResponse>('onboarding', data);
      setState(res.data.state);
      setChecklist(res.data.checklist);
    } finally {
      setUpdating(false);
    }
  }, []);

  const resetWizard = useCallback(async () => {
    setUpdating(true);
    try {
      const res = await api.post<OnboardingResponse>('onboarding/reset', null);
      setState(res.data.state);
      setChecklist(res.data.checklist);
    } finally {
      setUpdating(false);
    }
  }, []);

  useEffect(() => {
    void fetchState();
    return () => { abortRef.current?.abort(); };
  }, [fetchState]);

  return { state, checklist, loading, updating, updateState, resetWizard, refetch: fetchState };
}

/**
 * Lightweight check without fetching — uses the data already in wpSmsSettings.
 */
export function getOnboardingStatus(): OnboardingState | null {
  return getConfig().onboarding;
}

export function shouldShowOnboarding(): boolean {
  const state = getOnboardingStatus();
  return state?.status === 'pending';
}
