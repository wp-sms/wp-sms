<?php

namespace WP_SMS\Exceptions;

use Exception;

if (!defined('ABSPATH')) exit;

class LicenseException extends Exception
{
    private $status;

    /** @var array|null Renewal block the licence server sent with a refusal (renew link, offer, price). */
    private $renewal;

    public function __construct($message, $status = '', $code = 0, $previous = null, $renewal = null) {
        parent::__construct($message, $code, $previous);

        $this->status  = $status;
        $this->renewal = $renewal;
    }

    /** @var string Link to the licence server's support page, sent with a refusal. */
    private $supportUrl = '';

    public function setSupportUrl($url)
    {
        $this->supportUrl = is_string($url) ? esc_url_raw($url, ['https', 'mailto']) : '';

        return $this;
    }

    public function getSupportUrl()
    {
        return $this->supportUrl;
    }

    public function getRenewal()
    {
        return $this->renewal;
    }

    public function getStatus()
    {
        return $this->status;
    }
}