<?php

namespace WSms\Enums;

enum ChannelUsage: string
{
    case Login = 'login';
    case Mfa = 'mfa';
}
