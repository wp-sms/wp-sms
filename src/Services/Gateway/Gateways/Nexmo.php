<?php

namespace WPSmsTwoWay\Services\Gateway\Gateways;

class Nexmo extends AbstractGateway
{
    protected $registerType = 'panel';

    /**
     * Check if the incoming request is from nexmo
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    protected static function validateGateway($request)
    {
        // This is temporary
        return true;
    }

    /**
     * Extract message body text from incoming message request
     *
     * @param \WP_REST_Request $request
     *
     * @return string
     */
    public static function extractMessageText($request)
    {
        return $request->get_param('text');
    }

    /**
     * Extract sender number from incoming message request
     *
     * @param \WP_RES_Request $request
     * @return string
     */
    public static function extractSenderNumber($request)
    {
        return $request->get_param('msisdn');
    }
}
