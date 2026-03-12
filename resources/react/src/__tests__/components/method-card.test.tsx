import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { MethodCard } from '@/components/method-card';

describe('MethodCard', () => {
  it('renders title and description', () => {
    render(
      <MethodCard
        title="Phone OTP"
        description="One-time password via SMS"
        enabled={false}
        onToggle={() => {}}
      />
    );

    expect(screen.getByText('Phone OTP')).toBeInTheDocument();
    expect(screen.getByText('One-time password via SMS')).toBeInTheDocument();
  });

  it('calls onToggle when switch is clicked', async () => {
    const user = userEvent.setup();
    const onToggle = vi.fn();

    render(
      <MethodCard
        title="Phone OTP"
        description="One-time password via SMS"
        enabled={false}
        onToggle={onToggle}
      />
    );

    const switchEl = screen.getByRole('switch', { name: /toggle phone otp/i });
    await user.click(switchEl);

    expect(onToggle).toHaveBeenCalledWith(true);
  });

  it('renders children when enabled and expanded', async () => {
    const user = userEvent.setup();

    render(
      <MethodCard
        title="Phone OTP"
        description="One-time password via SMS"
        enabled={true}
        onToggle={() => {}}
      >
        <input aria-label="Code Length" />
      </MethodCard>
    );

    // Expand the card
    const expandBtn = screen.getByLabelText('Toggle configuration');
    await user.click(expandBtn);

    expect(screen.getByLabelText('Code Length')).toBeInTheDocument();
  });

  it('does not show expand button when disabled', () => {
    render(
      <MethodCard
        title="Phone OTP"
        description="One-time password via SMS"
        enabled={false}
        onToggle={() => {}}
      >
        <input aria-label="Code Length" />
      </MethodCard>
    );

    expect(screen.queryByLabelText('Toggle configuration')).not.toBeInTheDocument();
  });

  it('has reduced opacity when disabled', () => {
    const { container } = render(
      <MethodCard
        title="Phone OTP"
        description="One-time password via SMS"
        enabled={false}
        onToggle={() => {}}
      />
    );

    // The card should have opacity-60 class
    const card = container.querySelector('[class*="opacity"]');
    expect(card).toBeInTheDocument();
  });
});
