<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

class BuddyPressUserCommentsNotification extends Notification
{
    /**
     * BuddyPress user comment data array.
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
        '%comment%'                    => 'getComment',
        '%receiver_user_display_name%' => 'getReceiverUserDisplayName',
    ];

    /**
     * BuddyPressUserCommentsNotification constructor.
     *
     * @param array $bpData BuddyPress user comment data.
     */
    public function __construct($bpData)
    {
        $this->bpData = $bpData;
    }

    /**
     * Get the display name of the user who posted the comment.
     *
     * @return string|null
     */
    public function getPostedUserDisplayName()
    {
        return $this->bpData['posted_user_display_name'] ?? null;
    }

    /**
     * Get the content of the comment.
     *
     * @return string|null
     */
    public function getComment()
    {
        return $this->bpData['comment'] ?? null;
    }

    /**
     * Get the display name of the user who received the comment.
     *
     * @return string|null
     */
    public function getReceiverUserDisplayName()
    {
        return $this->bpData['receiver_user_display_name'] ?? null;
    }
}