import React, { useCallback, useEffect, useMemo } from 'react'
import { MessageSquare, Palette, Users, Link, Eye } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Repeater } from '@/components/ui/repeater'
import { InputField, SelectField, SettingRow } from '@/components/ui/form-field'
import { useSetting, useSettings } from '@/context/SettingsContext'
import { __, getWpSettings } from '@/lib/utils'

/**
 * Generate contact link based on type
 */
function generateContactLink(type, value) {
  const trimmedValue = (value || '').trim()
  switch (type) {
    case 'whatsapp':
      return `https://wa.me/${trimmedValue}`
    case 'telegram':
      return `https://t.me/${trimmedValue}`
    case 'facebook':
      return `https://me.me/${trimmedValue}`
    case 'sms':
      return `sms:${trimmedValue}`
    case 'email':
      return `mailto:${trimmedValue}`
    default:
      return `tel:${trimmedValue}`
  }
}

/**
 * Get default avatar URL from WordPress localized data
 */
function getDefaultAvatarUrl() {
  const wpSettings = getWpSettings()
  const baseUrl = wpSettings?.pluginUrl || '/wp-content/plugins/wp-sms/'
  return `${baseUrl}assets/images/avatar.png`
}

/**
 * Get contact icon URL
 */
function getContactIconUrl(type) {
  const wpSettings = getWpSettings()
  const baseUrl = wpSettings?.pluginUrl || '/wp-content/plugins/wp-sms/'
  return `${baseUrl}assets/images/chatbox/icon-${type || 'whatsapp'}.svg`
}

