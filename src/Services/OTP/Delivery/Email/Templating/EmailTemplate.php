<?php

namespace WP_SMS\Services\OTP\Delivery\Email\Templating;

final class EmailTemplate
{
    /**
     *
     */
    public const TYPE_OTP_CODE = 'otp_code';
    /**
     *
     */
    public const TYPE_MAGIC_LINK = 'magic_link';
    /**
     *
     */
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
    public $default_subject;
    /**
     * @var string
     */
    public $default_body;

    /**
     * @param string $id
     * @param string $label
     * @param array $placeholders
     * @param string $default_subject
     * @param string $default_body
     */
    public function __construct(
        string $id,
        string $label,
        array  $placeholders,
        string $default_subject,
        string $default_body
    )
    {
        $this->id              = $id;
        $this->label           = $label;
        $this->placeholders    = $placeholders;
        $this->default_subject = $default_subject;
        $this->default_body    = $default_body;
    }
}
