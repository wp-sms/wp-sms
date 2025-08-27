<?php

namespace WP_SMS\Services\Email;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Value object for email send results.
 */
class EmailResult
{
    /** @var bool */
    public $success = false;
    /** @var string|null */
    public $error = null;
    /** @var array */
    public $context = array();

    /**
     * @param bool $success
     * @param string|null $error
     * @param array $context
     */
    public function __construct($success, $error = null, $context = array())
    {
        $this->success = (bool)$success;
        $this->error   = $error;
        $this->context = is_array($context) ? $context : array();
    }
}
