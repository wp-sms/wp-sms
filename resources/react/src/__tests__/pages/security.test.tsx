import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { SecurityPage } from '@/pages/security';
import { DEFAULTS } from '@/lib/constants';

const testRoles = {
  administrator: 'Administrator',
  editor: 'Editor',
  subscriber: 'Subscriber',
};

describe('SecurityPage', () => {
  const defaultProps = {
    settings: { ...DEFAULTS },
    onUpdate: vi.fn(),
    roles: testRoles,
  };

  describe('MFA Policies', () => {
    it('renders backup codes card', () => {
      render(<SecurityPage section="mfa-policies" {...defaultProps} />);

      expect(screen.getByText('Backup Codes')).toBeInTheDocument();
    });

    it('renders role matrix with roles from WordPress', () => {
      render(<SecurityPage section="mfa-policies" {...defaultProps} />);

      expect(screen.getByText('Administrator')).toBeInTheDocument();
      expect(screen.getByText('Editor')).toBeInTheDocument();
      expect(screen.getByText('Subscriber')).toBeInTheDocument();
    });

    it('renders enrollment timing selector', () => {
      render(<SecurityPage section="mfa-policies" {...defaultProps} />);

      expect(screen.getByText('Enrollment Timing')).toBeInTheDocument();
    });

    it('toggles backup codes', async () => {
      const user = userEvent.setup();
      const onUpdate = vi.fn();

      render(
        <SecurityPage
          section="mfa-policies"
          settings={{ ...DEFAULTS }}
          onUpdate={onUpdate}
          roles={testRoles}
        />
      );

      const backupSwitch = screen.getByRole('switch', { name: /toggle backup codes/i });
      await user.click(backupSwitch);

      expect(onUpdate).toHaveBeenCalledWith('backup_codes', expect.objectContaining({ enabled: true }));
    });

    it('toggles a role in the matrix', async () => {
      const user = userEvent.setup();
      const onUpdate = vi.fn();

      render(
        <SecurityPage
          section="mfa-policies"
          settings={{ ...DEFAULTS }}
          onUpdate={onUpdate}
          roles={testRoles}
        />
      );

      const adminSwitch = screen.getByRole('switch', { name: /require mfa for administrator/i });
      await user.click(adminSwitch);

      expect(onUpdate).toHaveBeenCalledWith('mfa_required_roles', ['administrator']);
    });
  });

  describe('Rate Limiting', () => {
    it('renders Phone and Email rate limit settings', () => {
      render(<SecurityPage section="rate-limiting" {...defaultProps} />);

      expect(screen.getByText('Phone Channel Limits')).toBeInTheDocument();
      expect(screen.getByText('Email Channel Limits')).toBeInTheDocument();
    });

    it('updates max attempts setting for phone', async () => {
      const user = userEvent.setup();
      const onUpdate = vi.fn();

      render(
        <SecurityPage
          section="rate-limiting"
          settings={{ ...DEFAULTS }}
          onUpdate={onUpdate}
          roles={testRoles}
        />
      );

      const maxAttemptsInput = screen.getByLabelText('Max Attempts', { selector: '#phone_max_attempts' });
      await user.clear(maxAttemptsInput);
      await user.type(maxAttemptsInput, '10');

      expect(onUpdate).toHaveBeenCalled();
    });
  });
});
