<?php

namespace WP_SMS\Exceptions;

use Exception;

if (!defined('ABSPATH')) exit;

class SystemErrorException extends Exception
{
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        /* translators: %s: error message */
        $message = sprintf(__('System error: %s', 'wp-sms'), $message);
        parent::__construct($message, $code, $previous);
    }
}