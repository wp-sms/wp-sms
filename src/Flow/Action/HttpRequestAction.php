<?php

namespace WSms\Flow\Action;

use WSms\Flow\Contracts\ActionInterface;
use WSms\Flow\Contracts\ActionResult;

defined('ABSPATH') || exit;

class HttpRequestAction implements ActionInterface
{
    public function getId(): string
    {
        return 'http_request';
    }

    public function getName(): string
    {
        return __('HTTP Request', 'wp-sms');
    }

    public function getGroup(): string
    {
        return 'Utilities';
    }

    public function getConfigSchema(): array
    {
        return [
            'url'     => ['type' => 'string', 'label' => __('URL', 'wp-sms'), 'template' => true, 'required' => true],
            'method'  => ['type' => 'string', 'label' => __('Method', 'wp-sms'), 'enum' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'], 'default' => 'POST'],
            'headers' => ['type' => 'object', 'label' => __('Headers', 'wp-sms')],
            'body'    => ['type' => 'text', 'label' => __('Body', 'wp-sms'), 'template' => true],
        ];
    }

    public function execute(array $payload, array $config): ActionResult
    {
        $url = $config['url'] ?? '';
        $method = strtoupper($config['method'] ?? 'POST');
        $headers = $config['headers'] ?? ['Content-Type' => 'application/json'];
        $body = $config['body'] ?? '';

        $args = [
            'method'  => $method,
            'headers' => $headers,
            'timeout' => 30,
        ];

        if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = $body;
        }

        $response = wp_remote_post($url, $args);

        if (is_wp_error($response)) {
            return ActionResult::failure($response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $responseBody = wp_remote_retrieve_body($response);

        return ActionResult::success([
            'http_status' => $code,
            'body'        => $responseBody,
        ]);
    }
}
