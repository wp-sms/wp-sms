import React from 'react'
import { render, screen } from '@testing-library/react'
import { StatusBadge } from '../components/shared/StatusBadge'

describe('StatusBadge', () => {
  test('renders with success variant', () => {
    render(<StatusBadge variant="success">Sent</StatusBadge>)

    const badge = screen.getByText('Sent')
    expect(badge).toBeInTheDocument()
    expect(badge).toHaveClass('wsms-bg-emerald-500/10')
    expect(badge).toHaveClass('wsms-text-emerald-700')
  })

  test('renders with failed variant', () => {
    render(<StatusBadge variant="failed">Failed</StatusBadge>)

    const badge = screen.getByText('Failed')
    expect(badge).toHaveClass('wsms-bg-red-500/10')
    expect(badge).toHaveClass('wsms-text-red-700')
  })

  test('renders with pending variant', () => {
    render(<StatusBadge variant="pending">Pending</StatusBadge>)

    const badge = screen.getByText('Pending')
    expect(badge).toHaveClass('wsms-bg-amber-500/10')
    expect(badge).toHaveClass('wsms-text-amber-700')
  })

  test('renders with active variant', () => {
    render(<StatusBadge variant="active">Active</StatusBadge>)

    const badge = screen.getByText('Active')
    expect(badge).toHaveClass('wsms-bg-emerald-500/10')
  })

  test('renders with inactive variant', () => {
    render(<StatusBadge variant="inactive">Inactive</StatusBadge>)

    const badge = screen.getByText('Inactive')
    expect(badge).toHaveClass('wsms-bg-gray-500/10')
    expect(badge).toHaveClass('wsms-text-gray-700')
  })

  test('renders with warning variant', () => {
    render(<StatusBadge variant="warning">Warning</StatusBadge>)

    const badge = screen.getByText('Warning')
    expect(badge).toHaveClass('wsms-bg-orange-500/10')
    expect(badge).toHaveClass('wsms-text-orange-700')
  })

  test('renders with small size', () => {
    render(<StatusBadge variant="success" size="sm">Small</StatusBadge>)

    const badge = screen.getByText('Small')
    expect(badge).toHaveClass('wsms-text-[10px]')
    expect(badge).toHaveClass('wsms-px-2')
  })

  test('renders with large size', () => {
    render(<StatusBadge variant="success" size="lg">Large</StatusBadge>)

    const badge = screen.getByText('Large')
    expect(badge).toHaveClass('wsms-text-[12px]')
    expect(badge).toHaveClass('wsms-px-3')
  })

  test('shows icon by default', () => {
    render(<StatusBadge variant="success">Success</StatusBadge>)

    // The icon should be rendered as a child
    const badge = screen.getByText('Success')
    const icon = badge.querySelector('svg')
    expect(icon).toBeInTheDocument()
    expect(icon).toHaveClass('wsms-h-3')
  })

  test('hides icon when showIcon is false', () => {
    render(<StatusBadge variant="success" showIcon={false}>Success</StatusBadge>)

    const badge = screen.getByText('Success')
    const icon = badge.querySelector('svg')
    expect(icon).not.toBeInTheDocument()
  })

  test('applies custom className', () => {
    render(<StatusBadge variant="success" className="custom-badge">Test</StatusBadge>)

    const badge = screen.getByText('Test')
    expect(badge).toHaveClass('custom-badge')
  })

  test('renders with default pending variant when no variant specified', () => {
    render(<StatusBadge>Default</StatusBadge>)

    const badge = screen.getByText('Default')
    expect(badge).toHaveClass('wsms-bg-amber-500/10')
  })
})
