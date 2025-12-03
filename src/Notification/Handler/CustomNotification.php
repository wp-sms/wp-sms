<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

if (!defined('ABSPATH')) exit;

class CustomNotification extends Notification
{
    public function registerVariables($variable)
    {
        $this->variables = $variable;

        return $this;
    }
}