<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

if (!defined('ABSPATH')) exit;

class ContactForm7Notification extends Notification
{
    /**
     * Data received from Contact Form 7 form submission.
     *
     * @var array
     */
    protected $cf7Data;

    /**
     * Template variables and their corresponding getter methods.
     *
     * @var array
     */
    protected $variables = [
        '%_post_id%'    => 'getPostId',
        '%_post_title%' => 'getPostTitle',
        '%_post_url%'   => 'getPostUrl',
        '%_post_name%'  => 'getPostName',
        '%_site_url%'   => 'getSiteUrl',
        '%_site_title%' => 'getSiteTitle',
    ];

    /**
     * ContactForm7Notification constructor.
     *
     * @param array $cf7Data Data submitted by Contact Form 7
     */
    public function __construct($cf7Data)
    {
        $this->cf7Data = $cf7Data;

        foreach ($cf7Data as $key => $value) {
            $placeholder = "%$key%";
            if (!isset($this->variables[$placeholder])) {
                $this->variables[$placeholder] = is_array($value) ? implode(', ', $value) : $value;
            }
        }
    }

    /**
     * Get the post ID associated with the form submission.
     *
     * @return int|string|null The post ID, or null if not set
     */
    public function getPostId()
    {
        return $this->cf7Data['_post_id'] ?? null;
    }

    /**
     * Get the post title associated with the form submission.
     *
     * @return string|null The post title, or null if not set
     */
    public function getPostTitle()
    {
        return $this->cf7Data['_post_title'] ?? null;
    }

    /**
     * Get the post URL associated with the form submission.
     *
     * @return string|null The post URL, or null if not set
     */
    public function getPostUrl()
    {
        return $this->cf7Data['_post_url'] ?? null;
    }

    /**
     * Get the post slug (name) associated with the form submission.
     *
     * @return string|null The post name, or null if not set
     */
    public function getPostName()
    {
        return $this->cf7Data['_post_name'] ?? null;
    }

    /**
     * Get the site URL.
     *
     * @return string|null The site URL, or null if not set
     */
    public function getSiteUrl()
    {
        return $this->cf7Data['_site_url'] ?? null;
    }

    /**
     * Get the site title.
     *
     * @return string|null The site title, or null if not set
     */
    public function getSiteTitle()
    {
        return $this->cf7Data['_site_title'] ?? null;
    }
}