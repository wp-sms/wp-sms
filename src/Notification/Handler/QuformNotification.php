<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

if (!defined('ABSPATH')) exit;

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
        '%post_title%'    => 'getPostTitle',
        '%form_url%'      => 'getFormUrl',
        '%referring_url%' => 'getReferringUrl',
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
     * Get the URL of the submitted form.
     *
     * @return string|null The form URL if available, null otherwise
     */
    public function getFormUrl()
    {
        return $this->qfData['form_url'] ?? null;
    }

    /**
     * Get the referring URL from which the form was submitted.
     *
     * @return string|null The referring URL if available, null otherwise
     */
    public function getReferringUrl()
    {
        return $this->qfData['referring_url'] ?? null;
    }
}