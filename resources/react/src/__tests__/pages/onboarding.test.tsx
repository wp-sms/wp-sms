import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { server } from '../mocks/server';
import { OnboardingWizard } from '@/pages/onboarding';
import type { OnboardingState, OnboardingStatus } from '@/lib/api';
import { toast } from 'sonner';

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

// Spy on sonner toasts so the error-path tests can assert that users get
// feedback when the completion PUT fails.
vi.mock('sonner', () => ({
  toast: { error: vi.fn(), success: vi.fn(), info: vi.fn() },
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

/**
 * Regression coverage for the dashboard "stale resume notice" race:
 *
 * handleFinish / handleSkip used to fire-and-forget the status PUT and
 * immediately call onComplete(). The wizard then unmounted, the dashboard
 * mounted, and ContinueSetupCard's GET /onboarding raced the still-in-flight
 * PUT — frequently reading the old `in_progress` status and showing the
 * resume notice until a manual page reload.
 *
 * The fix: await updateState() inside both handlers so the server is
 * guaranteed to have the new status before navigation happens. These tests
 * pin that ordering by stalling the PUT response and asserting onComplete()
 * has NOT fired until the PUT resolves.
 */
describe('OnboardingWizard — completion handlers await PUT before navigating', () => {
  beforeEach(() => {
    setInitialOnboarding(buildState('in_progress', 3));
  });

  afterEach(() => {
    setInitialOnboarding(null);
  });

  /**
   * Wires MSW with a manually-resolved gate on PUT /onboarding so the test
   * can observe the in-flight window between "user clicked finish" and
   * "server has accepted the new status".
   */
  function installGatedHandlers(initial: OnboardingState) {
    let store = { ...initial };
    let releasePut: () => void = () => undefined;
    const putGate = new Promise<void>((resolve) => {
      releasePut = resolve;
    });
    const putSpy = vi.fn<[Partial<OnboardingState>], void>();

    server.use(
      http.get(`${BASE_URL}/onboarding`, () =>
        HttpResponse.json({ success: true, data: { state: store, checklist: [] } }),
      ),
      http.put(`${BASE_URL}/onboarding`, async ({ request }) => {
        const body = (await request.json()) as Partial<OnboardingState>;
        putSpy(body);
        store = { ...store, ...body };
        await putGate;
        return HttpResponse.json({ success: true, data: { state: store, checklist: [] } });
      }),
    );

    return { putSpy, releasePut };
  }

  it('handleFinish: onComplete is NOT called until the completion PUT resolves', async () => {
    const user = userEvent.setup();
    const onComplete = vi.fn();
    const { putSpy, releasePut } = installGatedHandlers(buildState('in_progress', 3));

    render(<OnboardingWizard onComplete={onComplete} onNavigate={() => undefined} />);

    // Wizard lands on CompleteStep (status=in_progress, current_step=3).
    const finishBtn = await screen.findByRole('button', { name: /go to dashboard/i });
    await user.click(finishBtn);

    // The PUT body has been observed by MSW, but the response is gated.
    await waitFor(() => {
      expect(putSpy).toHaveBeenCalledWith({ status: 'completed', current_step: 3 });
    });

    // Critical assertion: onComplete must NOT have fired yet — the await
    // inside handleFinish is keeping us parked on the PUT promise.
    expect(onComplete).not.toHaveBeenCalled();

    // Release the gate; now the await resolves and onComplete fires.
    releasePut();
    await waitFor(() => {
      expect(onComplete).toHaveBeenCalledTimes(1);
    });
  });

  it('handleSkip: onComplete is NOT called until the skip PUT resolves', async () => {
    const user = userEvent.setup();
    const onComplete = vi.fn();
    // Skip is exposed on steps 0–2; mount mid-wizard so the button is present
    // and we don't have to navigate through the goal-selection flow.
    setInitialOnboarding(buildState('in_progress', 1));
    const { putSpy, releasePut } = installGatedHandlers(buildState('in_progress', 1));

    render(<OnboardingWizard onComplete={onComplete} onNavigate={() => undefined} />);

    const skipBtn = await screen.findByRole('button', { name: /skip/i });
    await user.click(skipBtn);

    await waitFor(() => {
      expect(putSpy).toHaveBeenCalledWith({ status: 'skipped' });
    });

    expect(onComplete).not.toHaveBeenCalled();

    releasePut();
    await waitFor(() => {
      expect(onComplete).toHaveBeenCalledTimes(1);
    });
  });
});

/**
 * Error-path coverage for handleFinish / handleSkip.
 *
 * The await-before-navigate fix (see previous describe block) closed a stale
 * dashboard-notice race but opened a new failure mode: if the PUT rejects
 * (server 500, network error, lost auth), the handler's promise rejects
 * silently and `onComplete()` never fires — the user sits on the wizard with
 * zero feedback. The hardened handlers catch the rejection, surface a toast,
 * and re-enable the button so the user can retry.
 *
 * These tests also cover the double-click guard: without a `finishing` gate,
 * an impatient user could double-click Finish and fire two PUTs back-to-back.
 */
describe('OnboardingWizard — completion handlers error path', () => {
  beforeEach(() => {
    vi.mocked(toast.error).mockClear();
    setInitialOnboarding(buildState('in_progress', 3));
  });

  afterEach(() => {
    setInitialOnboarding(null);
  });

  /** MSW handlers that return 500 for every PUT /onboarding. */
  function installFailingHandlers(initial: OnboardingState) {
    const store = { ...initial };
    const putSpy = vi.fn<[Partial<OnboardingState>], void>();

    server.use(
      http.get(`${BASE_URL}/onboarding`, () =>
        HttpResponse.json({ success: true, data: { state: store, checklist: [] } }),
      ),
      http.put(`${BASE_URL}/onboarding`, async ({ request }) => {
        const body = (await request.json()) as Partial<OnboardingState>;
        putSpy(body);
        return HttpResponse.json(
          { success: false, error: { message: 'Server exploded' } },
          { status: 500 },
        );
      }),
    );

    return { putSpy };
  }

  it('handleFinish: shows toast and does NOT navigate when the PUT fails', async () => {
    const user = userEvent.setup();
    const onComplete = vi.fn();
    installFailingHandlers(buildState('in_progress', 3));

    render(<OnboardingWizard onComplete={onComplete} onNavigate={() => undefined} />);

    const finishBtn = await screen.findByRole('button', { name: /go to dashboard/i });
    await user.click(finishBtn);

    // Toast must fire so the user knows something went wrong.
    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledTimes(1);
    });

    // Navigation must NOT happen — the user is still parked on the wizard.
    expect(onComplete).not.toHaveBeenCalled();

    // Button must be re-enabled for a retry (the `finishing` flag reset).
    await waitFor(() => {
      expect(finishBtn).not.toBeDisabled();
    });
  });

  it('handleSkip: shows toast and does NOT navigate when the PUT fails', async () => {
    const user = userEvent.setup();
    const onComplete = vi.fn();
    setInitialOnboarding(buildState('in_progress', 1));
    installFailingHandlers(buildState('in_progress', 1));

    render(<OnboardingWizard onComplete={onComplete} onNavigate={() => undefined} />);

    const skipBtn = await screen.findByRole('button', { name: /skip/i });
    await user.click(skipBtn);

    await waitFor(() => {
      expect(toast.error).toHaveBeenCalledTimes(1);
    });

    expect(onComplete).not.toHaveBeenCalled();

    // Skip button re-enabled after the error so retry is possible.
    await waitFor(() => {
      expect(skipBtn).not.toBeDisabled();
    });
  });

  it('handleFinish: button disables during PUT to prevent double-submit', async () => {
    const user = userEvent.setup();
    const onComplete = vi.fn();

    // Gate the PUT so we can observe the disabled window — the button must
    // be disabled from the moment of the first click until the PUT resolves,
    // so an impatient second click has no target to fire against.
    let releasePut: () => void = () => undefined;
    const putGate = new Promise<void>((resolve) => {
      releasePut = resolve;
    });
    const putSpy = vi.fn<[Partial<OnboardingState>], void>();
    let store = buildState('in_progress', 3);

    server.use(
      http.get(`${BASE_URL}/onboarding`, () =>
        HttpResponse.json({ success: true, data: { state: store, checklist: [] } }),
      ),
      http.put(`${BASE_URL}/onboarding`, async ({ request }) => {
        const body = (await request.json()) as Partial<OnboardingState>;
        putSpy(body);
        store = { ...store, ...body };
        await putGate;
        return HttpResponse.json({ success: true, data: { state: store, checklist: [] } });
      }),
    );

    render(<OnboardingWizard onComplete={onComplete} onNavigate={() => undefined} />);

    const finishBtn = await screen.findByRole('button', { name: /go to dashboard/i });
    await user.click(finishBtn);

    // Assert the first click reached the server AND the button is now
    // disabled (finishing gate engaged) — that combination is what
    // prevents a double-submit from a rapid second click.
    await waitFor(() => {
      expect(putSpy).toHaveBeenCalledTimes(1);
    });
    expect(finishBtn).toBeDisabled();

    releasePut();
    await waitFor(() => {
      expect(onComplete).toHaveBeenCalledTimes(1);
    });

    // Still exactly one PUT — nothing else got through the gate.
    expect(putSpy).toHaveBeenCalledTimes(1);
  });
});

