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

  describe('Channels', () => {
    it('renders Phone, Email, and Password channel cards', () => {
      render(<AuthenticationPage section="channels" {...defaultProps} />);

      expect(screen.getByText('Phone')).toBeInTheDocument();
      expect(screen.getByText('Email')).toBeInTheDocument();
      expect(screen.getByText('Password')).toBeInTheDocument();
    });

    it('shows email as enabled by default', () => {
      render(<AuthenticationPage section="channels" {...defaultProps} />);

      // Email channel is enabled by default, password is enabled by default
      const switches = screen.getAllByRole('switch');
      // Phone (off), Email (on), Password (on)
      expect(switches[0]).not.toBeChecked(); // Phone
      expect(switches[1]).toBeChecked(); // Email
      expect(switches[2]).toBeChecked(); // Password
    });

    it('calls onUpdate when toggling phone channel', async () => {
      const user = userEvent.setup();
      const onUpdate = vi.fn();

      render(
        <AuthenticationPage
          section="channels"
          settings={{ ...DEFAULTS }}
          onUpdate={onUpdate}
        />
      );

      const phoneSwitch = screen.getByRole('switch', { name: /toggle phone/i });
      await user.click(phoneSwitch);

      expect(onUpdate).toHaveBeenCalledWith('phone', expect.objectContaining({ enabled: true }));
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
