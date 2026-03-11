<?php

namespace WSms\Enums;

enum ChannelStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Disabled = 'disabled';
}
