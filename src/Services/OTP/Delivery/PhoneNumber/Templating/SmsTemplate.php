<?php

namespace WP_SMS\Services\OTP\Delivery\PhoneNumber\Templating;

final class SmsTemplate
{
    public const TYPE_OTP_CODE       = 'otp_code';
    public const TYPE_MAGIC_LINK     = 'magic_link';
    public const TYPE_PASSWORD_RESET = 'password_reset';
    public const TYPE_COMBINED_REGISTER = 'combined_register';
    public const TYPE_COMBINED_LOGIN    = 'combined_login';

    /** @var string */
    public $id;
    /** @var string */
    public $label;
    /** @var array */
    public $placeholders;
    /** @var string */
    public $defaultBody;

    public function __construct(
        string $id,
        string $label,
        array  $placeholders,
        string $defaultBody
    )
    {
        $this->id           = $id;
        $this->label        = $label;
        $this->placeholders = $placeholders;
        $this->defaultBody  = $defaultBody;
    }
}
