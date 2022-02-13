<?php

namespace WPSmsTwoWay\Enums;

use Spatie\Enum\Enum;

/**
 * @method static self enabled()
 * @method static self disabled()
 */
class CommandStatus extends Enum
{
    protected static function labels()
    {
        return[
            'enabled'  => __('Enabled', 'wp-sms-two-way'),
            'disabled' => __('Disabled', 'wp-sms-two-way'),
        ];
    }
}
