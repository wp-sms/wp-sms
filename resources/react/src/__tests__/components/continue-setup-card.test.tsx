import { describe, it, expect, afterEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { server } from '../mocks/server';
import { ContinueSetupCard } from '@/components/onboarding/continue-setup-card';
import type { OnboardingState, OnboardingStatus } from '@/lib/api';

const BASE_URL = 'https://example.com/wp-json/wsms/v1';

function buildState(status: OnboardingStatus): OnboardingState {
  return {
    status,
    goals: [],
    current_step: 0,
    skipped_steps: [],
    checklist_dismissed: false,
    migration_deferred: false,
    completed_at: null,
    version: '8.0',
  };
}

function setInitialOnboarding(state: OnboardingState | null) {
  (window as unknown as { wpSmsSettings: { onboarding: OnboardingState | null } }).wpSmsSettings.onboarding = state;
}

function installOnboardingHandler(state: OnboardingState) {
  server.use(
    http.get(`${BASE_URL}/onboarding`, () =>
      HttpResponse.json({ success: true, data: { state, checklist: [] } }),
    ),
  );
}

describe('ContinueSetupCard', () => {
  afterEach(() => {
    setInitialOnboarding(null);
    // Reset URL hash between tests so click assertions are isolated.
    window.location.hash = '';
  });

  describe('visibility', () => {
    it('renders with resume copy when status is in_progress', async () => {
      setInitialOnboarding(buildState('in_progress'));
      installOnboardingHandler(buildState('in_progress'));

      render(<ContinueSetupCard />);

      // useOnboarding starts loading=true, so the card is null until fetch resolves.
      await waitFor(() => {
        expect(screen.getByText('Setup in progress')).toBeInTheDocument();
      });
      expect(screen.getByText(/pick up where you left off/i)).toBeInTheDocument();
      expect(screen.getByRole('button', { name: /resume setup/i })).toBeInTheDocument();
    });

    it('renders with start copy when status is pending', async () => {
      setInitialOnboarding(buildState('pending'));
      installOnboardingHandler(buildState('pending'));

      render(<ContinueSetupCard />);

      await waitFor(() => {
        expect(screen.getByText('Welcome to WSMS')).toBeInTheDocument();
      });
      expect(screen.getByRole('button', { name: /get started/i })).toBeInTheDocument();
    });

    it('does not render when status is completed', async () => {
      setInitialOnboarding(buildState('completed'));
      installOnboardingHandler(buildState('completed'));

      const { container } = render(<ContinueSetupCard />);

      // Wait for fetch to complete so the loading-guard can clear and the
      // status-guard takes over (otherwise this test would pass even if the
      // component were broken — it'd just still be in loading=true).
      await waitFor(() => {
        expect(container.firstChild).toBeNull();
      });
      expect(screen.queryByText(/setup in progress/i)).not.toBeInTheDocument();
      expect(screen.queryByText(/welcome to wsms/i)).not.toBeInTheDocument();
    });

    it('does not render when status is skipped', async () => {
      setInitialOnboarding(buildState('skipped'));
      installOnboardingHandler(buildState('skipped'));

      const { container } = render(<ContinueSetupCard />);

      await waitFor(() => {
        expect(container.firstChild).toBeNull();
      });
    });
  });

  describe('navigation', () => {
    it('sets the URL hash to onboarding when the button is clicked', async () => {
      const user = userEvent.setup();
      setInitialOnboarding(buildState('in_progress'));
      installOnboardingHandler(buildState('in_progress'));

      render(<ContinueSetupCard />);

      const button = await screen.findByRole('button', { name: /resume setup/i });
      await user.click(button);

      expect(window.location.hash).toBe('#onboarding');
    });
  });
});
