<?php

namespace WP_SMS\Exceptions;

use Exception;

class SystemErrorException extends Exception
{
    public function __construct($message, $code = 0, Exception $previous = null)
    {
        $message = sprintf(__('System error: %s', 'wp-sms'), $message);
        parent::__construct($message, $code, $previous);
    }
}