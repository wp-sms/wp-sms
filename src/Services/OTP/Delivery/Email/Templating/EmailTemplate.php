<?php

namespace WP_SMS\Services\OTP\Delivery\Email\Templating;

final class EmailTemplate
{
    public const TYPE_OTP_CODE       = 'otp_code';
    public const TYPE_MAGIC_LINK     = 'magic_link';
    public const TYPE_PASSWORD_RESET = 'password_reset';

    /**
     * @var string
     */
    public $id;
    /**
     * @var string
     */
    public $label;
    /**
     * @var array
     */
    public $placeholders;
    /**
     * @var string
     */
    public $defaultSubject;

    /**
     * @var string
     */
    public $defaultBody;

    /**
     * @param string $id
     * @param string $label
     * @param array $placeholders
     * @param string $defaultSubject
     * @param string $defaultBody
     */
    public function __construct(
        string $id,
        string $label,
        array  $placeholders,
        string $defaultSubject,
        string $defaultBody
    )
    {
        $this->id             = $id;
        $this->label          = $label;
        $this->placeholders   = $placeholders;
        $this->defaultSubject = $defaultSubject;
        $this->defaultBody    = $defaultBody;
    }
}
