<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

if (!defined('ABSPATH')) exit;

class GravityFormsNotification extends Notification
{
    /**
     * Holds the Gravity Forms submission data.
     *
     * @var array
     */
    protected $gformData;

    /**
     * Template variables and their corresponding getter methods.
     *
     * @var array
     */
    protected $variables = [
        '%title%'      => 'getTitle',
        '%ip%'         => 'getIp',
        '%source_url%' => 'getSourceUrl',
        '%user_agent%' => 'getUserAgent'
    ];

    /**
     * GravityFormsNotification constructor.
     *
     * @param array $gformData
     */
    public function __construct($gformData)
    {
        $this->gformData = $gformData;

        foreach ($gformData as $key => $value) {
            $placeholder = "%$key%";
            if (!isset($this->variables[$placeholder])) {
                $this->variables[$placeholder] = is_array($value) ? implode(', ', $value) : $value;
            }
        }
    }

    /**
     * Returns the form title.
     *
     * @return string|null
     */
    public function getTitle()
    {
        return $this->gformData['title'] ?? null;
    }

    /**
     * Returns the submitter's IP address.
     *
     * @return string|null
     */
    public function getIp()
    {
        return $this->gformData['ip'] ?? null;
    }

    /**
     * Returns the form source URL (the page where the form was submitted).
     *
     * @return string|null
     */
    public function getSourceUrl()
    {
        return $this->gformData['source_url'] ?? null;
    }

    /**
     * Returns the user's browser user agent string.
     *
     * @return string|null
     */
    public function getUserAgent()
    {
        return $this->gformData['user_agent'] ?? null;
    }
}