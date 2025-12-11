<?php

namespace WP_SMS\Notification\Handler\BuddyPress;

use WP_SMS\Notification\Notification;

if (!defined('ABSPATH')) exit;

class BuddyPressPrivateMessageNotification extends Notification
{
    /**
     * BuddyPress private message data array.
     *
     * @var array
     */
    protected $bpData;

    /**
     * Template variables and their corresponding getter methods.
     *
     * @var array
     */
    protected $variables = [
        '%sender_display_name%' => 'getSenderDisplayName',
        '%subject%'             => 'getSubject',
        '%message%'             => 'getMessage',
        '%message_url%'         => 'getMessageUrl'
    ];

    /**
     * BuddyPressPrivateMessageNotification constructor.
     *
     * @param array $bpData BuddyPress private message data.
     */
    public function __construct($bpData)
    {
        $this->bpData = $bpData;
    }

    /**
     * Get the display name of the user who sent the private message.
     *
     * @return string|null
     */
    public function getSenderDisplayName()
    {
        return $this->bpData['sender_display_name'] ?? null;
    }

    /**
     * Get the subject of the private message.
     *
     * @return string|null
     */
    public function getSubject()
    {
        return $this->bpData['subject'] ?? null;
    }

    /**
     * Get the content/body of the private message.
     *
     * @return string|null
     */
    public function getMessage()
    {
        return $this->bpData['message'] ?? null;
    }

    /**
     * Get the URL to view the private message.
     *
     * @return string|null
     */
    public function getMessageUrl()
    {
        return $this->bpData['message_url'] ?? null;
    }
}