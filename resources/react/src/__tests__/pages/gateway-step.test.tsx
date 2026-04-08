import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { GatewayStep } from '@/pages/onboarding/gateway-step';
import type { Gateway, TestConnectionResult } from '@/lib/api';
import type { UseGatewaysReturn } from '@/hooks/use-gateways';

/**
 * Coverage for the gateway-step debounce/flush wiring introduced in commit
 * 217b78d2 and the test-connection race fix that hardens it.
 *
 * The key invariant: when a user types into a credential field and immediately
 * clicks Test Connection (before the 600ms debounce expires), the test request
 * MUST run against the just-typed values — not against the previously-saved
 * server state. The fix awaits flushPendingSave() inside handleTestConnection,
 * so the PUT completes before the POST fires. These tests pin that ordering by
 * gating the updateConfig promise and asserting testConnection has not been
 * called until updateConfig resolves.
 */

/** Single-gateway fixture: one shared `api_key` text field, no channel fields. */
function makeGateway(overrides: Partial<Gateway> = {}): Gateway {
  return {
    id: 'twilio',
    name: 'Twilio',
    supported_channels: ['sms'],
    is_configured: false,
    config: { shared: { api_key: '' }, channels: {}, is_default: {} },
    config_schema: {
      shared: {
        api_key: { type: 'text', label: 'API Key', required: true },
      },
      channels: {},
    },
    metadata: { regions: ['global'] },
    features: {},
    ...overrides,
  };
}

interface FakeHook extends UseGatewaysReturn {
  updateConfig: ReturnType<typeof vi.fn>;
  testConnection: ReturnType<typeof vi.fn>;
}

function makeHook(gateways: Gateway[]): FakeHook {
  return {
    gateways,
    loading: false,
    updateConfig: vi.fn(() => Promise.resolve()),
    testConnection: vi.fn(
      (): Promise<TestConnectionResult> =>
        Promise.resolve({ success: true, message: 'OK', details: {} }),
    ),
    testGateway: vi.fn(),
    getCredit: vi.fn(),
    refetch: vi.fn(),
  };
}

describe('GatewayStep — flush ordering', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('Test Connection awaits the pending debounced save before POSTing', async () => {
    // delay: null disables userEvent's per-keystroke timing so typing is
    // synchronous — otherwise the 600ms debounce in handleFormChange could
    // fire mid-test and we lose the "save still pending when test clicked"
    // window we are trying to assert on.
    const user = userEvent.setup({ delay: null });

    // Manually-resolved updateConfig promise — gates the PUT so the test
    // can observe the in-flight window between flush and test-connection.
    let resolveUpdate: () => void = () => undefined;
    const updateGate = new Promise<void>((resolve) => {
      resolveUpdate = resolve;
    });
    const hook = makeHook([makeGateway()]);
    hook.updateConfig.mockImplementation(() => updateGate);

    render(<GatewayStep goals={['notifications']} gatewaysHook={hook} />);

    // Open the configuration panel by clicking the recommended Twilio card.
    await user.click(screen.getByRole('button', { name: /twilio/i }));

    // Type into the API key field. Triggers handleFormChange → debounce timer.
    const apiKeyInput = await screen.findByLabelText(/api key/i);
    await user.type(apiKeyInput, 'sk_live_abc');

    // Click Test Connection BEFORE the 600ms debounce naturally fires.
    const testBtn = screen.getByRole('button', { name: /test connection/i });
    await user.click(testBtn);

    // The flush kicks updateConfig immediately with the typed value.
    await waitFor(() => {
      expect(hook.updateConfig).toHaveBeenCalledTimes(1);
    });
    expect(hook.updateConfig).toHaveBeenCalledWith({
      twilio: expect.objectContaining({
        shared: expect.objectContaining({ api_key: 'sk_live_abc' }),
      }),
    });

    // CRITICAL: testConnection must NOT have fired yet — handleTestConnection
    // is parked on `await flushPendingSave()` because updateConfig is gated.
    expect(hook.testConnection).not.toHaveBeenCalled();

    // Release the gate. Now flushPendingSave() resolves, and only after that
    // does testConnection get called with the (now-saved) credentials.
    resolveUpdate();
    await waitFor(() => {
      expect(hook.testConnection).toHaveBeenCalledWith('twilio');
    });
    expect(hook.testConnection).toHaveBeenCalledTimes(1);
  });

  it('switching gateways flushes the pending save for the previous gateway', async () => {
    const user = userEvent.setup({ delay: null });
    const hook = makeHook([
      makeGateway({ id: 'twilio', name: 'Twilio' }),
      makeGateway({ id: 'vonage', name: 'Vonage' }),
    ]);

    render(<GatewayStep goals={['notifications']} gatewaysHook={hook} />);

    // Select Twilio, type into the field.
    await user.click(screen.getByRole('button', { name: /twilio/i }));
    const apiKeyInput = await screen.findByLabelText(/api key/i);
    await user.type(apiKeyInput, 'twilio_key');

    // Switch back to the recommendations and select Vonage. The pending
    // Twilio draft must flush as part of selectGateway() — otherwise the
    // user's typing is lost when the form unmounts the field.
    await user.click(screen.getByRole('button', { name: /choose a different gateway/i }));
    await user.click(screen.getByRole('button', { name: /vonage/i }));

    await waitFor(() => {
      expect(hook.updateConfig).toHaveBeenCalledWith({
        twilio: expect.objectContaining({
          shared: expect.objectContaining({ api_key: 'twilio_key' }),
        }),
      });
    });
  });

  it('unmount flushes the pending save', async () => {
    const user = userEvent.setup({ delay: null });
    const hook = makeHook([makeGateway()]);

    const { unmount } = render(<GatewayStep goals={['notifications']} gatewaysHook={hook} />);

    await user.click(screen.getByRole('button', { name: /twilio/i }));
    const apiKeyInput = await screen.findByLabelText(/api key/i);
    await user.type(apiKeyInput, 'unmount_key');

    // Unmount mid-debounce — the cleanup effect must flush so the user's
    // typing is not silently dropped when they navigate away from the step.
    unmount();

    await waitFor(() => {
      expect(hook.updateConfig).toHaveBeenCalledWith({
        twilio: expect.objectContaining({
          shared: expect.objectContaining({ api_key: 'unmount_key' }),
        }),
      });
    });
  });
});
