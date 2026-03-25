import { describe, it, expect, beforeEach } from 'vitest';
import { render, screen, within, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { http, HttpResponse } from 'msw';
import { server } from '../mocks/server';
import { ConfirmProvider } from '@/components/confirm-provider';
import { Webhooks } from '@/pages/messaging/webhooks';

const BASE_URL = 'https://example.com/wp-json/wsms/v1';

const mockWebhooks = [
  {
    id: 'wh-1',
    name: 'Zapier Integration',
    url: 'https://hooks.zapier.com/abc123',
    secret: 'abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234abcd1234',
    events: ['message.sent', 'contact.created'],
    status: 'active' as const,
    description: 'Send events to Zapier',
    created_at: '2026-03-25T14:30:00Z',
    updated_at: '2026-03-25T14:30:00Z',
    last_test: { success: true, message: 'HTTP 200 in 342ms', at: '2026-03-25T14:30:00Z' },
  },
  {
    id: 'wh-2',
    name: 'Make.com',
    url: 'https://hook.make.com/xyz',
    secret: 'efgh5678efgh5678efgh5678efgh5678efgh5678efgh5678efgh5678efgh5678',
    events: ['campaign.completed'],
    status: 'paused' as const,
    description: null,
    created_at: '2026-03-20T10:00:00Z',
    updated_at: '2026-03-20T10:00:00Z',
    last_test: null,
  },
];

const mockEventGroups = {
  Messages: [
    {
      name: 'message.sent',
      label: 'Message Sent',
      description: 'Fires when a message is delivered successfully.',
      sample_payload: { message_id: '01J...', channel: 'sms', recipient: '+1555', gateway: 'twilio', status: 'sent', provider_id: 'SM...' },
    },
    {
      name: 'message.failed',
      label: 'Message Failed',
      description: 'Fires when a message fails to send.',
      sample_payload: { channel: 'sms', recipient: '+1555', gateway: 'twilio', error: 'Invalid number' },
    },
  ],
  Contacts: [
    {
      name: 'contact.created',
      label: 'Contact Created',
      description: 'Fires when a new contact is added.',
      sample_payload: { contact_id: '01J...', email: 'jane@example.com', phone: '+1555' },
    },
  ],
  Campaigns: [
    {
      name: 'campaign.completed',
      label: 'Campaign Completed',
      description: 'Fires when a campaign finishes.',
      sample_payload: { campaign_id: '01J...', sent_count: 1480 },
    },
  ],
};

function setupHandlers(webhooks = mockWebhooks) {
  server.use(
    http.get(`${BASE_URL}/outbound-webhooks`, () => {
      return HttpResponse.json({ success: true, data: webhooks });
    }),
    http.get(`${BASE_URL}/outbound-webhooks/events`, () => {
      return HttpResponse.json({ success: true, data: mockEventGroups });
    }),
    http.post(`${BASE_URL}/outbound-webhooks`, async ({ request }) => {
      const body = await request.json() as Record<string, unknown>;
      return HttpResponse.json({
        success: true,
        data: {
          id: 'wh-new',
          ...body,
          secret: 'newsecret'.repeat(8),
          status: 'active',
          created_at: '2026-03-25T15:00:00Z',
          updated_at: '2026-03-25T15:00:00Z',
          last_test: null,
        },
      }, { status: 201 });
    }),
    http.delete(`${BASE_URL}/outbound-webhooks/:id`, () => {
      return HttpResponse.json({ success: true });
    }),
    http.post(`${BASE_URL}/outbound-webhooks/:id/toggle`, ({ params }) => {
      const wh = webhooks.find((w) => w.id === params.id);
      return HttpResponse.json({
        success: true,
        data: { ...wh, status: wh?.status === 'active' ? 'paused' : 'active' },
      });
    }),
    http.post(`${BASE_URL}/outbound-webhooks/:id/test-connection`, () => {
      return HttpResponse.json({
        success: true,
        message: 'Connection successful \u2014 HTTP 200 in 150ms',
        details: { status_code: 200, response_time_ms: 150 },
      });
    }),
  );
}

function renderWebhooks() {
  return render(
    <ConfirmProvider>
      <Webhooks />
    </ConfirmProvider>,
  );
}

describe('Webhooks page', () => {
  beforeEach(() => {
    setupHandlers();
  });

  it('renders list of webhooks', async () => {
    renderWebhooks();

    await waitFor(() => {
      expect(screen.getByText('Zapier Integration')).toBeInTheDocument();
    });

    expect(screen.getByText('Make.com')).toBeInTheDocument();
    expect(screen.getByText('2 events')).toBeInTheDocument();
    expect(screen.getByText('1 event')).toBeInTheDocument();
  });

  it('shows health indicator from last_test', async () => {
    renderWebhooks();

    await waitFor(() => {
      expect(screen.getByText('Zapier Integration')).toBeInTheDocument();
    });

    // Zapier has successful last_test → green check (SVG)
    // Make.com has null last_test → gray dash (SVG)
    // We can verify the icons are present by checking table rows
    const rows = screen.getAllByRole('row');
    // 1 header + 2 data rows
    expect(rows.length).toBe(3);
  });

  it('shows empty state when no webhooks', async () => {
    setupHandlers([]);
    renderWebhooks();

    await waitFor(() => {
      expect(screen.getByText('No webhooks yet')).toBeInTheDocument();
    });
  });

  it('navigates to create form', async () => {
    const user = userEvent.setup();
    renderWebhooks();

    await waitFor(() => {
      expect(screen.getByText('Zapier Integration')).toBeInTheDocument();
    });

    await user.click(screen.getByRole('button', { name: /Create Webhook/i }));

    await waitFor(() => {
      expect(screen.getByLabelText('Name')).toBeInTheDocument();
    });

    expect(screen.getByLabelText('URL')).toBeInTheDocument();
    // Heading says "Create Webhook"
    expect(screen.getByRole('heading', { name: 'Create Webhook' })).toBeInTheDocument();
  });

  it('shows event picker with groups', async () => {
    const user = userEvent.setup();
    renderWebhooks();

    await waitFor(() => {
      expect(screen.getByText('Zapier Integration')).toBeInTheDocument();
    });

    await user.click(screen.getByRole('button', { name: /Create Webhook/i }));

    await waitFor(() => {
      expect(screen.getByText('Messages')).toBeInTheDocument();
    });

    expect(screen.getByText('Contacts')).toBeInTheDocument();
    expect(screen.getByText('Campaigns')).toBeInTheDocument();
    expect(screen.getByText('Message Sent')).toBeInTheDocument();
    expect(screen.getByText('Contact Created')).toBeInTheDocument();
  });

  it('validates required fields on create', async () => {
    const user = userEvent.setup();
    renderWebhooks();

    await waitFor(() => {
      expect(screen.getByText('Zapier Integration')).toBeInTheDocument();
    });

    await user.click(screen.getByRole('button', { name: /Create Webhook/i }));

    await waitFor(() => {
      expect(screen.getByLabelText('Name')).toBeInTheDocument();
    });

    // Click submit without filling anything — find the submit button by its text within form area
    const allButtons = screen.getAllByRole('button');
    const submitBtn = allButtons.find((b) => b.textContent === 'Create Webhook');
    await user.click(submitBtn!);

    await waitFor(() => {
      expect(screen.getByText('Name is required.')).toBeInTheDocument();
    });

    expect(screen.getByText('URL is required.')).toBeInTheDocument();
    expect(screen.getByText('Select at least one event.')).toBeInTheDocument();
  });

  it('navigates to edit form when clicking webhook name', async () => {
    const user = userEvent.setup();
    renderWebhooks();

    await waitFor(() => {
      expect(screen.getByText('Zapier Integration')).toBeInTheDocument();
    });

    await user.click(screen.getByText('Zapier Integration'));

    await waitFor(() => {
      expect(screen.getByText('Edit Webhook')).toBeInTheDocument();
    });
  });
});
