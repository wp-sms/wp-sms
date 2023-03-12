<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

class WordPressCommentNotification extends Notification
{
    protected $comment;

    protected $variables = [
        '%comment_id%'           => 'getCommentId',
        '%comment_author%'       => 'getAuthor',
        '%comment_author_email%' => 'getAuthorEmail',
        '%comment_author_url%'   => 'getAuthorUrl',
        '%comment_author_IP'     => 'getAuthorIp',
        '%comment_date%'         => 'getDate',
        '%comment_content%'      => 'getContent',
        '%comment_url%'          => 'getUrl',
        '%comment_post_title%'   => 'getPostTitle',
        '%comment_post_url%'     => 'getPostUrl',
        '%comment_post_id%'      => 'getPostId',
        ];

    public function __construct($commentId = false)
    {
        if ($commentId) {
            $this->comment = get_comment($commentId);
        }
    }

    public function getCommentId()
    {
        return $this->comment->comment_ID;
    }

    public function getAuthor()
    {
        return $this->comment->comment_author;
    }

    public function getAuthorEmail()
    {
        return $this->comment->comment_author_email;
    }

    public function getAuthorUrl()
    {
        return wp_sms_shorturl($this->comment->comment_author_url);
    }

    public function getAuthorIp()
    {
        return $this->comment->comment_author_IP;
    }

    public function getDate()
    {
        return $this->comment->comment_date;
    }

    public function getContent()
    {
        return $this->comment->comment_content;
    }

    public function getUrl()
    {
        return get_comment_link($this->comment->comment_ID);
    }

    public function getPostTitle()
    {
        return get_the_title($this->comment->comment_post_ID);
    }

    public function getPostUrl()
    {
        return wp_sms_shorturl($this->comment->comment_post_ID);
    }

    public function getPostId()
    {
        return $this->comment->comment_post_ID;
    }
}