export type TeamMember = {
  member_name: string
  member_role: string
  member_photo: string
  member_availability: string
  member_contact_type: 'whatsapp' | 'call' | 'facebook' | 'telegram' | 'sms' | 'email'
  member_contact_value: string
}

export type ChatboxLink = {
  link_title: string
  link_url: string
  link_icon?: string
}

export type ChatboxSettings = {
  chatbox_message_button: boolean
  chatbox_title: string
  chatbox_button_text: string
  chatbox_button_position: 'bottom_right' | 'bottom_left'
  chatbox_color: string
  chatbox_text_color: string
  chatbox_footer_text: string
  chatbox_footer_text_color: string
  chatbox_footer_link_title: string
  chatbox_footer_link_url: string
  chatbox_animation_effect: '' | 'fade' | 'slide'
  chatbox_disable_logo: boolean
  chatbox_links_enabled: boolean
  chatbox_links_title: string
  chatbox_team_members: TeamMember[]
  chatbox_links: ChatboxLink[]
}
