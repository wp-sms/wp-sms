<?php

namespace WPSmsTwoWay\Services\Gateway\Gateways;

use WPSmsTwoWay\Services\Gateway\GatewayManager;
use WPSmsTwoWay\Services\Webhook\Exceptions\GatewayNotValid;
use WPSmsTwoWay\Models\IncomingMessage;

abstract class AbstractGateway
{
    /**
     * @var string
     * @example api, panel or filter
     */
    protected $registerType;

    /**
     * Gateway's last time of registration
     *
     * @var string|null null means not registered yet
     */
    protected $lastRegistrationDate;

    /**
     * If the register type is panel, then we have to put the gateway panel/console URL to refer user there to add our webhook url on manually.
     *
     * @var string
     */
    protected $panelUrl;

    /**
     * Initiate the gateway
     *
     * @return WPSmsTwoWay\Models\IncomingMessage
     */
    final public static function createMessageModelEntry(\WP_REST_Request $request)
    {
        if (static::validateGateway($request)) {
            $text         = static::extractMessageText($request) ?? '';
            $senderNumber = static::extractSenderNumber($request) ?? '';
            $gateway      = array_search(static::class, GatewayManager::GATEWAYS);
            return IncomingMessage::newMessage($gateway, $text, $senderNumber);
        }
        throw new GatewayNotValid();
    }

    /**
     * Check if the webhook is called by the gateway or not
     *
     * @param \WP_REST_Request $request
     *
     * @return bool
     */
    abstract protected static function validateGateway($request);

    /**
     * Extract message body text from incoming message request
     *
     * @param \WP_REST_Request $request
     *
     * @return string|false message's text on success, false on failure
     */
    abstract protected static function extractMessageText($request);

    /**
     * Extract sender number from incoming message request
     *
     * @param \WP_RES_Request $request
     * @return string|false message's sender number on success, false on failure
     */
    abstract protected static function extractSenderNumber($request);

    /**
     * Get the register type of the gateway
     *
     * @return string
     */
    public function getRegisterType()
    {
        return $this->registerType;
    }

    /**
     * Get gateway's webhook registration panel url
     *
     * @return string
     */
    public function getPanelUrl()
    {
        return $this->panelUrl;
    }
}
