import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, waitFor } from '@testing-library/react';
import { http, HttpResponse } from 'msw';
import { server } from '../mocks/server';
import { OnboardingWizard } from '@/pages/onboarding';
import type { OnboardingState, OnboardingStatus } from '@/lib/api';

// useGateways hits /gateways on mount and isn't relevant to the mount-effect
// behavior under test — stub it so we don't need to wire MSW handlers for it.
vi.mock('@/hooks/use-gateways', () => ({
  useGateways: () => ({
    gateways: [],
    loading: false,
    updateConfig: vi.fn(),
    testGateway: vi.fn(),
    testConnection: vi.fn(),
    getCredit: vi.fn(),
    refetch: vi.fn(),
  }),
}));

const BASE_URL = 'https://example.com/wp-json/wsms/v1';

function buildState(status: OnboardingStatus, current_step = 0): OnboardingState {
  return {
    status,
    goals: [],
    current_step,
    skipped_steps: [],
    checklist_dismissed: false,
    migration_deferred: false,
    completed_at: null,
    version: '8.0',
  };
}

function setInitialOnboarding(state: OnboardingState | null) {
  const settings = (window as unknown as {
    wpSmsSettings: {
      onboarding: OnboardingState | null;
      detectedIntegrations: unknown[];
      detectedMigrations: unknown[];
    };
  }).wpSmsSettings;
  settings.onboarding = state;
  // WelcomeStep reads these via getConfig() and calls .filter() on them.
  settings.detectedIntegrations = [];
  settings.detectedMigrations = [];
}

/**
 * Wire MSW with a mutable backing store and a spy on PUT /onboarding so each
 * test can assert exactly what status transition was sent to the server.
 */
function installOnboardingHandlers(initial: OnboardingState) {
  let store = { ...initial };
  const putSpy = vi.fn<[Partial<OnboardingState>], void>();

  server.use(
    http.get(`${BASE_URL}/onboarding`, () =>
      HttpResponse.json({ success: true, data: { state: store, checklist: [] } }),
    ),
    http.put(`${BASE_URL}/onboarding`, async ({ request }) => {
      const body = (await request.json()) as Partial<OnboardingState>;
      putSpy(body);
      store = { ...store, ...body };
      return HttpResponse.json({ success: true, data: { state: store, checklist: [] } });
    }),
  );

  return { putSpy, getStore: () => store };
}

describe('OnboardingWizard — mount-time status transition', () => {
  const noop = () => undefined;

  beforeEach(() => {
    setInitialOnboarding(buildState('pending'));
  });

  afterEach(() => {
    setInitialOnboarding(null);
  });

  it('PUTs status=in_progress on mount when initial status is pending', async () => {
    const { putSpy } = installOnboardingHandlers(buildState('pending'));

    render(<OnboardingWizard onComplete={noop} onNavigate={noop} />);

    // The mount-effect fires once and PUTs { status: 'in_progress' }.
    // Wait for it because updateState is async (calls REST through fetch).
    await waitFor(() => {
      expect(putSpy).toHaveBeenCalledTimes(1);
    });

    expect(putSpy).toHaveBeenCalledWith({ status: 'in_progress' });
  });

  it('does NOT PUT on mount when status is already in_progress (idempotent re-entry)', async () => {
    setInitialOnboarding(buildState('in_progress', 1));
    const { putSpy } = installOnboardingHandlers(buildState('in_progress', 1));

    render(<OnboardingWizard onComplete={noop} onNavigate={noop} />);

    // Give any pending effects time to flush — none should have written.
    await new Promise((r) => setTimeout(r, 50));

    expect(putSpy).not.toHaveBeenCalled();
  });

  it('does NOT PUT on mount when status is completed (re-rendering past wizard)', async () => {
    setInitialOnboarding(buildState('completed'));
    const { putSpy } = installOnboardingHandlers(buildState('completed'));

    render(<OnboardingWizard onComplete={noop} onNavigate={noop} />);

    await new Promise((r) => setTimeout(r, 50));

    expect(putSpy).not.toHaveBeenCalled();
  });

  it('does NOT PUT on mount when status is skipped', async () => {
    setInitialOnboarding(buildState('skipped'));
    const { putSpy } = installOnboardingHandlers(buildState('skipped'));

    render(<OnboardingWizard onComplete={noop} onNavigate={noop} />);

    await new Promise((r) => setTimeout(r, 50));

    expect(putSpy).not.toHaveBeenCalled();
  });
});
