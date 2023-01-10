<?php

namespace WP_SMS\Notification\Handler;

use WP_SMS\Notification\Notification;

class CustomNotification extends Notification
{
    public function registerVariables($variable)
    {
        $this->variables = $variable;

        return $this;
    }
}