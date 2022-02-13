<?php

namespace WPSmsTwoWay\Services\Gateway\Gateways;

class Plivo extends AbstractGateway
{
    protected $registerType = 'panel';

    /**
     * Check if the incoming request is from plivo
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    protected static function validateGateway($request)
    {
        $referer = wp_parse_url($request->get_header('referer'))['host'] ?? null;
        return $referer == 'plivo.com';
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
        return $request->get_param('Text');
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
