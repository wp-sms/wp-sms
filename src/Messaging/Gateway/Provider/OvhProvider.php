<?php

namespace WSms\Messaging\Gateway\Provider;

use WSms\Messaging\Contracts\DeliveryResult;
use WSms\Messaging\Contracts\MessageInterface;
use WSms\Messaging\Gateway\AbstractProvider;

defined('ABSPATH') || exit;

class OvhProvider extends AbstractProvider
{
    private const API_BASE = 'https://eu.api.ovh.com/1.0';

    public function getId(): string
    {
        return 'ovh';
    }

    public function getName(): string
    {
        return 'OVH';
    }

    public function getSupportedChannels(): array
    {
        return ['sms'];
    }

    public function getConfigSchema(): array
    {
        return [
            'shared' => [
                'application_key' => [
                    'type'        => 'string',
                    'label'       => __('Application Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generated when creating an OVH API application at api.ovh.com', 'wp-sms'),
                    'placeholder' => 'AbCdEf123456',
                ],
                'application_secret' => [
                    'type'        => 'secret',
                    'label'       => __('Application Secret', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Shown once when you create the API application — save it securely', 'wp-sms'),
                    'placeholder' => 'Your application secret',
                ],
                'consumer_key' => [
                    'type'        => 'secret',
                    'label'       => __('Consumer Key', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Generated when you authorize the application with your account', 'wp-sms'),
                    'placeholder' => 'Your consumer key',
                ],
                'service_name' => [
                    'type'        => 'string',
                    'label'       => __('Service Name', 'wp-sms'),
                    'required'    => true,
                    'description' => __('Your OVH SMS service identifier, found in Control Panel > Telecom > SMS', 'wp-sms'),
                    'placeholder' => 'sms-xx12345-1',
                ],
            ],
            'channels' => [
                'sms' => [
                    'sender' => [
                        'type'        => 'string',
                        'label'       => __('Sender', 'wp-sms'),
                        'required'    => true,
                        'description' => __('An approved sender name or number from your OVH SMS panel', 'wp-sms'),
                        'placeholder' => '+33612345678',
                    ],
                ],
            ],
        ];
    }

    public function getMetadata(): array
    {
        return [
            'description' => __('European cloud provider with SMS services', 'wp-sms'),
            'website'     => 'https://www.ovh.com/sms/',
            'icon'        => '',
            'regions'     => ['EU', 'FR'],
            'setup_url'   => 'https://api.ovh.com/createToken/',
            'setup_notes' => [
                __('Create API credentials at api.ovh.com/createToken with GET, POST, PUT rights on /sms/*.', 'wp-sms'),
                __('Find your Service Name in the OVH Control Panel under Telecom > SMS.', 'wp-sms'),
                __('Register a sender name or number in the OVH SMS panel before use.', 'wp-sms'),
            ],
        ];
    }

    protected function doSend(MessageInterface $message): DeliveryResult
    {
        $appKey = $this->getSharedConfig('application_key');
        $appSecret = $this->getSharedConfig('application_secret');
        $consumerKey = $this->getSharedConfig('consumer_key');
        $serviceName = $this->getSharedConfig('service_name');
        $sender = $this->getChannelConfig('sms', 'sender');

        if (!$appKey || !$appSecret || !$consumerKey || !$serviceName || !$sender) {
            return DeliveryResult::failed(__('OVH credentials not configured', 'wp-sms'));
        }

        $url = self::API_BASE . "/sms/{$serviceName}/jobs";
        $body = wp_json_encode([
            'message'    => $message->getBody(),
            'receivers'  => [$message->getRecipient()],
            'sender'     => $sender,
            'charset'    => 'UTF-8',
            'coding'     => '7bit',
            'noStopClause' => true,
        ]);

        $timestamp = time();
        $signature = '$1$' . sha1($appSecret . '+' . $consumerKey . '+POST+' . $url . '+' . $body . '+' . $timestamp);

        $result = $this->httpPost($url, [
            'headers' => [
                'Content-Type'          => 'application/json',
                'X-Ovh-Application'     => $appKey,
                'X-Ovh-Consumer'        => $consumerKey,
                'X-Ovh-Timestamp'       => (string) $timestamp,
                'X-Ovh-Signature'       => $signature,
            ],
            'body' => $body,
        ]);

        if ($result instanceof DeliveryResult) {
            return $result;
        }

        $data = json_decode($result['body'], true);

        if ($result['code'] >= 200 && $result['code'] < 300) {
            $ids = $data['ids'] ?? [];
            return DeliveryResult::sent(
                providerId: !empty($ids) ? (string) $ids[0] : null,
                cost: isset($data['totalCreditsRemoved']) ? (float) $data['totalCreditsRemoved'] : null,
            );
        }

        return DeliveryResult::failed($data['message'] ?? "HTTP {$result['code']}");
    }

    public function getCredit(): ?string
    {
        $appKey = $this->getSharedConfig('application_key');
        $appSecret = $this->getSharedConfig('application_secret');
        $consumerKey = $this->getSharedConfig('consumer_key');
        $serviceName = $this->getSharedConfig('service_name');

        if (!$appKey || !$appSecret || !$consumerKey || !$serviceName) {
            return null;
        }

        $url = self::API_BASE . "/sms/{$serviceName}";
        $timestamp = time();
        $signature = '$1$' . sha1($appSecret . '+' . $consumerKey . '+GET+' . $url . '++' . $timestamp);

        $result = $this->httpGet($url, [
            'headers' => [
                'X-Ovh-Application' => $appKey,
                'X-Ovh-Consumer'    => $consumerKey,
                'X-Ovh-Timestamp'   => (string) $timestamp,
                'X-Ovh-Signature'   => $signature,
            ],
        ]);

        if ($result instanceof DeliveryResult) {
            return null;
        }

        $data = json_decode($result['body'], true);
        return isset($data['creditsLeft']) ? (string) $data['creditsLeft'] : null;
    }
}
