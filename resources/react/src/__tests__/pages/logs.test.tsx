import { describe, it, expect, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { LogsPage } from '@/pages/logs';
import { ConfirmProvider } from '@/components/confirm-provider';
import { DEFAULTS } from '@/lib/constants';

function renderWithProviders(ui: React.ReactElement) {
  return render(<ConfirmProvider>{ui}</ConfirmProvider>);
}

describe('LogsPage', () => {
  const defaultProps = {
    settings: { ...DEFAULTS },
    onUpdate: vi.fn(),
  };

  it('renders log settings section', () => {
    renderWithProviders(<LogsPage {...defaultProps} />);

    expect(screen.getByText('Log Settings')).toBeInTheDocument();
    expect(screen.getByLabelText('Verbosity')).toBeInTheDocument();
    expect(screen.getByLabelText('Retention (days)')).toBeInTheDocument();
  });

  it('renders event log table after loading', async () => {
    renderWithProviders(<LogsPage {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('Login Success')).toBeInTheDocument();
    });

    expect(screen.getByText('Login Failure')).toBeInTheDocument();
  });

  it('renders filter controls including date filters', () => {
    renderWithProviders(<LogsPage {...defaultProps} />);

    expect(screen.getByLabelText('Event Type')).toBeInTheDocument();
    expect(screen.getByLabelText('Status')).toBeInTheDocument();
    expect(screen.getByLabelText('User ID')).toBeInTheDocument();
    expect(screen.getByLabelText('From Date')).toBeInTheDocument();
    expect(screen.getByLabelText('To Date')).toBeInTheDocument();
  });

  it('shows total events count', async () => {
    renderWithProviders(<LogsPage {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText(/3 total events/i)).toBeInTheDocument();
    });
  });

  it('shows clear logs button when logs exist', async () => {
    renderWithProviders(<LogsPage {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('Clear Logs')).toBeInTheDocument();
    });
  });

  it('shows confirmation dialog before clearing logs', async () => {
    const user = userEvent.setup();
    renderWithProviders(<LogsPage {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('Clear Logs')).toBeInTheDocument();
    });

    await user.click(screen.getByText('Clear Logs'));

    expect(screen.getByText('Clear all logs?')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Clear Logs' })).toBeInTheDocument();
    expect(screen.getByRole('button', { name: 'Cancel' })).toBeInTheDocument();
  });

  it('cancels clear confirmation dialog', async () => {
    const user = userEvent.setup();
    renderWithProviders(<LogsPage {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('Clear Logs')).toBeInTheDocument();
    });

    await user.click(screen.getByText('Clear Logs'));
    await user.click(screen.getByText('Cancel'));

    await waitFor(() => {
      expect(screen.queryByText('Clear all logs?')).not.toBeInTheDocument();
    });
  });
});
