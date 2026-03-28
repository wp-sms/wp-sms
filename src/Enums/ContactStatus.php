<?php

namespace WSms\Enums;

defined('ABSPATH') || exit;

enum ContactStatus: string
{
    case Subscribed = 'subscribed';
    case Bounced = 'bounced';
    case Complained = 'complained';
}
