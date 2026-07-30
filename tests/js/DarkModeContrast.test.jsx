import React from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogTitle,
} from '@/components/ui/dialog'
import { Toaster, useToast } from '@/components/ui/toaster'
import CountryStep from '@/components/migration/CountryStep'
import PhoneInput from '@/components/wizard/components/PhoneInput'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'

function ToastTrigger({ variant = 'default' }) {
  const { toast } = useToast()

  return (
    <button onClick={() => toast({ title: 'Contrast notice', variant })}>
      Show toast
    </button>
  )
}

describe('dark-mode contrast safeguards', () => {
  beforeEach(() => {
    const root = document.createElement('div')
    root.id = 'wpsms-settings-root'
    document.body.appendChild(root)
  })

  afterEach(() => {
    document.getElementById('wpsms-settings-root')?.remove()
  })

  test('shared dialogs use theme surface and text tokens', () => {
    render(
      <Dialog open>
        <DialogContent>
          <DialogTitle>Dialog title</DialogTitle>
          <DialogDescription>Dialog description</DialogDescription>
        </DialogContent>
      </Dialog>
    )

    expect(screen.getByRole('dialog')).toHaveStyle({
      backgroundColor: 'hsl(var(--popover))',
      color: 'hsl(var(--popover-foreground))',
    })
    expect(screen.getByText('Dialog title')).toHaveStyle({ color: 'hsl(var(--foreground))' })
    expect(screen.getByText('Dialog description')).toHaveStyle({ color: 'hsl(var(--muted-foreground))' })
  })

  test('default and warning toasts provide paired foreground and background colors', () => {
    const { rerender } = render(
      <Toaster>
        <ToastTrigger />
      </Toaster>
    )

    fireEvent.click(screen.getByRole('button', { name: 'Show toast' }))
    const defaultToast = screen.getByText('Contrast notice').closest('.wsms-pointer-events-auto')
    expect(defaultToast).toHaveClass('wsms-bg-popover', 'wsms-text-popover-foreground')

    rerender(
      <Toaster>
        <ToastTrigger variant="warning" />
      </Toaster>
    )
    fireEvent.click(screen.getByRole('button', { name: 'Show toast' }))
    const toasts = screen.getAllByText('Contrast notice')
    const warningToast = toasts[toasts.length - 1].closest('.wsms-pointer-events-auto')
    expect(warningToast).toHaveClass('dark:wsms-bg-amber-950', 'dark:wsms-text-amber-200')
  })

  test('row/bulk action dropdown menus use theme surface and text tokens', () => {
    render(
      <DropdownMenu open>
        <DropdownMenuTrigger>Open</DropdownMenuTrigger>
        <DropdownMenuContent>
          <DropdownMenuItem>Edit</DropdownMenuItem>
        </DropdownMenuContent>
      </DropdownMenu>
    )

    expect(screen.getByRole('menu')).toHaveStyle({
      backgroundColor: 'hsl(var(--popover))',
      color: 'hsl(var(--popover-foreground))',
    })
    expect(screen.getByRole('menuitem', { name: 'Edit' })).toHaveStyle({
      color: 'hsl(var(--popover-foreground))',
    })
  })

  test('phone input muted text is scoped to dark mode only, not light mode', () => {
    render(<PhoneInput aria-label="Phone number" />)

    const input = screen.getByLabelText('Phone number')
    // Muted text must only apply in dark mode; light mode keeps the default foreground.
    expect(input).toHaveClass('dark:wsms-text-muted-foreground')
    expect(input).not.toHaveClass('wsms-text-muted-foreground')
  })

  test('the migration country selector has an explicit foreground token', () => {
    window.wpSmsSettings.countriesByDialCode = { '+1': 'United States (+1)' }

    render(
      <CountryStep
        value=""
        onChange={jest.fn()}
        loading={false}
        onContinue={jest.fn()}
        onBack={jest.fn()}
      />
    )

    expect(screen.getByLabelText('Default country code')).toHaveClass(
      'wsms-bg-background',
      'wsms-text-foreground'
    )
  })
})
