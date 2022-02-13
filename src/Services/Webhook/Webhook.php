<?php

namespace WPSmsTwoWay\Services\Webhook;

use WPSmsTwoWay\Services\Option;
use WPSmsTwoWay\Exceptions\SendRestResponse;

class Webhook
{
    const BASE_ROUTE = 'webhook';
    const TOKEN_NAME = 'webhook_token';
    const PARAM_KEY  = 'wpsms_token';

    /**
     * Crypto generated token
     *
     * @var string
     */
    private $token;

    /**
     * Webhook's registered URL
     *
     * @var string
     */
    private $url;

    /**
     * Webhook's callback
     *
     * @var callable
     */
    private $callback;

    /**
     * Reset token URL
     *
     * @var object
     */
    private $resetTokenRoute;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->setToken();
        $this->setUrl();
        $this->registerTokenResetRoute();
    }

    /**
     * Generate a new token
     *
     * @return void
     */
    private function generateToken()
    {
        $token = bin2hex(openssl_random_pseudo_bytes(16));
        return $token;
    }

    /**
     * Set the webhook's token
     *
     * @return void
     */
    private function setToken()
    {
        $savedToken = Option::get(self::TOKEN_NAME);

        if ($savedToken) {
            $this->token = (string)$savedToken;
        } else {
            $token = $this->generateToken();
            Option::add(self::TOKEN_NAME, $token);
            $this->token = $token;
        }
    }

    /**
     * Reset the webhook's token
     *
     * @return void
     */
    public function resetToken()
    {
        $newToken = $this->generateToken();
        Option::update(self::TOKEN_NAME, $newToken);
        $this->token = $newToken;
    }

    /**
     * Get the webhook's token
     *
     * @return string
     */
    public function getToken()
    {
        return $this->token;
    }

    /**
     * Set webhook's URL
     *
     * @return void
     */
    private function setUrl()
    {
        $plugin = WPSmsTwoWay()->getPlugin();
        $this->url = $plugin->get('route')->getUrl(self::BASE_ROUTE);
    }

    /**
     * Get webhook's URL
     *
     * @throws Exception when webhook is not registered
     * @param bool $withToken
     * @return string
     */
    public function getUrl($withToken = true)
    {
        if (! $this->url) {
            throw new \Exception('Webhook is not registered yet, try using the register method');
        }
        return $withToken ? add_query_arg(self::PARAM_KEY, $this->token, $this->url) : $this->url;
    }

    /**
     * Get reset token URL
     *
     * @return void
     */
    public function getResetTokenRoute()
    {
        return $this->resetTokenRoute;
    }

    /**
     * Register the webhook using the given callback
     *
     * Must be called after 'rest_api_init'
     *
     * @param callable $callback
     * @return void
     */
    public function register(callable $callback)
    {
        $plugin = WPSmsTwoWay()->getPlugin();
        $webhook = $this;

        $this->callback = $callback;

        $plugin->get('route')->post(self::BASE_ROUTE, $this->callback);
        $plugin->get('route')->get(self::BASE_ROUTE, $this->callback);
    }

    /**
     * Check incoming request's token
     *
     * @param \WP_REST_Request  $request
     * @return void
     */
    public function checkToken(\WP_REST_Request $request)
    {
        if ($request->get_param(self::PARAM_KEY) == $this->token) {
            return;
        }
        throw new Exceptions\TokenMismatch();
    }

    /**
     * Register reset token REST route
     *
     * @return void
     */
    public function registerTokenResetRoute()
    {
        $plugin = WPSmsTwoWay()->getPlugin();
        $webhook = $this;

        $callback = function () use ($webhook, $plugin) {
            $webhook->resetToken();

            $redirectUrl = $plugin->redirect()
                ->back()
                ->withNotice(__('Webhook Token Has Been Reset Successfully.'), 'success')->getUrl();

            return new \WP_REST_Response(__('Webhook token is regenerated successfully.'), 200);
        };

        $this->resetTokenRoute = $plugin->get('route')->get(self::BASE_ROUTE.'/reset-token', $callback, 'manage_options', 'wpsms-tw-reset-token');
    }
}
