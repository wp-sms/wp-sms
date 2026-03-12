import { describe, it, expect } from 'vitest';
import { renderHook, act, waitFor } from '@testing-library/react';
import { useSettings } from '@/hooks/use-settings';

describe('useSettings', () => {
  it('fetches settings on mount', async () => {
    const { result } = renderHook(() => useSettings());

    expect(result.current.loading).toBe(true);

    await waitFor(() => {
      expect(result.current.loading).toBe(false);
    });

    expect(result.current.settings.password.enabled).toBe(true);
    expect(result.current.settings.email.enabled).toBe(true);
    expect(result.current.error).toBeNull();
  });

  it('tracks dirty state when settings change', async () => {
    const { result } = renderHook(() => useSettings());

    await waitFor(() => {
      expect(result.current.loading).toBe(false);
    });

    expect(result.current.isDirty).toBe(false);

    act(() => {
      result.current.updateSetting('phone', { ...result.current.settings.phone, code_length: 8 });
    });

    expect(result.current.isDirty).toBe(true);
    expect(result.current.settings.phone.code_length).toBe(8);
  });

  it('saves changed settings and resets dirty state', async () => {
    const { result } = renderHook(() => useSettings());

    await waitFor(() => {
      expect(result.current.loading).toBe(false);
    });

    act(() => {
      result.current.updateSetting('phone', { ...result.current.settings.phone, enabled: true });
    });

    expect(result.current.isDirty).toBe(true);

    await act(async () => {
      await result.current.save();
    });

    expect(result.current.isDirty).toBe(false);
    expect(result.current.saveStatus).toBe('saved');
    expect(result.current.settings.phone.enabled).toBe(true);
  });

  it('saves only specified keys when keys parameter is provided', async () => {
    const { result } = renderHook(() => useSettings());

    await waitFor(() => {
      expect(result.current.loading).toBe(false);
    });

    act(() => {
      result.current.updateSetting('phone', { ...result.current.settings.phone, code_length: 8 });
      result.current.updateSetting('email', { ...result.current.settings.email, code_length: 4 });
    });

    await act(async () => {
      await result.current.save(['phone']);
    });

    expect(result.current.saveStatus).toBe('saved');
  });

  it('resets save status after timeout', async () => {
    const { result } = renderHook(() => useSettings());

    await waitFor(() => {
      expect(result.current.loading).toBe(false);
    });

    act(() => {
      result.current.updateSetting('phone', { ...result.current.settings.phone, code_length: 8 });
    });

    await act(async () => {
      await result.current.save();
    });

    expect(result.current.saveStatus).toBe('saved');
  });
});
