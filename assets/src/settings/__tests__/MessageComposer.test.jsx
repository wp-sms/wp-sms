import React from 'react'
import { render, screen, fireEvent } from '@testing-library/react'
import { MessageComposer, calculateSmsInfo } from '../components/shared/MessageComposer'

describe('calculateSmsInfo', () => {
  test('returns correct info for empty string', () => {
    const info = calculateSmsInfo('')

    expect(info.characters).toBe(0)
    expect(info.segments).toBe(0)
    expect(info.remaining).toBe(160)
    expect(info.encoding).toBe('GSM-7')
    expect(info.isUnicode).toBe(false)
  })

  test('calculates correctly for short GSM message', () => {
    const info = calculateSmsInfo('Hello World')

    expect(info.characters).toBe(11)
    expect(info.segments).toBe(1)
    expect(info.remaining).toBe(149)
    expect(info.encoding).toBe('GSM-7')
    expect(info.isUnicode).toBe(false)
  })

  test('calculates correctly for message at single segment limit', () => {
    const message = 'a'.repeat(160)
    const info = calculateSmsInfo(message)

    expect(info.characters).toBe(160)
    expect(info.segments).toBe(1)
    expect(info.remaining).toBe(0)
    expect(info.encoding).toBe('GSM-7')
  })

  test('calculates correctly for multi-segment GSM message', () => {
    const message = 'a'.repeat(161)
    const info = calculateSmsInfo(message)

    expect(info.characters).toBe(161)
    expect(info.segments).toBe(2)
    expect(info.encoding).toBe('GSM-7')
  })

  test('calculates correctly for three segment message', () => {
    const message = 'a'.repeat(307) // 153 + 153 + 1 = 307
    const info = calculateSmsInfo(message)

    expect(info.characters).toBe(307)
    expect(info.segments).toBe(3)
    expect(info.encoding).toBe('GSM-7')
  })

  test('detects Unicode characters', () => {
    const info = calculateSmsInfo('Hello 你好')

    expect(info.isUnicode).toBe(true)
    expect(info.encoding).toBe('Unicode')
    expect(info.limit).toBe(70) // Unicode single message limit
  })

  test('calculates correctly for Unicode multi-segment message', () => {
    const message = '你'.repeat(71) // Over 70 character limit
    const info = calculateSmsInfo(message)

    expect(info.characters).toBe(71)
    expect(info.segments).toBe(2)
    expect(info.encoding).toBe('Unicode')
  })

  test('counts extended GSM characters as 2', () => {
    // € is an extended GSM character
    const info = calculateSmsInfo('Price: €100')

    // 'Price: ' = 7, '€' = 2, '100' = 3 => 12 characters
    expect(info.characters).toBe(12)
    expect(info.encoding).toBe('GSM-7')
    expect(info.isUnicode).toBe(false)
  })

  test('handles emoji as Unicode', () => {
    const info = calculateSmsInfo('Hello 😀')

    expect(info.isUnicode).toBe(true)
    expect(info.encoding).toBe('Unicode')
  })
})

describe('MessageComposer', () => {
  test('renders textarea with placeholder', () => {
    render(<MessageComposer placeholder="Type message..." />)

    const textarea = screen.getByPlaceholderText('Type message...')
    expect(textarea).toBeInTheDocument()
  })

  test('displays character count', () => {
    render(<MessageComposer value="Hello" />)

    expect(screen.getByText('5')).toBeInTheDocument()
  })

  test('displays segment count for single segment', () => {
    render(<MessageComposer value="Hello" />)

    expect(screen.getByText('1 segment')).toBeInTheDocument()
  })

  test('displays segments (plural) for multiple segments', () => {
    const longMessage = 'a'.repeat(161)
    render(<MessageComposer value={longMessage} />)

    expect(screen.getByText('2 segments')).toBeInTheDocument()
  })

  test('displays GSM-7 encoding badge for ASCII text', () => {
    render(<MessageComposer value="Hello" />)

    expect(screen.getByText('GSM-7')).toBeInTheDocument()
  })

  test('displays Unicode encoding badge for non-ASCII text', () => {
    render(<MessageComposer value="Hello 你好" />)

    expect(screen.getByText('Unicode')).toBeInTheDocument()
  })

  test('shows Unicode warning when showWarning is true', () => {
    render(<MessageComposer value="Hello 你好" showWarning={true} />)

    expect(
      screen.getByText(/Your message contains Unicode characters/)
    ).toBeInTheDocument()
  })

  test('hides Unicode warning when showWarning is false', () => {
    render(<MessageComposer value="Hello 你好" showWarning={false} />)

    expect(
      screen.queryByText(/Your message contains Unicode characters/)
    ).not.toBeInTheDocument()
  })

  test('shows over limit warning when exceeding max segments', () => {
    const longMessage = 'a'.repeat(1600) // Way over 10 segments
    render(<MessageComposer value={longMessage} maxSegments={10} />)

    expect(
      screen.getByText(/Message exceeds maximum of 10 segments/)
    ).toBeInTheDocument()
  })

  test('calls onChange when text is entered', () => {
    const handleChange = jest.fn()
    render(<MessageComposer value="" onChange={handleChange} />)

    const textarea = screen.getByRole('textbox')
    fireEvent.change(textarea, { target: { value: 'New message' } })

    expect(handleChange).toHaveBeenCalledWith('New message')
  })

  test('is disabled when disabled prop is true', () => {
    render(<MessageComposer value="" disabled={true} />)

    const textarea = screen.getByRole('textbox')
    expect(textarea).toBeDisabled()
  })

  test('displays remaining characters hint', () => {
    render(<MessageComposer value="Hello" />)

    expect(screen.getByText(/155 characters remaining/)).toBeInTheDocument()
  })

  test('applies custom rows', () => {
    render(<MessageComposer value="" rows={8} />)

    const textarea = screen.getByRole('textbox')
    expect(textarea).toHaveAttribute('rows', '8')
  })

  test('applies custom className', () => {
    const { container } = render(<MessageComposer className="custom-composer" value="" />)

    expect(container.firstChild).toHaveClass('custom-composer')
  })
})
