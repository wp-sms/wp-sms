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
        // Render chatbox on the dashboard page for the settings preview
        // Hidden by default via CSS, toggled visible by React's Preview button
        if (isset($_GET['page']) && $_GET['page'] === 'wsms' && !isset($_GET['path'])) {
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