export default function MessageButton() {
  // Message button toggle
  const [messageButton, setMessageButton] = useSetting('chatbox_message_button', '')
  const [chatboxTitle, setChatboxTitle] = useSetting('chatbox_title', '')

  // Button appearance
  const [buttonText, setButtonText] = useSetting('chatbox_button_text', '')
  const [buttonPosition, setButtonPosition] = useSetting('chatbox_button_position', 'bottom_right')

  // Colors
  const [chatboxColor, setChatboxColor] = useSetting('chatbox_color', '#00a9c0')
  const [chatboxTextColor, setChatboxTextColor] = useSetting('chatbox_text_color', '#ffffff')

  // Footer
  const [footerText, setFooterText] = useSetting('chatbox_footer_text', '')
  const [footerTextColor, setFooterTextColor] = useSetting('chatbox_footer_text_color', '')
  const [footerLinkTitle, setFooterLinkTitle] = useSetting('chatbox_footer_link_title', '')
  const [footerLinkUrl, setFooterLinkUrl] = useSetting('chatbox_footer_link_url', '')

  // Animation
  const [animationEffect, setAnimationEffect] = useSetting('chatbox_animation_effect', '')
  const [disableLogo, setDisableLogo] = useSetting('chatbox_disable_logo', '')

  // Resource links
  const [linksEnabled, setLinksEnabled] = useSetting('chatbox_links_enabled', '')
  const [linksTitle, setLinksTitle] = useSetting('chatbox_links_title', '')
  const [chatboxLinks, setChatboxLinks] = useSetting('chatbox_links', [])

  // Team members
  const [teamMembers, setTeamMembers] = useSetting('chatbox_team_members', [])

  // Get hasChanges to know when save bar is visible
  const { hasChanges } = useSettings()

  const isEnabled = messageButton === '1'

  // Ensure chatbox is hidden when leaving this page
  useEffect(() => {
    return () => {
      const chatbox = document.querySelector('.wpsms-chatbox')
      if (chatbox) {
        chatbox.classList.remove('wpsms-chatbox--visible')
        const content = chatbox.querySelector('.wpsms-chatbox__content')
        if (content) {
          content.classList.remove('open')
          content.style.display = 'none'
        }
      }
    }
  }, [])

  // Handle close actions in preview to properly hide content
  useEffect(() => {
    const chatbox = document.querySelector('.wpsms-chatbox')
    if (!chatbox) return

    const closeButton = chatbox.querySelector('.js-wpsms-chatbox__close-button')
    const chatboxButton = chatbox.querySelector('.js-wpsms-chatbox__button')
    const content = chatbox.querySelector('.wpsms-chatbox__content')

    const handleClose = (e) => {
      e.stopPropagation()
      if (content) {
        content.classList.remove('open', 'opening')
        content.style.display = 'none'
      }
      document.body.classList.remove('chatbox-open')
    }

    // Handle chatbox button click (toggles open/close)
    const handleButtonClick = (e) => {
      if (content && content.classList.contains('open')) {
        // Closing - intercept and handle properly
        e.stopPropagation()
        content.classList.remove('open', 'opening')
        content.style.display = 'none'
        document.body.classList.remove('chatbox-open')
      }
      // If not open, let original handler open it
    }

    if (closeButton) {
      closeButton.addEventListener('click', handleClose, true)
    }
    if (chatboxButton) {
      chatboxButton.addEventListener('click', handleButtonClick, true)
    }

    return () => {
      if (closeButton) {
        closeButton.removeEventListener('click', handleClose, true)
      }
      if (chatboxButton) {
        chatboxButton.removeEventListener('click', handleButtonClick, true)
      }
    }
  }, [])

  // Update chatbox preview whenever settings change
  useEffect(() => {
    const chatbox = document.querySelector('.wpsms-chatbox')
    if (!chatbox) return

    // Move chatbox higher when save bar is visible (hasChanges)
    // Add smooth transition for the animation
    chatbox.style.transition = 'bottom 0.3s ease'
    chatbox.style.bottom = hasChanges ? '80px' : '2rem'

    const chatboxContent = chatbox.querySelector('.wpsms-chatbox__content')
    if (chatboxContent) {
      chatboxContent.style.transition = 'bottom 0.3s ease'
      chatboxContent.style.bottom = hasChanges ? '144px' : '96px'
    }

    const chatboxArrow = chatbox.querySelector('.wpsms-chatbox__arrow')
    if (chatboxArrow) {
      chatboxArrow.style.transition = 'bottom 0.3s ease'
      chatboxArrow.style.bottom = hasChanges ? '125px' : '77px'
    }

    // Update button text
    const buttonTitle = chatbox.querySelector('.wpsms-chatbox__button-title')
    if (buttonTitle) {
      buttonTitle.textContent = buttonText || __('Talk to Us')
    }

    // Update header title
    const headerTitle = chatbox.querySelector('.wpsms-chatbox__header h2')
    if (headerTitle) {
      headerTitle.textContent = chatboxTitle || __('Chat with Us!')
    }

    // Update colors
    const primaryColor = chatboxColor || '#00a9c0'
    const textColor = chatboxTextColor || '#ffffff'

    const button = chatbox.querySelector('.wpsms-chatbox__button')
    if (button) {
      button.style.backgroundColor = primaryColor
      button.style.color = textColor
    }

    const header = chatbox.querySelector('.wpsms-chatbox__header')
    if (header) {
      header.style.backgroundColor = primaryColor
      header.style.color = textColor
    }

    // Update SVG fill colors in button
    const svgPaths = chatbox.querySelectorAll('.wpsms-chatbox__button svg path, .wpsms-chatbox__button svg')
    svgPaths.forEach(el => {
      if (el.hasAttribute('fill') && el.getAttribute('fill') !== 'none') {
        el.setAttribute('fill', textColor)
      }
      if (el.hasAttribute('stroke')) {
        el.setAttribute('stroke', textColor)
      }
    })

    // Update position
    chatbox.classList.remove('wpsms-chatbox--right-side', 'wpsms-chatbox--left-side')
    chatbox.classList.add(buttonPosition === 'bottom_left' ? 'wpsms-chatbox--left-side' : 'wpsms-chatbox--right-side')

    // Update animation effect
    const content = chatbox.querySelector('.wpsms-chatbox__content')
    if (content) {
      content.classList.remove('wpsms-chatbox__content--fade', 'wpsms-chatbox__content--slide')
      if (animationEffect) {
        content.classList.add(`wpsms-chatbox__content--${animationEffect}`)
      }
    }

    // Update footer text
    const footerTextEl = chatbox.querySelector('.wpsms-chatbox__info--text')
    if (footerTextEl) {
      // Preserve any existing link
      const existingLink = footerTextEl.querySelector('a')
      footerTextEl.textContent = footerText || __('Chat with us on WhatsApp for instant support!')

      // Add footer link if present
      if (footerLinkUrl && footerLinkTitle) {
        const link = document.createElement('a')
        link.href = footerLinkUrl
        link.textContent = footerLinkTitle
        footerTextEl.appendChild(document.createTextNode(' '))
        footerTextEl.appendChild(link)
      }

      // Update footer text color
      if (footerTextColor) {
        footerTextEl.style.color = footerTextColor
      }
    }

    // Update team members
    const teamsContainer = chatbox.querySelector('.wpsms-chatbox__teams')
    if (teamsContainer) {
      teamsContainer.innerHTML = ''

      const members = Array.isArray(teamMembers) ? teamMembers : []
      members.forEach(member => {
        if (!member.member_name && !member.member_role) return

        const memberName = member.member_name || __('Emily Brown')
        const memberRole = member.member_role || __('Marketing Manager')
        const memberAvailability = member.member_availability || __('Available 10AM-5PM PST')
        const memberPhoto = member.member_photo || getDefaultAvatarUrl()
        const contactType = member.member_contact_type || 'whatsapp'
        const contactValue = member.member_contact_value || '+1122334455'
        const contactLink = generateContactLink(contactType, contactValue)
        const contactIcon = getContactIconUrl(contactType)

        const teamEl = document.createElement('a')
        teamEl.href = contactLink
        teamEl.target = '_blank'
        teamEl.className = 'wpsms-chatbox__team'
        teamEl.innerHTML = `
          <div class="wpsms-chatbox__team-avatar">
            <span class="wpsms-chatbox__team-icon messenger" style="background-color: ${primaryColor}">
              <img src="${contactIcon}"/>
            </span>
            <img class="wpsms-chatbox__team-avatar-img" src="${memberPhoto}" loading="lazy" width="56" height="56" alt="${memberName}">
          </div>
          <div class="wpsms-chatbox__team-info">
            <ul class="wpsms-chatbox__team-list">
              <li class="wpsms-chatbox__team-item">${memberRole}</li>
              <li class="wpsms-chatbox__team-item wpsms-chatbox__team-name">${memberName}</li>
              <li class="wpsms-chatbox__team-item wpsms-chatbox__team-status">
                <span class="online dot"></span>
                <span>${memberAvailability}</span>
              </li>
            </ul>
          </div>
        `
        teamsContainer.appendChild(teamEl)
      })
    }

    // Update links section
    const articlesContainer = chatbox.querySelector('.wpsms-chatbox__articles')
    if (linksEnabled === '1') {
      if (!articlesContainer) {
        // Create links section if it doesn't exist
        const container = chatbox.querySelector('.wpsms-chatbox__container')
        if (container) {
          const articlesEl = document.createElement('div')
          articlesEl.className = 'wpsms-chatbox__articles'
          articlesEl.innerHTML = `<ul><li class="wpsms-chatbox__articles-header">${linksTitle || __('Quick Links')}</li></ul>`
          container.appendChild(articlesEl)
        }
      } else {
        // Show and update existing links section
        articlesContainer.style.display = ''
        const ul = articlesContainer.querySelector('ul')
        if (ul) {
          ul.innerHTML = `<li class="wpsms-chatbox__articles-header">${linksTitle || __('Quick Links')}</li>`

          const links = Array.isArray(chatboxLinks) ? chatboxLinks : []
          links.forEach(link => {
            if (!link.chatbox_link_title) return

            const li = document.createElement('li')
            li.className = 'wpsms-chatbox__article'
            li.innerHTML = `
              <a href="${link.chatbox_link_url || '#'}" title="${link.chatbox_link_title}">
                ${link.chatbox_link_title}
                <span>
                  <svg width="6" height="10" viewBox="0 0 6 10" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M5 9L1 5L5 1" stroke="#4F7EF6" stroke-miterlimit="10" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </span>
              </a>
            `
            ul.appendChild(li)
          })
        }
      }
    } else if (articlesContainer) {
      articlesContainer.style.display = 'none'
    }

    // Update branding visibility
    const branding = chatbox.querySelector('.wpsms-chatbox__copy-right')
    if (branding) {
      branding.style.display = disableLogo === '1' ? 'none' : ''
    }

  }, [
    hasChanges,
    buttonText,
    chatboxTitle,
    chatboxColor,
    chatboxTextColor,
    buttonPosition,
    animationEffect,
    footerText,
    footerTextColor,
    footerLinkTitle,
    footerLinkUrl,
    teamMembers,
    linksEnabled,
    linksTitle,
    chatboxLinks,
    disableLogo,
  ])

  // Toggle chatbox preview visibility
  const handlePreviewClick = useCallback(() => {
    const chatbox = document.querySelector('.wpsms-chatbox')
    if (chatbox) {
      chatbox.classList.toggle('wpsms-chatbox--visible')
    }
  }, [])

  return (
    <div className="wsms-space-y-6">
      {/* Message Button Toggle */}
      <Card>
        <CardHeader>
          <div className="wsms-flex wsms-items-start wsms-justify-between">
            <div>
              <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                <MessageSquare className="wsms-h-5 wsms-w-5" />
                {__('Message Button Configuration')}
              </CardTitle>
              <CardDescription>
                {__('Display a floating message button on your website for quick communication')}
              </CardDescription>
            </div>
            <Button
              variant="outline"
              size="sm"
              onClick={handlePreviewClick}
              className="wsms-flex wsms-items-center wsms-gap-1.5"
            >
              <Eye className="wsms-h-4 wsms-w-4" />
              {__('Preview')}
            </Button>
          </div>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <SettingRow
            title={__('Enable Message Button')}
            description={__('Show a floating chat button on your website for visitor inquiries.')}
            checked={isEnabled}
            onCheckedChange={(checked) => setMessageButton(checked ? '1' : '')}
          />

          {isEnabled && (
            <InputField
              label={__('Chat Window Title')}
              value={chatboxTitle}
              onChange={(e) => setChatboxTitle(e.target.value)}
              placeholder={__('How can we help?')}
              description={__('Heading shown when the chat window opens.')}
            />
          )}
        </CardContent>
      </Card>

      {isEnabled && (
        <>
          {/* Button Appearance */}
          <Card>
            <CardHeader>
              <CardTitle>{__('Button Appearance')}</CardTitle>
              <CardDescription>
                {__('Customize how the message button looks and where it appears')}
              </CardDescription>
            </CardHeader>
            <CardContent className="wsms-space-y-4">
              <InputField
                label={__('Button Label')}
                value={buttonText}
                onChange={(e) => setButtonText(e.target.value)}
                placeholder={__('Chat with us')}
                description={__('Text shown on the floating button.')}
              />

              <SelectField
                label={__('Button Position')}
                value={buttonPosition}
                onValueChange={setButtonPosition}
                placeholder={__('Select position')}
                description={__('Where the button appears on screen.')}
                options={[
                  { value: 'bottom_right', label: __('Bottom Right') },
                  { value: 'bottom_left', label: __('Bottom Left') },
                ]}
              />

              <SelectField
                label={__('Open Animation')}
                value={animationEffect || 'none'}
                onValueChange={(val) => setAnimationEffect(val === 'none' ? '' : val)}
                placeholder={__('Select animation')}
                description={__('How the chat window appears when opened.')}
                options={[
                  { value: 'none', label: __('None') },
                  { value: 'fade', label: __('Fade In') },
                  { value: 'slide', label: __('Slide Up') },
                ]}
              />
            </CardContent>
          </Card>

          {/* Colors */}
          <Card>
            <CardHeader>
              <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                <Palette className="wsms-h-5 wsms-w-5" />
                {__('Colors')}
              </CardTitle>
              <CardDescription>
                {__('Customize the chatbox color scheme')}
              </CardDescription>
            </CardHeader>
            <CardContent className="wsms-space-y-4">
              <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4">
                <div className="wsms-space-y-2">
                  <Label htmlFor="chatboxColor">{__('Primary Color')}</Label>
                  <div className="wsms-flex wsms-gap-2">
                    <Input
                      id="chatboxColor"
                      type="color"
                      value={chatboxColor}
                      onChange={(e) => setChatboxColor(e.target.value)}
                      className="wsms-h-10 wsms-w-14 wsms-p-1"
                    />
                    <Input
                      value={chatboxColor}
                      onChange={(e) => setChatboxColor(e.target.value)}
                      placeholder="#00a9c0"
                    />
                  </div>
                  <p className="wsms-text-[12px] wsms-text-muted-foreground">
                    {__('Background color for the button and chat header.')}
                  </p>
                </div>

                <div className="wsms-space-y-2">
                  <Label htmlFor="chatboxTextColor">{__('Text Color')}</Label>
                  <div className="wsms-flex wsms-gap-2">
                    <Input
                      id="chatboxTextColor"
                      type="color"
                      value={chatboxTextColor}
                      onChange={(e) => setChatboxTextColor(e.target.value)}
                      className="wsms-h-10 wsms-w-14 wsms-p-1"
                    />
                    <Input
                      value={chatboxTextColor}
                      onChange={(e) => setChatboxTextColor(e.target.value)}
                      placeholder="#ffffff"
                    />
                  </div>
                  <p className="wsms-text-[12px] wsms-text-muted-foreground">
                    {__('Text color for the button and header.')}
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Footer Settings */}
          <Card>
            <CardHeader>
              <CardTitle>{__('Footer Settings')}</CardTitle>
              <CardDescription>
                {__('Customize the chatbox footer area')}
              </CardDescription>
            </CardHeader>
            <CardContent className="wsms-space-y-4">
              <InputField
                label={__('Footer Message')}
                value={footerText}
                onChange={(e) => setFooterText(e.target.value)}
                placeholder={__('We typically reply within minutes')}
                description={__('Optional message shown at the bottom of the chat window.')}
              />

              <div className="wsms-space-y-2">
                <Label htmlFor="footerTextColor">{__('Footer Text Color')}</Label>
                <div className="wsms-flex wsms-gap-2">
                  <Input
                    id="footerTextColor"
                    type="color"
                    value={footerTextColor || '#666666'}
                    onChange={(e) => setFooterTextColor(e.target.value)}
                    className="wsms-h-10 wsms-w-14 wsms-p-1"
                  />
                  <Input
                    value={footerTextColor}
                    onChange={(e) => setFooterTextColor(e.target.value)}
                    placeholder="#666666"
                  />
                </div>
              </div>

              <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4">
                <InputField
                  label={__('Footer Link Text')}
                  value={footerLinkTitle}
                  onChange={(e) => setFooterLinkTitle(e.target.value)}
                  placeholder={__('View FAQ')}
                />
                <InputField
                  label={__('Footer Link URL')}
                  value={footerLinkUrl}
                  onChange={(e) => setFooterLinkUrl(e.target.value)}
                  placeholder="https://yoursite.com/help"
                />
              </div>

              <SettingRow
                title={__('Hide WSMS Branding')}
                description={__('Remove the "Powered by WSMS" text from the footer.')}
                checked={disableLogo === '1'}
                onCheckedChange={(checked) => setDisableLogo(checked ? '1' : '')}
              />
            </CardContent>
          </Card>

          {/* Team Members */}
          <Card>
            <CardHeader>
              <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                <Users className="wsms-h-5 wsms-w-5" />
                {__('Support Team')}
              </CardTitle>
              <CardDescription>
                {__('Add team members that visitors can contact directly.')}
              </CardDescription>
            </CardHeader>
            <CardContent className="wsms-space-y-4">
              <Repeater
                value={teamMembers}
                onValueChange={setTeamMembers}
                fields={[
                  { name: 'member_name', label: __('Name'), type: 'text', placeholder: __('Jane Smith') },
                  { name: 'member_role', label: __('Role'), type: 'text', placeholder: __('Customer Support') },
                  { name: 'member_contact_type', label: __('Contact Type'), type: 'select', placeholder: __('Select...'), options: [
                    { value: 'whatsapp', label: 'WhatsApp' },
                    { value: 'telegram', label: 'Telegram' },
                    { value: 'sms', label: 'SMS' },
                    { value: 'phone', label: __('Phone Call') },
                    { value: 'email', label: __('Email') },
                  ]},
                  { name: 'member_contact_value', label: __('Contact Value'), type: 'text', placeholder: '+1 555 123 4567' },
                  { name: 'member_availability', label: __('Availability'), type: 'text', placeholder: __('Available 9AM-5PM') },
                  { name: 'member_photo', label: __('Avatar'), type: 'media', buttonText: __('Select Avatar') },
                ]}
                addLabel={__('Add Team Member')}
                maxItems={5}
                emptyMessage={__('No team members added yet.')}
              />
            </CardContent>
          </Card>

          {/* Resource Links */}
          <Card>
            <CardHeader>
              <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                <Link className="wsms-h-5 wsms-w-5" />
                {__('Quick Links')}
              </CardTitle>
              <CardDescription>
                {__('Add helpful resource links to the chatbox')}
              </CardDescription>
            </CardHeader>
            <CardContent className="wsms-space-y-4">
              <SettingRow
                title={__('Show Quick Links')}
                description={__('Display helpful links in the chat window.')}
                checked={linksEnabled === '1'}
                onCheckedChange={(checked) => setLinksEnabled(checked ? '1' : '')}
              />

              {linksEnabled === '1' && (
                <>
                  <InputField
                    label={__('Links Section Title')}
                    value={linksTitle}
                    onChange={(e) => setLinksTitle(e.target.value)}
                    placeholder={__('Helpful Resources')}
                  />

                  <div className="wsms-space-y-2">
                    <Label>{__('Resource Links')}</Label>
                    <Repeater
                      value={chatboxLinks}
                      onValueChange={setChatboxLinks}
                      fields={[
                        { name: 'chatbox_link_title', label: __('Title'), type: 'text', placeholder: __('FAQ') },
                        { name: 'chatbox_link_url', label: __('URL'), type: 'url', placeholder: 'https://example.com/faq' },
                      ]}
                      addLabel={__('Add Link')}
                      maxItems={10}
                      emptyMessage={__('No links added. Add links to help visitors find answers quickly.')}
                    />
                  </div>
                </>
              )}
            </CardContent>
          </Card>
        </>
      )}
    </div>
  )
}
