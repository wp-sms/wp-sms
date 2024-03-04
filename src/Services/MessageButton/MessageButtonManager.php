<?php

namespace WP_SMS\Services\MessageButton;

use WP_SMS\Components\Assets;
use WP_SMS\Option;

class MessageButtonManager
{
    public function init()
    {
        if (ChatBox::isEnabled()) {
            add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
            add_action('wp_footer', [$this, 'renderChatBox']);
            add_action('admin_init', [$this, 'renderPreviewInAdmin']);
        }
    }

    /**
     * Get message button scripts
     *
     * @return void
     */
    public function enqueueScripts()
    {
        Assets::style('chatbox', 'css/chatbox.min.css', []);
        Assets::script('chatbox', 'js/chatbox.min.js', [], [], true);
    }

    public function renderPreviewInAdmin()
    {
        if (isset($_GET['page']) && $_GET['page'] == 'wp-sms-settings' && $_GET['tab'] && $_GET['tab'] == 'message_button') {
            add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
            add_action('admin_footer', [$this, 'renderChatBox']);
        }
    }

    /**
     * Get message button options
     *
     * @return array
     */
    private function getOptions()
    {
        $options = [
            'title'              => Option::getOption('chatbox_title'),
            'chatbox_animation'  => Option::getOption('chatbox_animation_effect'),
            'chatbox_color'      => Option::getOption('chatbox_color'),
            'chatbox_text_color' => Option::getOption('chatbox_text_color'),
            'button_text'        => Option::getOption('chatbox_button_text'),
            'button_position'    => Option::getOption('chatbox_button_position'),
            'team_members'       => Option::getOption('chatbox_team_members'),
            'links_enabled'      => Option::getOption('chatbox_links_enabled'),
            'links_title'        => Option::getOption('chatbox_links_title'),
            'links'              => Option::getOption('chatbox_links'),
            'footer_text'        => Option::getOption('chatbox_footer_text'),
            'footer_text_color'  => Option::getOption('chatbox_footer_text_color'),
            'footer_link_title'  => Option::getOption('chatbox_footer_link_title'),
            'footer_link_url'    => Option::getOption('chatbox_footer_link_url')
        ];

        return $options;
    }

    /**
     * Render chatbox in the footer
     *
     * @return void
     */
    public function renderChatBox()
    {
        $options = $this->getOptions();
        $chatbox = new ChatBox($options);
        $chatbox->render();
    }
}