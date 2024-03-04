<?php

namespace WP_SMS\Services\MessageButton;

use WP_SMS\Helper;

class ChatBox
{
    /**
     * @var ChatBoxDecorator $chatBoxDecorator
     */
    private $chatBoxDecorator;

    public function __construct($chatBoxDecorator)
    {
        $this->chatBoxDecorator = $chatBoxDecorator;
    }

    /**
     * Render chatbox template
     *
     * @return void
     */
    public function render()
    {
        echo Helper::loadTemplate('chatbox.php', [
            'chatbox' => $this->chatBoxDecorator
        ]);
    }
}