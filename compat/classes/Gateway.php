<?php

namespace WP_SMS;

// @deprecated Legacy shim. Old gateway classes in add-ons extend this.

class Gateway
{
    public $from;
    public $to = [];
    public $msg;
    public $isFlash = false;
    public $mediaUrls = [];
    public $validateNumber = '';
    public $hasKey = false;
    public $help = false;
    public $bulk_send = true;
    public $supportMedia = false;
    public $supportIncoming = false;

    public function sendSMS()
    {
        return false;
    }

    public function getCredit()
    {
        return false;
    }

    public function isConnected()
    {
        return false;
    }

    public static function initial()
    {
        return null;
    }

    public function __call($name, $arguments)
    {
        return null;
    }

    public static function __callStatic($name, $arguments)
    {
        return null;
    }
}
