<?php

namespace WP_SMS\Notification\Handler\BuddyPress;

use WP_SMS\Notification\Notification;

if (!defined('ABSPATH')) exit;

class BuddyPressUserCommentsNotification extends Notification
{

    protected $activity;

    protected $comment;

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
     */
    public function __construct($activity = false, $commentId = false)
    {
        if ($activity) {
            $this->activity = $activity;
        }

        if ($commentId) {
            $this->comment = new \BP_Activity_Activity($commentId);
        }
    }

    /**
     * Get the display name of the user who posted the comment.
     *
     * @return string|null
     */
    public function getPostedUserDisplayName()
    {
        if (!$this->comment) {
            return null;
        }

        $userPosted = get_userdata($this->comment->user_id);
        return ($userPosted && isset($userPosted->display_name)) ? $userPosted->display_name : null;
    }

    /**
     * Get the content of the comment.
     *
     * @return string|null
     */
    public function getComment()
    {
        if (!$this->comment) {
            return null;
        }

        return $this->comment->content ?? null;
    }

    /**
     * Get the display name of the user who received the comment.
     *
     * @return string|null
     */
    public function getReceiverUserDisplayName()
    {
        if (!$this->activity) {
            return null;
        }

        $userReceiver = get_userdata($this->activity->user_id);
        return ($userReceiver && isset($userReceiver->display_name)) ? $userReceiver->display_name : null;
    }
}