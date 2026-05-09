<?php

namespace WSms\Messaging\Gateway\Provider;

defined('ABSPATH') || exit;

/**
 * SMSAPI.bg — Bulgaria-only deployment of the LINK Mobility SMSAPI platform.
 * Same Bearer-token API as smsapi.com / smsapi.pl, hardwired to api.smsapi.bg.
 */
final class SmsApiBgProvider extends SmsApiProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    public const HOST = 'https://api.smsapi.bg';

    public function getId(): string
    {
        return 'smsapi-bg';
    }

    protected function getApiHost(): string
    {
        return self::HOST;
    }

    public function getConfigSchema(): array
    {
        $schema = parent::getConfigSchema();
        unset($schema['shared']['region']);

        $schema['shared']['api_token']['description'] = __(
            'Bearer token from the SMSAPI.bg portal (Settings > API tokens). Grant scopes: sms, profile (and contacts for blacklist support).',
            'wp-sms'
        );

        return $schema;
    }
}
