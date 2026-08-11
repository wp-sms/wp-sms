import React from 'react'
import { fireEvent, render, screen } from '@testing-library/react'
import CountryStep from '@/components/migration/CountryStep'

const renderCountryStep = () => {
  const onChange = jest.fn()

  window.wpSmsSettings = {
    countries: [
      { code: 'CA', name: 'Canada', dialCode: '+1' },
      { code: 'US', name: 'United States (USA)', dialCode: '+1' },
      { code: 'GB', name: 'United Kingdom (UK)', dialCode: '+44' },
    ],
    countriesByDialCode: {
      '+1': 'Canada & United States (USA) (+1)',
      '+44': 'United Kingdom (UK) (+44)',
    },
  }

  render(
    <CountryStep
      value=""
      onChange={onChange}
      loading={false}
      onContinue={jest.fn()}
      onBack={jest.fn()}
    />
  )

  fireEvent.click(screen.getByRole('combobox', { name: 'Default country code' }))

  return onChange
}

describe('CountryStep', () => {
  test.each(['United States', 'USA', 'US', '+1'])(
    'makes the United States +1 option discoverable by searching for %s',
    (searchTerm) => {
      renderCountryStep()

      fireEvent.change(screen.getByRole('textbox', { name: 'Search countries...' }), {
        target: { value: searchTerm },
      })

      expect(
        screen.getByRole('button', {
          name: 'United States (USA) (US) & Canada (CA) (+1)',
        })
      ).toBeInTheDocument()
    }
  )

  test('stores +1 when the United States option is selected', () => {
    const onChange = renderCountryStep()

    fireEvent.click(
      screen.getByRole('button', {
        name: 'United States (USA) (US) & Canada (CA) (+1)',
      })
    )

    expect(onChange).toHaveBeenCalledWith('+1')
  })
})