import React from 'react'
import { waitFor } from '@testing-library/react'
import MessageButton from '@/pages/MessageButton'
import { renderWithProviders, setupWpSmsSettings } from './testing-utils'

describe('MessageButton footer preview', () => {
  beforeEach(() => {
    document.body.innerHTML = `
      <aside class="wpsms-chatbox">
        <button class="wpsms-chatbox__button">
          <span class="wpsms-chatbox__button-title"></span>
        </button>
        <div class="wpsms-chatbox__content">
          <header class="wpsms-chatbox__header"><h2></h2></header>
          <div class="wpsms-chatbox__container"></div>
          <footer class="wpsms-chatbox__info">
            <div class="wpsms-chatbox__info--text">
              Chat with us on WhatsApp for instant support!
            </div>
            <div class="wpsms-chatbox__copy-right">Powered By WSMS</div>
          </footer>
          <span class="wpsms-chatbox__arrow"></span>
        </div>
      </aside>
    `
  })

  test('shows no fallback message while preserving the footer link and branding', async () => {
    setupWpSmsSettings({
      settings: {
        chatbox_message_button: '1',
        chatbox_footer_text: '',
        chatbox_footer_link_title: 'Help Center',
        chatbox_footer_link_url: 'https://example.com/help',
      },
    })

    renderWithProviders(<MessageButton />)

    const footer = document.querySelector('.wpsms-chatbox__info--text')
    await waitFor(() => {
      expect(footer).not.toHaveTextContent('Chat with us on WhatsApp for instant support!')
      expect(footer.querySelector('a')).toHaveTextContent('Help Center')
      expect(footer.querySelector('a')).toHaveAttribute('href', 'https://example.com/help')
    })
    expect(document.querySelector('.wpsms-chatbox__copy-right')).toHaveTextContent('Powered By WSMS')
  })
})