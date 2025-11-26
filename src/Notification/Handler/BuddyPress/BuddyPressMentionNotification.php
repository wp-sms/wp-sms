<?php

namespace WP_SMS\Notification\Handler\BuddyPress;

use WP_SMS\Notification\Notification;

if (!defined('ABSPATH')) exit;

class BuddyPressMentionNotification extends Notification
{
    /**
     * BuddyPress mention data array.
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
        '%posted_user_display_name%'   => 'getPostedUserDisplayName',
        '%primary_link%'               => 'getPrimaryLink',
        '%time%'                       => 'getTime',
        '%message%'                    => 'getMessage',
        '%receiver_user_display_name%' => 'getReceiverUserDisplayName'
    ];

    /**
     * BuddyPressMentionNotification constructor.
     *
     * @param array $bpData BuddyPress mention data.
     */
    public function __construct($bpData)
    {
        $this->bpData = $bpData;
    }

    /**
     * Get the display name of the user who posted the mention.
     *
     * @return string|null
     */
    public function getPostedUserDisplayName()
    {
        return $this->bpData['posted_user_display_name'] ?? null;
    }

    /**
     * Get the primary link associated with the mention.
     *
     * @return string|null
     */
    public function getPrimaryLink()
    {
        return $this->bpData['primary_link'] ?? null;
    }

    /**
     * Get the time of the mention.
     *
     * @return string|null
     */
    public function getTime()
    {
        return $this->bpData['time'] ?? null;
    }

    /**
     * Get the message content of the mention.
     *
     * @return string|null
     */
    public function getMessage()
    {
        return $this->bpData['message'] ?? null;
    }

    /**
     * Get the display name of the user who received the mention.
     *
     * @return string|null
     */
    public function getReceiverUserDisplayName()
    {
        return $this->bpData['receiver_user_display_name'] ?? null;
    }
}