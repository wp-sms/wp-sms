<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

// Wire format reverse-engineered from LibSmsGateway (https://github.com/alexhauser/LibSmsGateway):
// SmsClient.cs builds the GET request, SmsResult.cs parses an XML body with
// <result>OK|OK:|ERROR:</result> and <errorcode>NNN</errorcode>. Status codes
// from StatusCode.cs.
class SmsgatewayatProvider extends AbstractProvider
{
    public const TESTED = false;

    private const API_URL = 'https://www.sms-gateway.at/sms/sendsms.php';

    public function getId(): string
    {
        return 'smsgatewayat';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'username' => [
                    'type'        => 'string',
                    'label'       => __('Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your sms-gateway.at account username.', 'wp-sms'),
                ],
                'validpass' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('The HTTP2SMS API password generated in your sms-gateway.at account (the provider calls this "validpass").', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'absender' => [
                        'type'        => 'string',
                        'label'       => __('Sender ID', 'wp-sms'),
                        'placeholder' => 'MyBrand',
                        'description' => __('Optional alphanumeric sender name (max 10 chars, no spaces or special characters). Charged ~€0.03/SMS extra by the provider.', 'wp-sms'),
                    ],
                    'flash' => [
                        'type'    => 'boolean',
                        'label'   => __('Flash SMS', 'wp-sms'),
                        'default' => false,
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username  = $this->getSharedConfig('username');
        $validpass = $this->getSharedConfig('validpass');

        if (!$username || !$validpass) {
            return DeliveryResult::failed(__('SMSgateway.at username and API password are required', 'wp-sms'));
        }

        $params = [
            'username'  => $username,
            'validpass' => $validpass,
            'message'   => $message->getBody(),
            'encoding'  => 'utf8',
            'receipt'   => '1',
        ];

        $sender = $this->getChannelConfig('sms', 'absender');
        if ($sender) {
            $params['absender'] = $sender;
        }

        if ($this->getChannelConfig('sms', 'flash')) {
            $params['flash'] = '1';
        }

        // http_build_query() turns 'number[]' into 'number[0]=...' which the
        // PHP endpoint may reject; the SDK uses bare 'number[]'. Append manually.
        $url = self::API_URL . '?' . http_build_query($params)
             . '&number%5B%5D=' . rawurlencode($message->getRecipient());

        $result = $this->httpGet($url);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(sprintf('HTTP %d', $result['code']));
        }

        return $this->parseResponse(trim($result['body']));
    }

    private function parseResponse(string $body): DeliveryResult
    {
        $previousErrors = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($body);
        libxml_use_internal_errors($previousErrors);

        if ($xml !== false && isset($xml->result)) {
            $resultValue = trim((string) $xml->result);
            if ($resultValue === 'OK' || $resultValue === 'OK:') {
                return DeliveryResult::sent();
            }
            if ($resultValue === 'ERROR:') {
                $code = trim((string) ($xml->errorcode ?? ''));
                return DeliveryResult::failed($this->mapError($code !== '' ? $code : '999'));
            }
        }

        return DeliveryResult::failed(sprintf(
            __('Unexpected response from SMSgateway.at: %s', 'wp-sms'),
            $body,
        ));
    }

    // Maps numeric codes from LibSmsGateway/StatusCode.cs.
    private function mapError(string $code): string
    {
        return match ($code) {
            '100' => __('Message cannot be sent', 'wp-sms'),
            '108' => __('Wrong username', 'wp-sms'),
            '109' => __('Wrong password', 'wp-sms'),
            '110' => __('Invalid sender — no source number set or sender ID rejected', 'wp-sms'),
            '111' => __('Unsupported destination number', 'wp-sms'),
            '113' => __('Message is empty', 'wp-sms'),
            '114' => __('Message length invalid', 'wp-sms'),
            '116' => __('Insufficient credit', 'wp-sms'),
            '200' => __('Unsupported destination address', 'wp-sms'),
            '999' => __('Unknown error', 'wp-sms'),
            default => sprintf(__('SMSgateway.at error %s', 'wp-sms'), $code),
        };
    }
}
