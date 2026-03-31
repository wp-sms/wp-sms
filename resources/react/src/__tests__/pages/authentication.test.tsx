import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { AuthenticationPage } from '@/pages/authentication';
import { DEFAULTS } from '@/lib/constants';

describe('AuthenticationPage', () => {
  const defaultProps = {
    settings: { ...DEFAULTS, auth_enabled: true },
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

      // switches[0] is the auth_enabled toggle (on in test defaults)
      const switches = screen.getAllByRole('switch');
      // Auth toggle (on), Phone (off), Email (on), Password (on)
      expect(switches[0]).toBeChecked(); // Auth toggle
      expect(switches[1]).not.toBeChecked(); // Phone
      expect(switches[2]).toBeChecked(); // Email
      expect(switches[3]).toBeChecked(); // Password
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

      // Two Phone switches exist (Sign-in + MFA columns), pick the first one.
      const phoneSwitches = screen.getAllByRole('switch', { name: /toggle phone/i });
      await user.click(phoneSwitches[0]);

      expect(onUpdate).toHaveBeenCalledWith('phone', expect.objectContaining({ enabled: true }));
    });
  });

});
