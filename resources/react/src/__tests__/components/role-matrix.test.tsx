import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { RoleMatrix } from '@/components/role-matrix';

const testRoles = {
  administrator: 'Administrator',
  editor: 'Editor',
  subscriber: 'Subscriber',
};

describe('RoleMatrix', () => {
  it('renders all roles', () => {
    render(
      <RoleMatrix
        roles={testRoles}
        selectedRoles={[]}
        onToggleRole={() => {}}
      />
    );

    expect(screen.getByText('Administrator')).toBeInTheDocument();
    expect(screen.getByText('Editor')).toBeInTheDocument();
    expect(screen.getByText('Subscriber')).toBeInTheDocument();
  });

  it('shows selected roles as checked', () => {
    render(
      <RoleMatrix
        roles={testRoles}
        selectedRoles={['administrator']}
        onToggleRole={() => {}}
      />
    );

    const adminSwitch = screen.getByRole('switch', { name: /require mfa for administrator/i });
    expect(adminSwitch).toBeChecked();

    const editorSwitch = screen.getByRole('switch', { name: /require mfa for editor/i });
    expect(editorSwitch).not.toBeChecked();
  });

  it('calls onToggleRole when a role switch is toggled', async () => {
    const user = userEvent.setup();
    const onToggleRole = vi.fn();

    render(
      <RoleMatrix
        roles={testRoles}
        selectedRoles={[]}
        onToggleRole={onToggleRole}
      />
    );

    const editorSwitch = screen.getByRole('switch', { name: /require mfa for editor/i });
    await user.click(editorSwitch);

    expect(onToggleRole).toHaveBeenCalledWith('editor', true);
  });

  it('shows empty message when no roles available', () => {
    render(
      <RoleMatrix
        roles={{}}
        selectedRoles={[]}
        onToggleRole={() => {}}
      />
    );

    expect(screen.getByText(/no roles available/i)).toBeInTheDocument();
  });
});
