<?php

namespace WPSmsTwoWay\Services\Action\Actions\WPSMS;

use WPSmsTwoWay\Services\Action\Actions\AbstractClassWrapper;
use WPSmsTwoWay\Services\Action\Exceptions\ActionException;

use WPSmsTwoWay\Models\IncomingMessage;
use WP_SMS\Newsletter;

class Wrapper extends AbstractClassWrapper
{
    public const NAME        = 'wpsms';
    public const DESCRIPTION = 'WPSMS Actions';

    /**
     * @inheritDoc
     */
    public static function checkRequirements():bool
    {
        if (function_exists('WPSms')) {
            return true;
        }
        return false;
    }
}
