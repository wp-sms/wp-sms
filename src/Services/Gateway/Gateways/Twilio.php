<?php

namespace WPSmsTwoWay\Services\Gateway\Gateways;

use Twilio\Security\RequestValidator;
use WPSmsTwoWay\Services\Webhook\Webhook;

class Twilio extends AbstractGateway
{
    protected $registerType = 'panel';

    /**
     * Check if the incoming request is from twilio
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    protected static function validateGateway($request)
    {
        $referer = wp_parse_url($request->get_header('referer'))['host'] ?? null;
        return $referer == 'twilio.com';
    }

    /**
     * Extract message body text from incoming message request
     *
     * @param \WP_REST_Request $request
     *
     * @return string|false message's text on success, false on failure
     */
    public static function extractMessageText($request)
    {
        return $request->get_param('Body');
    }

    /**
     * Extract sender number from incoming message request
     *
     * @param \WP_RES_Request $request
     * @return string|false message's sender number on success, false on failure
     */
    public static function extractSenderNumber($request)
    {
        return $request->get_param('From');
    }
}
