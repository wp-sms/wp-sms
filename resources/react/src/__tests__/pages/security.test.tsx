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

  describe('MFA Factors', () => {
    it('renders all 3 MFA factor cards', () => {
      render(<SecurityPage section="mfa-factors" {...defaultProps} />);

      expect(screen.getByText('SMS Code')).toBeInTheDocument();
      expect(screen.getByText('Email OTP')).toBeInTheDocument();
      expect(screen.getByText('Backup Codes')).toBeInTheDocument();
    });

    it('toggles MFA factor', async () => {
      const user = userEvent.setup();
      const onUpdate = vi.fn();

      render(
        <SecurityPage
          section="mfa-factors"
          settings={{ ...DEFAULTS }}
          onUpdate={onUpdate}
          roles={testRoles}
        />
      );

      const smsSwitch = screen.getByRole('switch', { name: /toggle sms code/i });
      await user.click(smsSwitch);

      expect(onUpdate).toHaveBeenCalledWith('mfa_factors', ['sms']);
    });
  });

  describe('Policies', () => {
    it('renders role matrix with roles from WordPress', () => {
      render(<SecurityPage section="policies" {...defaultProps} />);

      expect(screen.getByText('Administrator')).toBeInTheDocument();
      expect(screen.getByText('Editor')).toBeInTheDocument();
      expect(screen.getByText('Subscriber')).toBeInTheDocument();
    });

    it('renders enrollment timing selector', () => {
      render(<SecurityPage section="policies" {...defaultProps} />);

      expect(screen.getByText('Enrollment Timing')).toBeInTheDocument();
    });

    it('toggles a role in the matrix', async () => {
      const user = userEvent.setup();
      const onUpdate = vi.fn();

      render(
        <SecurityPage
          section="policies"
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
    it('renders SMS and email rate limit settings', () => {
      render(<SecurityPage section="rate-limiting" {...defaultProps} />);

      expect(screen.getByText('SMS OTP Limits')).toBeInTheDocument();
      expect(screen.getByText('Email OTP Limits')).toBeInTheDocument();
    });

    it('updates max attempts setting', async () => {
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

      const maxAttemptsInput = screen.getByLabelText('Max Attempts', { selector: '#otp_sms_max_attempts' });
      await user.clear(maxAttemptsInput);
      await user.type(maxAttemptsInput, '10');

      expect(onUpdate).toHaveBeenCalled();
    });
  });
});
