import { useState, useEffect, useCallback, useRef } from 'react';
import { __ } from '@wordpress/i18n';
import { api, type MutationResponse } from '@/lib/api';
import { isAbortError } from '@/lib/error-utils';
import { usePolling } from '@/hooks/use-polling';

export interface MigrationStepProgress {
  status: string;
  total_items: number;
  processed_items: number;
  migrated_count: number;
  skipped_count: number;
  failed_count: number;
  conflict_count: number;
  last_cursor: string | null;
  errors: Array<{ user_id?: number; phone?: string; reason: string }>;
}

export interface MigrationStatus {
  status: string;
  migrator_id: string;
  total_items: number;
  processed_items: number;
  percent: number;
  steps: Record<string, MigrationStepProgress>;
  started_at: string | null;
  completed_at: string | null;
  created_by: number;
  created_by_name?: string;
}

export interface DetectionResult {
  id: string;
  installed: boolean;
  active: boolean;
  version: string | null;
  data_found: boolean;
  user_count: number;
  summary: string[];
}

export interface PreviewStep {
  label: string;
  description: string;
  total_items: number;
  ready_to_migrate: number;
  conflicts: number;
  skippable: number;
  invalid_data: number;
  sample_conflicts: unknown[];
  sample_errors: Array<{ user_id: number; phone?: string; reason: string }>;
  warnings: string[];
}

export interface PreflightCheck {
  label: string;
  status: 'pass' | 'warning' | 'fail';
  detail: string;
}

export interface PreflightResult {
  checks: Record<string, PreflightCheck>;
  can_start: boolean;
}

export interface MigrationError {
  step: string;
  user_id?: number;
  phone?: string;
  reason: string;
}

export function useMigration() {
  const [available, setAvailable] = useState<DetectionResult[]>([]);
  const [preview, setPreview] = useState<Record<string, PreviewStep> | null>(null);
  const [preflight, setPreflight] = useState<PreflightResult | null>(null);
  const [status, setStatus] = useState<MigrationStatus | null>(null);
  const [errors, setErrors] = useState<MigrationError[]>([]);
  const [loading, setLoading] = useState(true);
  const [actionLoading, setActionLoading] = useState(false);
  const abortRef = useRef<AbortController>(undefined);

  const isRunning = status?.status === 'running';

  // Fetch available migrators.
  const fetchAvailable = useCallback(async () => {
    try {
      const res = await api.get<MutationResponse<DetectionResult[]>>('migration/available');
      setAvailable(res.data);
    } catch (e) {
      if (!isAbortError(e)) {
        setAvailable([]);
      }
    }
  }, []);

  // Fetch migration status.
  const fetchStatus = useCallback(async () => {
    try {
      const res = await api.get<MutationResponse<MigrationStatus | null>>('migration/status');
      setStatus(res.data);
    } catch (e) {
      if (!isAbortError(e)) {
        setStatus(null);
      }
    }
  }, []);

  // Initial load.
  useEffect(() => {
    const load = async () => {
      setLoading(true);
      await Promise.all([fetchAvailable(), fetchStatus()]);
      setLoading(false);
    };
    void load();
  }, [fetchAvailable, fetchStatus]);

  // Poll status while running (every 5 seconds).
  usePolling(fetchStatus, 5000, isRunning);

  // Fetch preview for a migrator.
  const fetchPreview = useCallback(async (migratorId: string, options?: Record<string, string>) => {
    abortRef.current?.abort();
    const controller = new AbortController();
    abortRef.current = controller;

    setActionLoading(true);
    try {
      const params = new URLSearchParams(options);
      const res = await api.get<MutationResponse<Record<string, PreviewStep>>>(
        `migration/${migratorId}/preview?${params.toString()}`,
        { signal: controller.signal },
      );
      setPreview(res.data);
    } catch (e) {
      if (!isAbortError(e)) {
        setPreview(null);
      }
    } finally {
      setActionLoading(false);
    }
  }, []);

  // Fetch preflight checks.
  const fetchPreflight = useCallback(async (migratorId: string) => {
    setActionLoading(true);
    try {
      const res = await api.get<MutationResponse<PreflightResult>>(`migration/${migratorId}/preflight`);
      setPreflight(res.data);
    } catch (e) {
      if (!isAbortError(e)) {
        setPreflight(null);
      }
    } finally {
      setActionLoading(false);
    }
  }, []);

  // Start migration.
  const start = useCallback(async (migratorId: string, options: {
    conflict_resolution?: string;
    default_country?: string;
    wc_sync?: boolean;
    dry_run?: boolean;
    selected_settings?: string[];
  }) => {
    setActionLoading(true);
    try {
      const res = await api.post<MutationResponse<MigrationStatus>>(`migration/${migratorId}/start`, options);
      setStatus(res.data);
    } finally {
      setActionLoading(false);
    }
  }, []);

  // Pause.
  const pause = useCallback(async () => {
    setActionLoading(true);
    try {
      const res = await api.post<MutationResponse<MigrationStatus>>('migration/pause', {});
      setStatus(res.data);
    } finally {
      setActionLoading(false);
    }
  }, []);

  // Resume.
  const resume = useCallback(async () => {
    setActionLoading(true);
    try {
      const res = await api.post<MutationResponse<MigrationStatus>>('migration/resume', {});
      setStatus(res.data);
    } finally {
      setActionLoading(false);
    }
  }, []);

  // Rollback.
  const rollback = useCallback(async () => {
    setActionLoading(true);
    try {
      await api.post<MutationResponse<{ rolled_back: number; failed: number }>>('migration/rollback', {});
      await fetchStatus();
    } finally {
      setActionLoading(false);
    }
  }, [fetchStatus]);

  // Switchover.
  const switchover = useCallback(async (migratorId: string) => {
    setActionLoading(true);
    try {
      const res = await api.post<MutationResponse<{ message: string; affected_pages: unknown[]; redirects: unknown[] }>>(
        `migration/${migratorId}/switchover`,
        {},
      );
      await fetchStatus();
      return res.data;
    } finally {
      setActionLoading(false);
    }
  }, [fetchStatus]);

  // Fetch errors.
  const fetchErrors = useCallback(async () => {
    try {
      const res = await api.get<MutationResponse<MigrationError[]>>('migration/errors');
      setErrors(res.data);
    } catch {
      setErrors([]);
    }
  }, []);

  return {
    available,
    preview,
    preflight,
    status,
    errors,
    loading,
    actionLoading,
    isRunning,
    fetchAvailable,
    fetchPreview,
    fetchPreflight,
    fetchStatus,
    fetchErrors,
    start,
    pause,
    resume,
    rollback,
    switchover,
  };
}
