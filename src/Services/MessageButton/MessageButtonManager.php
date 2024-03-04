<?php

namespace WP_SMS\Services\MessageButton;

use WP_SMS\Components\Assets;

class MessageButtonManager
{
    /**
     * @var ChatBoxDecorator $chatBoxDecorator
     */
    private $chatBoxDecorator;

    public function init()
    {
        $this->chatBoxDecorator = new ChatBoxDecorator();

        if ($this->chatBoxDecorator->isEnabled()) {
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
     * Render chatbox in the footer
     *
     * @return void
     */
    public function renderChatBox()
    {
        $chatbox = new ChatBox($this->chatBoxDecorator);
        $chatbox->render();
    }
}