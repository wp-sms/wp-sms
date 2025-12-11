<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

if (!defined('ABSPATH')) exit;

class EasyDigitalDownloadsNotification extends Notification
{
    /**
     * The Easy Digital Downloads data.
     *
     * @var array
     */
    protected $eddData;

    /**
     * Template variables and their corresponding getter methods.
     *
     * @var array
     */
    protected $variables = [
        '%edd_email%' => 'getEddEmail',
        '%edd_first%' => 'getEddFirst',
        '%edd_last%'  => 'getEddLast',
    ];

    /**
     * EasyDigitalDownloadsNotification constructor.
     *
     * @param array $eddData
     */
    public function __construct($eddData)
    {
        $this->eddData = $eddData;
    }

    /**
     * Get the EDD customer's email address.
     *
     * @return string|null
     */
    public function getEddEmail()
    {
        return $this->eddData['edd_email'] ?? null;
    }

    /**
     * Get the EDD customer's first name.
     *
     * @return string|null
     */
    public function getEddFirst()
    {
        return $this->eddData['edd_first'] ?? null;
    }

    /**
     * Get the EDD customer's last name.
     *
     * @return string|null
     */
    public function getEddLast()
    {
        return $this->eddData['edd_last'] ?? null;
    }
}