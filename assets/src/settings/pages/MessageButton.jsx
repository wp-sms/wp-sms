import React, { useCallback, useEffect } from 'react'
import { MessageSquare, Palette, Users, Link, Sparkles, Eye } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardHeader, CardTitle, CardDescription, CardContent } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Switch } from '@/components/ui/switch'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Repeater } from '@/components/ui/repeater'
import { useSetting } from '@/context/SettingsContext'

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

  const isEnabled = messageButton === '1'

  // Ensure chatbox is hidden when leaving this page
  useEffect(() => {
    return () => {
      const chatbox = document.querySelector('.wpsms-chatbox')
      if (chatbox) {
        chatbox.classList.remove('wpsms-chatbox--visible')
      }
    }
  }, [])

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
                Message Button Configuration
              </CardTitle>
              <CardDescription>
                Display a floating message button on your website for quick communication
              </CardDescription>
            </div>
            <Button
              variant="outline"
              size="sm"
              onClick={handlePreviewClick}
              className="wsms-flex wsms-items-center wsms-gap-1.5"
            >
              <Eye className="wsms-h-4 wsms-w-4" />
              Preview
            </Button>
          </div>
        </CardHeader>
        <CardContent className="wsms-space-y-4">
          <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
            <div>
              <p className="wsms-font-medium">Enable Message Button</p>
              <p className="wsms-text-sm wsms-text-muted-foreground">
                Show a floating chat button on your website for visitor inquiries.
              </p>
            </div>
            <Switch
              checked={isEnabled}
              onCheckedChange={(checked) => setMessageButton(checked ? '1' : '')}
              aria-label="Enable message button"
            />
          </div>

          {isEnabled && (
            <div className="wsms-space-y-2">
              <Label htmlFor="chatboxTitle">Chat Window Title</Label>
              <Input
                id="chatboxTitle"
                value={chatboxTitle}
                onChange={(e) => setChatboxTitle(e.target.value)}
                placeholder="How can we help?"
              />
              <p className="wsms-text-xs wsms-text-muted-foreground">
                Heading shown when the chat window opens.
              </p>
            </div>
          )}
        </CardContent>
      </Card>

      {isEnabled && (
        <>
          {/* Button Appearance */}
          <Card>
            <CardHeader>
              <CardTitle>Button Appearance</CardTitle>
              <CardDescription>
                Customize how the message button looks and where it appears
              </CardDescription>
            </CardHeader>
            <CardContent className="wsms-space-y-4">
              <div className="wsms-space-y-2">
                <Label htmlFor="buttonText">Button Label</Label>
                <Input
                  id="buttonText"
                  value={buttonText}
                  onChange={(e) => setButtonText(e.target.value)}
                  placeholder="Chat with us"
                />
                <p className="wsms-text-xs wsms-text-muted-foreground">
                  Text shown on the floating button.
                </p>
              </div>

              <div className="wsms-space-y-2">
                <Label>Button Position</Label>
                <Select value={buttonPosition} onValueChange={setButtonPosition}>
                  <SelectTrigger aria-label="Button position">
                    <SelectValue placeholder="Select position" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="bottom_right">Bottom Right</SelectItem>
                    <SelectItem value="bottom_left">Bottom Left</SelectItem>
                  </SelectContent>
                </Select>
                <p className="wsms-text-xs wsms-text-muted-foreground">
                  Where the button appears on screen.
                </p>
              </div>

              <div className="wsms-space-y-2">
                <Label>Open Animation</Label>
                <Select
                  value={animationEffect || 'none'}
                  onValueChange={(val) => setAnimationEffect(val === 'none' ? '' : val)}
                >
                  <SelectTrigger aria-label="Open animation">
                    <SelectValue placeholder="Select animation" />
                  </SelectTrigger>
                  <SelectContent>
                    <SelectItem value="none">None</SelectItem>
                    <SelectItem value="fade">Fade In</SelectItem>
                    <SelectItem value="slide">Slide Up</SelectItem>
                  </SelectContent>
                </Select>
                <p className="wsms-text-xs wsms-text-muted-foreground">
                  How the chat window appears when opened.
                </p>
              </div>
            </CardContent>
          </Card>

          {/* Colors */}
          <Card>
            <CardHeader>
              <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                <Palette className="wsms-h-5 wsms-w-5" />
                Colors
              </CardTitle>
              <CardDescription>
                Customize the chatbox color scheme
              </CardDescription>
            </CardHeader>
            <CardContent className="wsms-space-y-4">
              <div className="wsms-grid wsms-grid-cols-2 wsms-gap-4">
                <div className="wsms-space-y-2">
                  <Label htmlFor="chatboxColor">Primary Color</Label>
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
                  <p className="wsms-text-xs wsms-text-muted-foreground">
                    Background color for the button and chat header.
                  </p>
                </div>

                <div className="wsms-space-y-2">
                  <Label htmlFor="chatboxTextColor">Text Color</Label>
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
                  <p className="wsms-text-xs wsms-text-muted-foreground">
                    Text color for the button and header.
                  </p>
                </div>
              </div>
            </CardContent>
          </Card>

          {/* Footer Settings */}
          <Card>
            <CardHeader>
              <CardTitle>Footer Settings</CardTitle>
              <CardDescription>
                Customize the chatbox footer area
              </CardDescription>
            </CardHeader>
            <CardContent className="wsms-space-y-4">
              <div className="wsms-space-y-2">
                <Label htmlFor="footerText">Footer Message</Label>
                <Input
                  id="footerText"
                  value={footerText}
                  onChange={(e) => setFooterText(e.target.value)}
                  placeholder="We typically reply within minutes"
                />
                <p className="wsms-text-xs wsms-text-muted-foreground">
                  Optional message shown at the bottom of the chat window.
                </p>
              </div>

              <div className="wsms-space-y-2">
                <Label htmlFor="footerTextColor">Footer Text Color</Label>
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
                <div className="wsms-space-y-2">
                  <Label htmlFor="footerLinkTitle">Footer Link Text</Label>
                  <Input
                    id="footerLinkTitle"
                    value={footerLinkTitle}
                    onChange={(e) => setFooterLinkTitle(e.target.value)}
                    placeholder="View FAQ"
                  />
                </div>
                <div className="wsms-space-y-2">
                  <Label htmlFor="footerLinkUrl">Footer Link URL</Label>
                  <Input
                    id="footerLinkUrl"
                    value={footerLinkUrl}
                    onChange={(e) => setFooterLinkUrl(e.target.value)}
                    placeholder="https://yoursite.com/help"
                  />
                </div>
              </div>

              <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
                <div>
                  <p className="wsms-font-medium">Hide WP SMS Branding</p>
                  <p className="wsms-text-sm wsms-text-muted-foreground">
                    Remove the "Powered by WP SMS" text from the footer.
                  </p>
                </div>
                <Switch
                  checked={disableLogo === '1'}
                  onCheckedChange={(checked) => setDisableLogo(checked ? '1' : '')}
                  aria-label="Hide WP SMS branding"
                />
              </div>
            </CardContent>
          </Card>

          {/* Team Members */}
          <Card>
            <CardHeader>
              <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                <Users className="wsms-h-5 wsms-w-5" />
                Support Team
              </CardTitle>
              <CardDescription>
                Add team members that visitors can contact directly.
              </CardDescription>
            </CardHeader>
            <CardContent className="wsms-space-y-4">
              <Repeater
                value={teamMembers}
                onValueChange={setTeamMembers}
                fields={[
                  { name: 'name', label: 'Name', type: 'text', placeholder: 'Jane Smith' },
                  { name: 'role', label: 'Role', type: 'text', placeholder: 'Customer Support' },
                  { name: 'phone', label: 'Phone', type: 'tel', placeholder: '+1 555 123 4567' },
                  { name: 'avatar', label: 'Avatar URL', type: 'url', placeholder: 'https://...' },
                ]}
                addLabel="Add Team Member"
                maxItems={5}
                emptyMessage="No team members added yet."
              />
            </CardContent>
          </Card>

          {/* Resource Links */}
          <Card>
            <CardHeader>
              <CardTitle className="wsms-flex wsms-items-center wsms-gap-2">
                <Link className="wsms-h-5 wsms-w-5" />
                Quick Links
              </CardTitle>
              <CardDescription>
                Add helpful resource links to the chatbox
              </CardDescription>
            </CardHeader>
            <CardContent className="wsms-space-y-4">
              <div className="wsms-flex wsms-items-center wsms-justify-between wsms-rounded-lg wsms-border wsms-p-4">
                <div>
                  <p className="wsms-font-medium">Show Quick Links</p>
                  <p className="wsms-text-sm wsms-text-muted-foreground">
                    Display helpful links in the chat window.
                  </p>
                </div>
                <Switch
                  checked={linksEnabled === '1'}
                  onCheckedChange={(checked) => setLinksEnabled(checked ? '1' : '')}
                  aria-label="Show quick links"
                />
              </div>

              {linksEnabled === '1' && (
                <>
                  <div className="wsms-space-y-2">
                    <Label htmlFor="linksTitle">Links Section Title</Label>
                    <Input
                      id="linksTitle"
                      value={linksTitle}
                      onChange={(e) => setLinksTitle(e.target.value)}
                      placeholder="Helpful Resources"
                    />
                  </div>

                  <div className="wsms-space-y-2">
                    <Label>Resource Links</Label>
                    <Repeater
                      value={chatboxLinks}
                      onValueChange={setChatboxLinks}
                      fields={[
                        { name: 'title', label: 'Title', type: 'text', placeholder: 'FAQ' },
                        { name: 'url', label: 'URL', type: 'url', placeholder: 'https://example.com/faq' },
                      ]}
                      addLabel="Add Link"
                      maxItems={10}
                      emptyMessage="No links added. Add links to help visitors find answers quickly."
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
