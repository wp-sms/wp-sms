<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

class WordPressPostNotification extends Notification
{
    protected $post;

    protected $variables = [
        '%post_title%' => 'getPostTitle',
    ];

    public function __construct($postId = false)
    {
        if ($postId) {
            $this->post = get_post($postId);
        }
    }

    public function getPostTitle()
    {
        return $this->post->post_title;
    }
}