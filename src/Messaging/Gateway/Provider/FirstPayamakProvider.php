<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Contracts\TestConnectionResult;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

/**
 * First Payamak — Iranian SMS panel with a hybrid REST/SOAP API.
 *
 * Endpoints:
 *   Send:   GET http://ui.firstpayamak.ir/tools/urlservice/send/
 *           ?username=&password=&from=&to=&message=
 *   Credit: SOAP http://ui.firstpayamak.ir/webservice/v2.asmx?WSDL
 *           GetCredit({ username, password }) -> GetCreditResult (numeric)
 *
 * Auth: account username + password sent on every request (query string for
 * the URL service, SOAP body parameters for the credit endpoint).
 *
 * Out of scope: templates/patterns (not exposed by either endpoint), MMS,
 * status webhooks, inbound webhooks. Credit lookup requires the PHP soap
 * extension; sending uses plain HTTP.
 *
 * NOTE: This implementation is reverse-engineered from the v7 PHP source,
 * NOT from official provider documentation. The gateway's API is reachable
 * only from inside Iran, so the wire-format claims here are unverified —
 * field names and response shapes may need adjustment against live traffic.
 */
class FirstPayamakProvider extends AbstractProvider
{
    /** Flip to true once the gateway clears end-to-end manual verification. */
    public const TESTED = false;

    private const SEND_URL = 'http://ui.firstpayamak.ir/tools/urlservice/send/';
    private const WSDL     = 'http://ui.firstpayamak.ir/webservice/v2.asmx?WSDL';

    public function getId(): string
    {
        return 'firstpayamak';
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
                    'label'       => __('API Username', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your First Payamak panel username', 'wp-sms'),
                ],
                'password' => [
                    'type'        => 'secret',
                    'label'       => __('API Password', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your First Payamak panel password', 'wp-sms'),
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender Number', 'wp-sms'),
                        'required'    => true,
                        'description' => __('Dedicated line purchased from the First Payamak panel', 'wp-sms'),
                    ],
                ],
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        $sender   = (string) $this->getChannelConfig('sms', 'sender', '');

        if ($username === '' || $password === '') {
            return DeliveryResult::failed(__('First Payamak credentials not configured', 'wp-sms'));
        }
        if ($sender === '') {
            return DeliveryResult::failed(__('First Payamak sender not configured', 'wp-sms'));
        }

        $url = self::SEND_URL . '?' . http_build_query([
            'username' => $username,
            'password' => $password,
            'from'     => $sender,
            'to'       => $message->getRecipient(),
            'message'  => $message->getBody(),
        ]);

        $result = $this->httpGet($url);

        return $this->parseResponse($result);
    }

    private function parseResponse(array|DeliveryResult $result): DeliveryResult
    {
        if ($result instanceof DeliveryResult) {
            return $result;
        }

        if ($result['code'] < 200 || $result['code'] >= 300) {
            return DeliveryResult::failed(
                sprintf(__('First Payamak send failed (HTTP %d)', 'wp-sms'), $result['code']),
            );
        }

        $body = trim((string) $result['body']);
        if ($body === '') {
            return DeliveryResult::failed(__('Empty response from First Payamak', 'wp-sms'));
        }

        // Numeric responses indicate a message id; everything else is treated as an error string.
        if (is_numeric($body)) {
            return DeliveryResult::sent($body);
        }

        return DeliveryResult::failed($body);
    }

    public function getCredit(): ?string
    {
        if (!class_exists('SoapClient')) {
            return null;
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');
        if ($username === '' || $password === '') {
            return null;
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client   = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->GetCredit(['username' => $username, 'password' => $password]);
        } catch (\Throwable $ex) {
            return null;
        }

        $credit = $response->GetCreditResult ?? null;
        return $credit !== null ? (string) $credit : null;
    }

    public function testConnection(): TestConnectionResult
    {
        if (!class_exists('SoapClient')) {
            return TestConnectionResult::error(__('PHP SOAP extension is required for the First Payamak gateway', 'wp-sms'));
        }

        $username = (string) $this->getSharedConfig('username', '');
        $password = (string) $this->getSharedConfig('password', '');

        if ($username === '' || $password === '') {
            return TestConnectionResult::error(__('API Username and Password are required', 'wp-sms'));
        }

        try {
            @ini_set('soap.wsdl_cache_enabled', '0');
            $client   = new \SoapClient(self::WSDL, ['exceptions' => true, 'connection_timeout' => 30]);
            $response = $client->GetCredit(['username' => $username, 'password' => $password]);
        } catch (\SoapFault $ex) {
            return TestConnectionResult::error($ex->getMessage());
        } catch (\Throwable $ex) {
            return TestConnectionResult::error($ex->getMessage());
        }

        $credit = $response->GetCreditResult ?? null;
        if ($credit === null) {
            return TestConnectionResult::error(__('Unknown error', 'wp-sms'));
        }

        $credit = (string) $credit;
        return TestConnectionResult::ok(
            sprintf(__('Connected — Credit: %s', 'wp-sms'), $credit),
            ['credit' => $credit],
        );
    }
}
