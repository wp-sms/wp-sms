<?php

namespace WPSmsTwoWay\Services\Gateway;

use WPSmsTwoWay\Services\Webhook\Webhook;
use WPSmsTwoWay\Services\Webhook\Exceptions as WebhookExceptions;
use WPSmsTwoWay\Models\Command;
use WPSmsTwoWay\Models\IncomingMessage;
use WPSmsTwoWay\Services\RestApi\Exceptions\SendRestResponse;
use WPSmsTwoWay\Services\Logger\WebhookRequestLogger;
use WPSmsTwoWay\Services\Logger\ExceptionLogger;

class GatewayManager
{
    public const GATEWAYS = [
        'twilio' => Gateways\Twilio::class,
        'plivo'  => Gateways\Plivo::class,
        'nexmo'  => Gateways\Nexmo::class,
    ];

    /**
     * Current active gateway
     *
     * @var object
     */
    private $currentGateway;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setCurrentGateway();
    }

    /**
     * Set current gateway
     *
     * @return void
     */
    private function setCurrentGateway()
    {
        global $sms;

        $activeGateway = $sms->options['gateway_name'] ?? null;

        if (array_key_exists($activeGateway, self::GATEWAYS)) {
            $gatewayClass = self::GATEWAYS[$activeGateway];
            $this->currentGateway              = new $gatewayClass;
            $this->currentGateway->isSupported = true;
        } else {
            $this->currentGateway = new \StdClass;
            $this->currentGateway->isSupported = false;
        }
        $this->currentGateway->name = $activeGateway;
    }

    /**
     * Check if current gateway is supported by the plugin
     *
     * @return void
     */
    public function getCurrentGateway()
    {
        return $this->currentGateway;
    }


    /**
     * Init the two-way functionality
     *
     * @return void
     */
    public function init()
    {
        if ($this->currentGateway->isSupported) {
            self::listenToIncomingMessages();
        } else {
            WPSmsTwoWay()->getPlugin()->logger()->error("{$this->currentGateway->name} is not supported by two-way-sms");
        }
    }

    /**
     * Listen to incoming messages
     *
     * @return void
     */
    public function listenToIncomingMessages()
    {
        $plugin  = WPSmsTwoWay()->getPlugin();
        $webhook = $plugin->get(Webhook::class);
        $gateway = $this->currentGateway;
        $logger  = $plugin->get(WebhookRequestLogger::class);

        $webhook->register(function (\WP_REST_Request $request) use ($plugin, $gateway, $webhook, $logger) {
            try {
                $webhook->checkToken($request);
                $message = $gateway->createMessageModelEntry($request);
                $message->fireAction();
                $message->save();
                $logger->withName('valid_message_request')->addEntry('Message received successfully', $request);
                throw new SendRestResponse([
                    'message'        => 'Message was received successfully',
                    'action_status'  => $message->action_status
                ], 200);
            } catch (WebhookExceptions\GatewayNotValid $exception) {
                $logger->withName('invalid_message_request')->addEntry('Gateway Not Valid', $request);
                throw new SendRestResponse(['message' => 'You are not authorized to call this webhook'], 403);
            } catch (WebhookExceptions\TokenMismatch $exception) {
                $logger->withName('invalid_message_request')->addEntry('Token Mismatch', $request);
                throw new SendRestResponse(['message' => 'You are not authorized to call this webhook'], 403);
            } catch (SendRestResponse $exception) {
                throw $exception;
            } catch (\Throwable $exception) {
                $plugin->get(ExceptionLogger::class)->error($exception);
                throw new SendRestResponse(['message' => 'Something went wrong!'], 500);
            }
        });
    }
}
