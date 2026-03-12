import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AuthenticationPage } from '@/pages/authentication';
import { DEFAULTS } from '@/lib/constants';

describe('AuthenticationPage', () => {
  const defaultProps = {
    settings: { ...DEFAULTS },
    onUpdate: vi.fn(),
  };

  describe('Login Methods', () => {
    it('renders all 4 method cards', () => {
      render(<AuthenticationPage section="login-methods" {...defaultProps} />);

      expect(screen.getByText('Password')).toBeInTheDocument();
      expect(screen.getByText('Phone OTP')).toBeInTheDocument();
      expect(screen.getByText('Email OTP')).toBeInTheDocument();
      expect(screen.getByText('Magic Link')).toBeInTheDocument();
    });

    it('shows password as enabled by default', () => {
      render(<AuthenticationPage section="login-methods" {...defaultProps} />);

      const switches = screen.getAllByRole('switch');
      // First switch (Password) should be checked
      expect(switches[0]).toBeChecked();
    });

    it('calls onUpdate when toggling a method', async () => {
      const user = userEvent.setup();
      const onUpdate = vi.fn();

      render(
        <AuthenticationPage
          section="login-methods"
          settings={{ ...DEFAULTS }}
          onUpdate={onUpdate}
        />
      );

      const phoneSwitch = screen.getByRole('switch', { name: /toggle phone otp/i });
      await user.click(phoneSwitch);

      expect(onUpdate).toHaveBeenCalledWith('primary_methods', ['password', 'phone_otp']);
    });
  });

  describe('Registration', () => {
    it('renders registration settings', () => {
      render(<AuthenticationPage section="registration" {...defaultProps} />);

      expect(screen.getByText('Auto-Create Users')).toBeInTheDocument();
      expect(screen.getByText('Registration Fields')).toBeInTheDocument();
    });

    it('toggles auto_create_users', async () => {
      const user = userEvent.setup();
      const onUpdate = vi.fn();

      render(
        <AuthenticationPage
          section="registration"
          settings={{ ...DEFAULTS }}
          onUpdate={onUpdate}
        />
      );

      const autoCreateSwitch = screen.getByRole('switch', { name: /toggle auto-create users/i });
      await user.click(autoCreateSwitch);

      expect(onUpdate).toHaveBeenCalledWith('auto_create_users', true);
    });

    it('renders registration field checkboxes', () => {
      render(<AuthenticationPage section="registration" {...defaultProps} />);

      expect(screen.getByText('Phone Number')).toBeInTheDocument();
      expect(screen.getByText('First Name')).toBeInTheDocument();
      expect(screen.getByText('Last Name')).toBeInTheDocument();
    });
  });
});
