import { describe, it, expect, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { LogsPage } from '@/pages/logs';
import { DEFAULTS } from '@/lib/constants';

describe('LogsPage', () => {
  const defaultProps = {
    settings: { ...DEFAULTS },
    onUpdate: vi.fn(),
  };

  it('renders log settings section', () => {
    render(<LogsPage {...defaultProps} />);

    expect(screen.getByText('Log Settings')).toBeInTheDocument();
    expect(screen.getByLabelText('Verbosity')).toBeInTheDocument();
    expect(screen.getByLabelText('Retention (days)')).toBeInTheDocument();
  });

  it('renders event log table after loading', async () => {
    render(<LogsPage {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('Login Success')).toBeInTheDocument();
    });

    expect(screen.getByText('Login Failure')).toBeInTheDocument();
  });

  it('renders filter controls including date filters', () => {
    render(<LogsPage {...defaultProps} />);

    expect(screen.getByLabelText('Event Type')).toBeInTheDocument();
    expect(screen.getByLabelText('Status')).toBeInTheDocument();
    expect(screen.getByLabelText('User ID')).toBeInTheDocument();
    expect(screen.getByLabelText('From Date')).toBeInTheDocument();
    expect(screen.getByLabelText('To Date')).toBeInTheDocument();
  });

  it('shows total events count', async () => {
    render(<LogsPage {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText(/3 total events/i)).toBeInTheDocument();
    });
  });

  it('shows clear logs button when logs exist', async () => {
    render(<LogsPage {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('Clear Logs')).toBeInTheDocument();
    });
  });

  it('shows confirmation before clearing logs', async () => {
    const user = userEvent.setup();
    render(<LogsPage {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('Clear Logs')).toBeInTheDocument();
    });

    await user.click(screen.getByText('Clear Logs'));

    expect(screen.getByText('Delete all logs?')).toBeInTheDocument();
    expect(screen.getByText('Confirm')).toBeInTheDocument();
    expect(screen.getByText('Cancel')).toBeInTheDocument();
  });

  it('cancels clear confirmation', async () => {
    const user = userEvent.setup();
    render(<LogsPage {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText('Clear Logs')).toBeInTheDocument();
    });

    await user.click(screen.getByText('Clear Logs'));
    await user.click(screen.getByText('Cancel'));

    expect(screen.queryByText('Delete all logs?')).not.toBeInTheDocument();
    expect(screen.getByText('Clear Logs')).toBeInTheDocument();
  });
});
