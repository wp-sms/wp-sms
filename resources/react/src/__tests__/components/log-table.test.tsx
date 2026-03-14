import { describe, it, expect, vi } from 'vitest';
import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { LogTable } from '@/components/log-table';
import type { LogEntry } from '@/lib/api';

const mockLogs: LogEntry[] = [
  {
    id: 1,
    user_id: 1,
    event: 'login_success',
    status: 'success',
    ip_address: '192.168.1.1',
    channel_id: null,
    user_agent: 'Mozilla/5.0',
    meta: null,
    created_at: '2025-01-15T10:30:00Z',
    user_display: { display_name: 'Admin', email: 'admin@example.com' },
  },
  {
    id: 2,
    user_id: 2,
    event: 'login_failure',
    status: 'failure',
    ip_address: '10.0.0.1',
    channel_id: null,
    user_agent: null,
    meta: { reason: 'invalid_password' },
    created_at: '2025-01-15T10:25:00Z',
    user_display: null,
  },
  {
    id: 3,
    user_id: 1,
    event: 'otp_sent',
    status: 'success',
    ip_address: '192.168.1.1',
    channel_id: 'phone',
    user_agent: 'Mozilla/5.0',
    meta: { channel: 'phone', method: 'sms' },
    created_at: '2025-01-15T10:20:00Z',
    user_display: { display_name: 'Admin', email: 'admin@example.com' },
  },
];

describe('LogTable', () => {
  it('renders log entries', () => {
    render(
      <LogTable
        logs={mockLogs}
        total={3}
        page={1}
        perPage={20}
        onPageChange={() => {}}
        loading={false}
      />
    );

    expect(screen.getByText('Login Success')).toBeInTheDocument();
    expect(screen.getByText('Login Failure')).toBeInTheDocument();
    expect(screen.getAllByText('192.168.1.1').length).toBeGreaterThan(0);
  });

  it('shows empty message when no logs', () => {
    render(
      <LogTable
        logs={[]}
        total={0}
        page={1}
        perPage={20}
        onPageChange={() => {}}
        loading={false}
      />
    );

    expect(screen.getByText(/no log entries found/i)).toBeInTheDocument();
  });

  it('shows loading skeletons', () => {
    const { container } = render(
      <LogTable
        logs={[]}
        total={0}
        page={1}
        perPage={20}
        onPageChange={() => {}}
        loading={true}
      />
    );

    const skeletons = container.querySelectorAll('[class*="animate-pulse"]');
    expect(skeletons.length).toBeGreaterThan(0);
  });

  it('renders pagination when total exceeds perPage', () => {
    render(
      <LogTable
        logs={mockLogs}
        total={50}
        page={1}
        perPage={20}
        onPageChange={() => {}}
        loading={false}
      />
    );

    const nav = screen.getByRole('navigation');
    expect(within(nav).getByText('3')).toBeInTheDocument();
  });

  it('calls onPageChange when pagination is clicked', async () => {
    const user = userEvent.setup();
    const onPageChange = vi.fn();

    render(
      <LogTable
        logs={mockLogs}
        total={50}
        page={1}
        perPage={20}
        onPageChange={onPageChange}
        loading={false}
      />
    );

    const nav = screen.getByRole('navigation');
    await user.click(within(nav).getByText('3'));
    expect(onPageChange).toHaveBeenCalledWith(3);
  });

  it('shows badge for successful events', () => {
    render(
      <LogTable
        logs={[mockLogs[0]!]}
        total={1}
        page={1}
        perPage={20}
        onPageChange={() => {}}
        loading={false}
      />
    );

    expect(screen.getByText('success')).toBeInTheDocument();
  });

  it('shows badge for failed events', () => {
    render(
      <LogTable
        logs={[mockLogs[1]!]}
        total={1}
        page={1}
        perPage={20}
        onPageChange={() => {}}
        loading={false}
      />
    );

    expect(screen.getByText('failure')).toBeInTheDocument();
  });

  it('shows user display name with email tooltip', () => {
    render(
      <LogTable
        logs={[mockLogs[0]!]}
        total={1}
        page={1}
        perPage={20}
        onPageChange={() => {}}
        loading={false}
      />
    );

    const nameEl = screen.getByText('Admin');
    expect(nameEl).toBeInTheDocument();
    expect(nameEl).toHaveAttribute('title', 'admin@example.com');
  });

  it('falls back to user_id when user_display is null', () => {
    render(
      <LogTable
        logs={[mockLogs[1]!]}
        total={1}
        page={1}
        perPage={20}
        onPageChange={() => {}}
        loading={false}
      />
    );

    expect(screen.getByText('2')).toBeInTheDocument();
  });

  it('shows expand chevron for rows with details', () => {
    render(
      <LogTable
        logs={[mockLogs[2]!]}
        total={1}
        page={1}
        perPage={20}
        onPageChange={() => {}}
        loading={false}
      />
    );

    expect(screen.getByLabelText('Expand details')).toBeInTheDocument();
  });

  it('does not show expand chevron for rows without details', () => {
    const logNoDetails: LogEntry = {
      ...mockLogs[0]!,
      user_agent: null,
      meta: null,
      channel_id: null,
    };

    render(
      <LogTable
        logs={[logNoDetails]}
        total={1}
        page={1}
        perPage={20}
        onPageChange={() => {}}
        loading={false}
      />
    );

    expect(screen.queryByLabelText('Expand details')).not.toBeInTheDocument();
  });

  it('expands row to show metadata on click', async () => {
    const user = userEvent.setup();

    render(
      <LogTable
        logs={[mockLogs[2]!]}
        total={1}
        page={1}
        perPage={20}
        onPageChange={() => {}}
        loading={false}
      />
    );

    await user.click(screen.getByLabelText('Expand details'));

    // "phone" appears in both channel_id and metadata
    expect(screen.getAllByText('phone').length).toBeGreaterThan(0);
    expect(screen.getByText('sms')).toBeInTheDocument();
    expect(screen.getByText('Metadata:')).toBeInTheDocument();
    expect(screen.getByText('User Agent:')).toBeInTheDocument();
    expect(screen.getByLabelText('Collapse details')).toBeInTheDocument();
  });

  it('collapses expanded row on second click', async () => {
    const user = userEvent.setup();

    render(
      <LogTable
        logs={[mockLogs[2]!]}
        total={1}
        page={1}
        perPage={20}
        onPageChange={() => {}}
        loading={false}
      />
    );

    await user.click(screen.getByLabelText('Expand details'));
    expect(screen.getByLabelText('Collapse details')).toBeInTheDocument();

    await user.click(screen.getByLabelText('Collapse details'));
    expect(screen.getByLabelText('Expand details')).toBeInTheDocument();
  });
});
