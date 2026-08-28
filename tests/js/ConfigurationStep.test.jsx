import React from 'react'
import { render, screen } from '@testing-library/react'
import ConfigurationStep from '@/components/wizard/steps/ConfigurationStep'

jest.mock('@/hooks/useGatewayRegistry', () => ({
  __esModule: true,
  default: () => ({ gateways: [] }),
}))

describe('ConfigurationStep', () => {
  test('renders gateway textarea fields as multiline controls', () => {
    render(
      <ConfigurationStep
        gatewayName="custom"
        gatewayCapabilities={{
          gatewayFields: {
            http_parameters: {
              id: 'gateway_http_parameters',
              name: 'HTTP Parameters',
              type: 'textarea',
            },
          },
        }}
        credentials={{ gateway_http_parameters: 'api_key:xxx\nmessage:{message}' }}
        onCredentialChange={jest.fn()}
      />
    )

    const field = screen.getByLabelText('HTTP Parameters')

    expect(field.tagName).toBe('TEXTAREA')
    expect(field).toHaveValue('api_key:xxx\nmessage:{message}')
  })

  test('masks secret gateway fields configured as textareas', () => {
    render(
      <ConfigurationStep
        gatewayName="custom"
        gatewayCapabilities={{
          gatewayFields: {
            http_headers: {
              id: 'gateway_http_headers',
              name: 'HTTP Headers',
              type: 'textarea',
              isPassword: true,
            },
          },
        }}
        credentials={{ gateway_http_headers: 'Authorization: Bearer secret-token' }}
        onCredentialChange={jest.fn()}
      />
    )

    const field = screen.getByLabelText('HTTP Headers')

    expect(field.tagName).toBe('INPUT')
    expect(field).toHaveAttribute('type', 'password')
    expect(field).toHaveValue('Authorization: Bearer secret-token')
  })
})
