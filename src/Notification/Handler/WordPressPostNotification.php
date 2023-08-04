<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

class WordPressPostNotification extends Notification
{
    protected $post;

    protected $variables = [
        '%post_title%'         => 'getTitle',
        '%post_content%'       => 'getContent',
        '%post_url%'           => 'getUrl',
        '%post_date%'          => 'getDate',
        '%post_thumbnail%'     => 'getThumbnail',
        '%post_author%'        => 'getAuthor',
        '%post_author_email%'  => 'getAuthorEmail',
        '%post_status%'        => 'getStatus',
        '%post_password%'      => 'getPassword',
        '%post_comment_count%' => 'getCommentCount',
        '%post_post_type%'     => 'getPostType',
        '%post_id%'            => 'getId',
    ];

    public function __construct($postId = false)
    {
        if ($postId) {
            $this->post = get_post($postId);
        }
    }

    public function getTitle()
    {
        return $this->post->post_title;
    }

    public function getContent()
    {
        $wordLimit = wp_sms_get_option('notif_publish_new_post_words_count');

        return wp_trim_words($this->post->post_content, $wordLimit ? $wordLimit : 10, '...');
    }

    public function getUrl()
    {
        return wp_sms_shorturl(wp_get_shortlink($this->post->ID));
    }

    public function getDate()
    {
        return $this->post->post_date;
    }

    public function getThumbnail()
    {
        return get_the_post_thumbnail_url($this->post->ID);
    }

    public function getAuthor()
    {
        return get_the_author_meta('display_name', $this->post->post_author);
    }

    public function getAuthorEmail()
    {
        return get_the_author_meta('user_email', $this->post->post_author);
    }

    public function getStatus()
    {
        return $this->post->post_status;
    }

    public function getPassword()
    {
        return $this->post->post_password;
    }

    public function getCommentCount()
    {
        return $this->post->comment_count;
    }

    public function getPostType()
    {
        return $this->post->post_type;
    }

    public function getId()
    {
        return $this->post->ID;
    }
}