import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { SaveBar } from '@/components/layout/save-bar';
import { SaveBarProvider, type SaveBarState } from '@/contexts/save-bar-context';

function renderSaveBar(state: SaveBarState) {
  return render(
    <SaveBarProvider defaultState={state}>
      <SaveBar />
    </SaveBarProvider>
  );
}

describe('SaveBar', () => {
  it('is hidden when not dirty and idle', () => {
    const { container } = renderSaveBar({
      isDirty: false, saveStatus: 'idle', onSave: () => {},
    });

    expect(container.querySelector('.sticky')).toBeNull();
  });

  it('shows unsaved changes message when dirty', () => {
    renderSaveBar({
      isDirty: true, saveStatus: 'idle', onSave: () => {},
    });

    expect(screen.getByText('You have unsaved changes')).toBeInTheDocument();
  });

  it('shows saving state', () => {
    renderSaveBar({
      isDirty: true, saveStatus: 'saving', onSave: () => {},
    });

    expect(screen.getAllByText('Saving...').length).toBeGreaterThan(0);
    expect(screen.getByRole('button')).toBeDisabled();
  });

  it('calls onSave when button is clicked', async () => {
    const user = userEvent.setup();
    const onSave = vi.fn();

    renderSaveBar({
      isDirty: true, saveStatus: 'idle', onSave,
    });

    await user.click(screen.getByRole('button', { name: /save changes/i }));
    expect(onSave).toHaveBeenCalledOnce();
  });

  it('disables save button when saving', () => {
    renderSaveBar({
      isDirty: true, saveStatus: 'saving', onSave: () => {},
    });

    expect(screen.getByRole('button')).toBeDisabled();
  });

  it('is hidden when not dirty and status is saved', () => {
    const { container } = renderSaveBar({
      isDirty: false, saveStatus: 'saved', onSave: () => {},
    });

    expect(container.querySelector('.sticky')).toBeNull();
  });
});
