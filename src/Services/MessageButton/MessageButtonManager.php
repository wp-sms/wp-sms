<?php

namespace WP_SMS\Services\MessageButton;

use WP_SMS\Components\Assets;

if (!defined('ABSPATH')) exit;

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
        }

        add_action('admin_init', [$this, 'initAdminPreview']);
    }

    public function initAdminPreview()
    {
        // Old settings page
        if (isset($_GET['page']) && $_GET['page'] == 'wp-sms-settings' && isset($_GET['tab']) && $_GET['tab'] == 'message_button') {
            add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
            add_action('admin_footer', [$this, 'renderChatBox']);
        }

        // New/Unified settings page (React dashboard)
        // Always render chatbox on React pages since React handles client-side routing
        // The chatbox is hidden by default via CSS and toggled visible by React's Preview button
        if (isset($_GET['page']) && (strpos($_GET['page'], 'new-settings') !== false || strpos($_GET['page'], 'unified-admin') !== false)) {
            add_action('admin_enqueue_scripts', [$this, 'enqueueScripts']);
            add_action('admin_footer', [$this, 'renderChatBox']);
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