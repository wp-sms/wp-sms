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
        return $this->getData('chatbox_button_position', 'bottom_right');
    }

    public function fetchTeamMembers()
    {
        $teamData      = $this->getData('chatbox_team_members');
        $processedTeam = [];

        //if (!$teamData) { // todo this is not working since the multidimensional array should have values
        $teamData = [
            [
                'member_name'          => 'Emily Brown',
                'member_role'          => 'Marketing Manager',
                'member_availability'  => 'Available 10AM-5PM PST',
                'member_contact_type'  => 'email',
                'member_contact_value' => 'emily@example.com',
            ],
            [
                'member_name'          => 'Michael Johnson',
                'member_role'          => 'Sales Representative',
                'member_availability'  => 'Busy',
                'member_contact_type'  => 'whatsapp',
                'member_contact_value' => '+1122334455',
            ],
            [
                'member_name'          => 'Sophia Lee',
                'member_role'          => 'Customer Support',
                'member_availability'  => 'Available 11AM-6PM PST',
                'member_contact_type'  => 'sms',
                'member_contact_value' => '+1122334466',
            ],
            [
                'member_name'          => 'David Smith',
                'member_role'          => 'Software Engineer',
                'member_availability'  => 'Available 5PM-10PM PST',
                'member_contact_type'  => 'tel',
                'member_contact_value' => '+11223344777',
            ]
        ];
        //}

        foreach ($teamData as $teamMember) {
            $teamMember['contact_link']      = $this->generateContactLink($teamMember['member_contact_type'], $teamMember['member_contact_value']);
            $teamMember['contact_link_icon'] = sprintf('%s/assets/images/chatbox/icon-%s.svg', WP_SMS_URL, $teamMember['member_contact_type']);
            $teamMember['member_photo']      = $teamMember['member_photo'] ?? WP_SMS_URL . 'assets/images/avatar.png';

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

        return $linkUrl;
    }

    public function fetchLinks()
    {
        return $this->getData('chatbox_links', []);
    }
}