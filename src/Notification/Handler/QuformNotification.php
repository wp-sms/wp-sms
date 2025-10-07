<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

class QuformNotification extends Notification
{
    /**
     * Stores the raw QuForm submission data.
     *
     * @var array
     */
    protected $qfData;

    /**
     * Template variables and their corresponding getter methods.
     *
     * @var array
     */
    protected $variables = [
        '%post_title%' => 'getPostTitle',
        '%ip%'         => 'getIp',
        '%source_url%' => 'getSourceUrl',
        '%user_agent%' => 'getUserAgent'
    ];

    /**
     * QuformNotification constructor.
     *
     * @param array $qfData
     */
    public function __construct($qfData)
    {
        $this->qfData = $qfData;

        foreach ($qfData as $key => $value) {
            $placeholder = "%$key%";
            if (!isset($this->variables[$placeholder])) {
                $this->variables[$placeholder] = is_array($value) ? implode(', ', $value) : $value;
            }
        }
    }

    /**
     * Get the form's post title.
     *
     * @return string|null
     */
    public function getPostTitle()
    {
        return $this->qfData['post_title'] ?? null;
    }

    /**
     * Get the submitter's IP address.
     *
     * @return string|null
     */
    public function getIp()
    {
        return $this->qfData['ip'] ?? null;
    }

    /**
     * Get the form submission source URL.
     *
     * @return string|null
     */
    public function getSourceUrl()
    {
        return $this->qfData['source_url'] ?? null;
    }

    /**
     * Get the submitter's browser user agent string.
     *
     * @return string|null
     */
    public function getUserAgent()
    {
        return $this->qfData['user_agent'] ?? null;
    }
}