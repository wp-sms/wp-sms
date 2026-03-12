import { describe, it, expect, vi } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
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

  it('renders filter controls', () => {
    render(<LogsPage {...defaultProps} />);

    expect(screen.getByLabelText('Event Type')).toBeInTheDocument();
    expect(screen.getByLabelText('Status')).toBeInTheDocument();
    expect(screen.getByLabelText('User ID')).toBeInTheDocument();
  });

  it('shows total events count', async () => {
    render(<LogsPage {...defaultProps} />);

    await waitFor(() => {
      expect(screen.getByText(/3 total events/i)).toBeInTheDocument();
    });
  });
});
