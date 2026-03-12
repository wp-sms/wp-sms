import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { SaveBar } from '@/components/layout/save-bar';

describe('SaveBar', () => {
  it('is hidden when not dirty and idle', () => {
    const { container } = render(
      <SaveBar isDirty={false} saveStatus="idle" onSave={() => {}} />
    );

    expect(container.firstChild).toBeNull();
  });

  it('shows unsaved changes message when dirty', () => {
    render(
      <SaveBar isDirty={true} saveStatus="idle" onSave={() => {}} />
    );

    expect(screen.getByText('You have unsaved changes')).toBeInTheDocument();
  });

  it('shows saving state', () => {
    render(
      <SaveBar isDirty={true} saveStatus="saving" onSave={() => {}} />
    );

    expect(screen.getAllByText('Saving...').length).toBeGreaterThan(0);
    expect(screen.getByRole('button')).toBeDisabled();
  });

  it('calls onSave when button is clicked', async () => {
    const user = userEvent.setup();
    const onSave = vi.fn();

    render(
      <SaveBar isDirty={true} saveStatus="idle" onSave={onSave} />
    );

    await user.click(screen.getByRole('button', { name: /save changes/i }));
    expect(onSave).toHaveBeenCalledOnce();
  });

  it('disables save button when saving', () => {
    render(
      <SaveBar isDirty={true} saveStatus="saving" onSave={() => {}} />
    );

    expect(screen.getByRole('button')).toBeDisabled();
  });

  it('is hidden when not dirty and status is saved', () => {
    const { container } = render(
      <SaveBar isDirty={false} saveStatus="saved" onSave={() => {}} />
    );

    expect(container.firstChild).toBeNull();
  });
});
