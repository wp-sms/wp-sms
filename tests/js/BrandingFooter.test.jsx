import React from 'react'
import { render, screen } from '@testing-library/react'
import BrandingFooter from '@/components/layout/BrandingFooter'
import { setupWpSmsSettings } from './testing-utils'

describe('BrandingFooter', () => {
  beforeEach(() => {
    setupWpSmsSettings()
  })

  test('renders the community message', () => {
    render(<BrandingFooter />)
    expect(screen.getByText('for the WordPress community')).toBeInTheDocument()
  })

  test('renders the help links safely', () => {
    render(<BrandingFooter />)

    const docLink = screen.getByText('Documentation').closest('a')
    const supportLink = screen.getByText('Support').closest('a')

    expect(docLink).toHaveAttribute('href', 'https://wsms.io/docs/')
    expect(supportLink).toHaveAttribute('href', 'https://wsms.io/support/')
    for (const link of [docLink, supportLink]) {
      expect(link).toHaveAttribute('target', '_blank')
      expect(link).toHaveAttribute('rel', 'noopener noreferrer')
    }
  })

  test('renders the version linking to the changelog', () => {
    setupWpSmsSettings({ version: '7.5.0' })
    render(<BrandingFooter />)

    const versionLink = screen.getByText(/v7\.5\.0/).closest('a')
    expect(versionLink).toHaveAttribute('href', 'https://wsms.io/changelog/')
    expect(versionLink).toHaveAttribute('target', '_blank')
    expect(versionLink).toHaveAttribute('rel', 'noopener noreferrer')
  })

  test('renders the review link', () => {
    render(<BrandingFooter />)

    const rateLink = screen.getByText('Rate us').closest('a')
    expect(rateLink).toHaveAttribute(
      'href',
      'https://wordpress.org/support/plugin/wp-sms/reviews/#new-post'
    )
    expect(rateLink).toHaveAttribute('rel', 'noopener noreferrer')
  })
})
