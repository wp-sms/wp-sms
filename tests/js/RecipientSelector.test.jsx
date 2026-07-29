import React from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import { RecipientSelector } from '@/components/shared/RecipientSelector'

const emptyRecipients = {
  groups: [],
  roles: [],
  users: [],
  numbers: [],
}

describe('RecipientSelector', () => {
  test('rejects unsupported characters from manual phone numbers', () => {
    const handleChange = jest.fn()

    render(<RecipientSelector value={emptyRecipients} onChange={handleChange} />)
    fireEvent.click(screen.getByRole('button', { name: /numbers/i }))

    const input = screen.getByRole('textbox', { name: /phone number/i })
    fireEvent.change(input, { target: { value: 'abc+1 234, test\n567-890' } })

    expect(input).toHaveValue('+1 234, 567890')

    fireEvent.click(screen.getByRole('button', { name: /add number/i }))

    expect(handleChange).toHaveBeenCalledWith({
      ...emptyRecipients,
      numbers: ['+1 234', '567890'],
    })
  })

  test('does not add malformed sanitized phone numbers', () => {
    const handleChange = jest.fn()

    render(<RecipientSelector value={emptyRecipients} onChange={handleChange} />)
    fireEvent.click(screen.getByRole('button', { name: /numbers/i }))

    fireEvent.change(screen.getByRole('textbox', { name: /phone number/i }), {
      target: { value: '+++, 1++2, + 123, +123, 456 789' },
    })
    fireEvent.click(screen.getByRole('button', { name: /add number/i }))

    expect(handleChange).toHaveBeenCalledWith({
      ...emptyRecipients,
      numbers: ['+123', '456 789'],
    })
  })
})
