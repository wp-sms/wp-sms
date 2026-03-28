<?php

namespace WSms\Messaging\Catalog;

defined('ABSPATH') || exit;

enum VariableStyle: string
{
    /** Positional variables: {{1}}, {{2}} — Twilio, Tencent, Kavenegar (token, token2, token3) */
    case Positional = 'positional';

    /** Named variables: {{otp_code}}, ##name## — MSG91, Alibaba */
    case Named = 'named';
}
