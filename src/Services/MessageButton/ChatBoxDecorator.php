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
        return $this->getData('chatbox_footer_text', __('All rights reserved.', 'wp-sms'));
    }

    public function getFooterLinkUrl()
    {
        return $this->getData('chatbox_footer_link_url');
    }

    public function getFooterLinkTitle()
    {
        return $this->getData('chatbox_footer_link_title', __('Learn More', 'wp-sms'));
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

    public function getLinkTitle()
    {
        return $this->getData('chatbox_links_title', __('Quick Links', 'wp-sms'));
    }

    public function getButtonPosition()
    {
        return $this->getData('chatbox_button_position');
    }

    public function fetchTeamMembers()
    {
        $teamData      = $this->getData('chatbox_team_members');
        $processedTeam = [];

        foreach ($teamData as $teamMember) {
            $teamMember['contact_link'] = $this->generateContactLink($teamMember['member_contact_type'], $teamMember['member_contact_value']);
            $teamMember['member_photo'] = $teamMember['member_photo'] ?? WP_SMS_URL . 'assets/images/avatar.png';
            $processedTeam[]            = $teamMember;
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
        } else {
            $linkUrl = 'tel:' . $value;
        }

        return $linkUrl;
    }

    public function fetchLinks()
    {
        return $this->getData('chatbox_links', []);
    }
}