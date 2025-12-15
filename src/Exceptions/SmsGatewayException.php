<?php

namespace WP_SMS\Exceptions;

if (!defined('ABSPATH')) exit;

use Exception;

class SmsGatewayException extends Exception
{
    public static function soapNotAvailable(): self
    {
        return new self(
            __('PHP SOAP extension is not enabled; please ask your hosting provider to enable SOAP in your PHP configuration.', 'wp-sms'),
            2001
        );
    }

    public static function invalidCredentials(): self
    {
        return new self(
            __('Invalid SMS gateway credentials provided.', 'wp-sms'),
            2002
        );
    }

    public static function connectionFailed(string $endpoint): self
    {
        return new self(
            sprintf(
            /* translators: %s: SMS gateway endpoint */
                __('Failed to connect to SMS gateway endpoint: %s.', 'wp-sms'),
                $endpoint
            ),
            2003
        );
    }

    public static function gatewayError(string $message): self
    {
        return new self(
            sprintf(
            /* translators: %s: SMS gateway error */
                __('SMS gateway returned an error: %s.', 'wp-sms'),
                $message
            ),
            2004
        );
    }

    public static function invalidResponse(): self
    {
        return new self(
            __('SMS gateway returned an unexpected or invalid response.', 'wp-sms'),
            2005
        );
    }

    public static function unsupportedFeature(string $feature): self
    {
        return new self(
            sprintf(
            /* translators: %s: SMS gateway feature name */
                __('SMS gateway does not support the feature: %s.', 'wp-sms'),
                $feature
            ),
            2006
        );
    }

    public static function messageSendFailed(): self
    {
        return new self(
            __('Failed to send SMS message due to a gateway error.', 'wp-sms'),
            2007
        );
    }
}
