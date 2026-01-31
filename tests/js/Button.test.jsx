import React from 'react'
import { render, screen, fireEvent } from '@testing-library/react'
import { Button } from '@/components/ui/button'

describe('Button', () => {
  test('renders with default variant and size', () => {
    render(<Button>Click me</Button>)

    const button = screen.getByRole('button', { name: /click me/i })
    expect(button).toBeInTheDocument()
    expect(button).toHaveClass('wsms-bg-primary')
    expect(button).toHaveClass('wsms-h-9')
  })

  test('renders with outline variant', () => {
    render(<Button variant="outline">Outline</Button>)

    const button = screen.getByRole('button', { name: /outline/i })
    expect(button).toHaveClass('wsms-border')
    expect(button).toHaveClass('wsms-bg-card')
  })

  test('renders with destructive variant', () => {
    render(<Button variant="destructive">Delete</Button>)

    const button = screen.getByRole('button', { name: /delete/i })
    expect(button).toHaveClass('wsms-bg-destructive')
  })

  test('renders with small size', () => {
    render(<Button size="sm">Small</Button>)

    const button = screen.getByRole('button', { name: /small/i })
    expect(button).toHaveClass('wsms-h-8')
    expect(button).toHaveClass('wsms-px-3')
  })

  test('renders with large size', () => {
    render(<Button size="lg">Large</Button>)

    const button = screen.getByRole('button', { name: /large/i })
    expect(button).toHaveClass('wsms-h-10')
    expect(button).toHaveClass('wsms-px-5')
  })

  test('renders with icon size', () => {
    render(<Button size="icon">+</Button>)

    const button = screen.getByRole('button', { name: /\+/i })
    expect(button).toHaveClass('wsms-h-9')
    expect(button).toHaveClass('wsms-w-9')
  })

  test('calls onClick handler when clicked', () => {
    const handleClick = jest.fn()
    render(<Button onClick={handleClick}>Click me</Button>)

    const button = screen.getByRole('button', { name: /click me/i })
    fireEvent.click(button)

    expect(handleClick).toHaveBeenCalledTimes(1)
  })

  test('is disabled when disabled prop is true', () => {
    render(<Button disabled>Disabled</Button>)

    const button = screen.getByRole('button', { name: /disabled/i })
    expect(button).toBeDisabled()
    expect(button).toHaveClass('disabled:wsms-opacity-50')
  })

  test('does not call onClick when disabled', () => {
    const handleClick = jest.fn()
    render(<Button disabled onClick={handleClick}>Disabled</Button>)

    const button = screen.getByRole('button', { name: /disabled/i })
    fireEvent.click(button)

    expect(handleClick).not.toHaveBeenCalled()
  })

  test('applies custom className', () => {
    render(<Button className="custom-class">Custom</Button>)

    const button = screen.getByRole('button', { name: /custom/i })
    expect(button).toHaveClass('custom-class')
  })

  test('renders with type submit', () => {
    render(<Button type="submit">Submit</Button>)

    const button = screen.getByRole('button', { name: /submit/i })
    expect(button).toHaveAttribute('type', 'submit')
  })

  test('combines variant and size', () => {
    render(<Button variant="outline" size="lg">Combo</Button>)

    const button = screen.getByRole('button', { name: /combo/i })
    expect(button).toHaveClass('wsms-border')
    expect(button).toHaveClass('wsms-h-10')
  })

  test('renders as child element when asChild is true', () => {
    render(
      <Button asChild>
        <a href="/test">Link Button</a>
      </Button>
    )

    const link = screen.getByRole('link', { name: /link button/i })
    expect(link).toBeInTheDocument()
    expect(link).toHaveAttribute('href', '/test')
  })
})
