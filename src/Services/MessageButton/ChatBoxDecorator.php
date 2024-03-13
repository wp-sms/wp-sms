<?php

namespace WP_SMS\Services\MessageButton;

use WP_SMS\Option;

class ChatBoxDecorator
{
    private function getData($key, $default = false)
    {
        $value = Option::getOption($key);

        return !empty($value) ? $value : $default;
    }

    public function isEnabled()
    {
        return $this->getData('chatbox_message_button');
    }

    public function getTitle()
    {
        return $this->getData('chatbox_title', __('Chat with Us!', 'wp-sms'));
    }

    public function getButtonText()
    {
        return $this->getData('chatbox_button_text', __('Talk to Us', 'wp-sms'));
    }

    public function getFooterText()
    {
        return $this->getData('chatbox_footer_text', __('Chat with us on WhatsApp for instant support!', 'wp-sms'));
    }

    public function getFooterLinkUrl()
    {
        return $this->getData('chatbox_footer_link_url');
    }

    public function getFooterLinkTitle()
    {
        return $this->getData('chatbox_footer_link_title', __('Related Articles', 'wp-sms'));
    }

    public function getFooterTextColor()
    {
        return $this->getData('chatbox_footer_text_color');
    }

    public function getTextColor($default = false)
    {
        return $this->getData('chatbox_text_color', $default);
    }

    public function getColor()
    {
        return $this->getData('chatbox_color');
    }

    public function getAnimationEffect()
    {
        return $this->getData('chatbox_animation_effect');
    }

    public function isLinkEnabled()
    {
        return $this->getData('chatbox_links_enabled');
    }

    public function isFooterLogoEnabled()
    {
        return $this->getData('chatbox_disable_logo', 'enable') == 'enable';
    }

    public function getLinkTitle()
    {
        return $this->getData('chatbox_links_title', __('Quick Links', 'wp-sms'));
    }

    public function getButtonPosition()
    {
        return $this->getData('chatbox_button_position', 'bottom_right');
    }

    public function fetchTeamMembers()
    {
        $teams         = $this->getData('chatbox_team_members', []);
        $processedTeam = [];

        // Loop through each team member
        foreach ($teams as &$teamMember) {
            // Check and replace empty values with sample data
            if ($teamMember['member_name'] == '') {
                $teamMember['member_name'] = __('Emily Brown', 'wp-sms');
            }
            if ($teamMember['member_role'] == '') {
                $teamMember['member_role'] = __('Marketing Manager', 'wp-sms');
            }
            if ($teamMember['member_availability'] == '') {
                $teamMember['member_availability'] = __('Available 10AM-5PM PST', 'wp-sms');
            }
            if ($teamMember['member_photo'] == '') {
                $teamMember['member_photo'] = WP_SMS_URL . 'assets/images/avatar.png';
            }
            if ($teamMember['member_contact_value'] == '') {
                $teamMember['member_contact_value'] = '+1122334455';
            }
            if ($teamMember['member_contact_type'] == '') {
                $teamMember['member_contact_type'] = 'whatsapp';
            }

            // Process each team member
            $teamMember['contact_link']      = $this->generateContactLink($teamMember['member_contact_type'], $teamMember['member_contact_value']);
            $teamMember['contact_link_icon'] = sprintf('%s/assets/images/chatbox/icon-%s.svg', WP_SMS_URL, $teamMember['member_contact_type']);

            $processedTeam[] = $teamMember;
        }

        return $processedTeam;
    }

    private function generateContactLink($type, $value)
    {
        $value = trim($value);

        if ($type === 'whatsapp') {
            $linkUrl = 'https://wa.me/' . $value;
        } else if ($type === 'telegram') {
            $linkUrl = 'https://t.me/' . $value;
        } else if ($type === 'facebook') {
            $linkUrl = 'https://me.me/' . $value;
        } else if ($type === 'sms') {
            $linkUrl = 'sms:' . $value;
        } else if ($type === 'email') {
            $linkUrl = 'mailto:' . $value;
        } else {
            $linkUrl = 'tel:' . $value;
        }

        return apply_filters('wp_sms_chatbox_contact_link', $linkUrl, $type, $value);
    }

    public function fetchLinks()
    {
        $links         = $this->getData('chatbox_links', []);
        $processedLink = [];

        // Loop through each team member
        foreach ($links as &$teamMember) {
            // Check and replace empty values with sample data
            if ($teamMember['chatbox_link_title'] == '') {
                $teamMember['chatbox_link_title'] = __('Troubleshooting Common Issues', 'wp-sms');
            }
            if ($teamMember['chatbox_link_url'] == '') {
                $teamMember['chatbox_link_url'] = site_url('troubleshooting');
            }

            $processedLink[] = $teamMember;
        }

        return $processedLink;
    }
}