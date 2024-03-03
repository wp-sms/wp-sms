<?php

namespace WP_SMS\Services\MessageButton;

use WP_SMS\Helper;
use WP_SMS\Option;

class ChatBox
{
    private $options = [];

    public function __construct($options)
    {
        $this->options = $options;
    }

    /**
     * Checks if the chatbox is enabled in the settings
     * 
     * @return bool
     */
    public static function isEnabled()
    {
        return Option::getOption('chatbox_message_button') == true;
    }

    /**
     * Render chatbox template
     * 
     * @return void
     */
    public function render()
    {
        echo Helper::loadTemplate('chatbox.php', $this->options);
    }
}