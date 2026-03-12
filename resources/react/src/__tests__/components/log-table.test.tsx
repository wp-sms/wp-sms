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
    context: {},
    created_at: '2025-01-15T10:30:00Z',
  },
  {
    id: 2,
    user_id: 2,
    event: 'login_failure',
    status: 'failure',
    ip_address: '10.0.0.1',
    context: {},
    created_at: '2025-01-15T10:25:00Z',
  },
];

describe('LogTable', () => {
  it('renders log entries', () => {
    render(
      <LogTable
        logs={mockLogs}
        total={2}
        page={1}
        perPage={20}
        onPageChange={() => {}}
        loading={false}
      />
    );

    expect(screen.getByText('Login Success')).toBeInTheDocument();
    expect(screen.getByText('Login Failure')).toBeInTheDocument();
    expect(screen.getByText('192.168.1.1')).toBeInTheDocument();
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
});
