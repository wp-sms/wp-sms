<?php

namespace WPSmsTwoWay\Services\RestApi;

use Backyard\Exceptions\MissingConfigurationException;
use WPSmsTwoWay\Services\RestApi\Exceptions\SendRestResponse;

class Route
{
    const NONCE_FIELD_NAME = 'wp-sms-nonce';
    const REST_NAMESPACE   = 'wp-sms-two-way/v1';

    /**
     * Route's address
     *
     * @var string
     */
    protected $route;

    /**
     * Routes method
     *
     * @var string
     */
    protected $method;

    /**
     * Route's nonce handle name
     *
     * @var string
     */
    protected $nonceHandle;

    /**
     * Route's endpoint callback
     *
     * @var string
     */
    protected $endpoint;

    /**
     * Route's permission
     *
     * @see https://wordpress.org/support/article/roles-and-capabilities/
     * @var string
     */
    protected $permission;

    /**
     * Constructor
     *
     * Kept empty for convenience
     */
    public function __construct()
    {
    }

    /**
     * Register a get route
     *
     * @param string $route
     * @param callable $endpoint
     * @param string|null $permission
     * @param string|null $nonceHandle
     * @return void
     */
    public function get(string $route, callable $endpoint, string $permission = null, string $nonceHandle = null)
    {
        $this->method      = 'GET';
        $this->route       = $route;
        $this->endpoint    = $endpoint;
        $this->permission  = $permission;
        $this->nonceHandle = $nonceHandle;

        $this->register();

        return $this;
    }

    /**
     * Register a post route
     *
     * @param string $route
     * @param callable $endpoint
     * @param string|null $permission
     * @param string|null $nonceHandle
     * @return void
     */
    public function post(string $route, callable $endpoint, string $permission = null, string $nonceHandle = null)
    {
        $this->method      = 'POST';
        $this->route       = $route;
        $this->endpoint    = $endpoint;
        $this->permission  = $permission;
        $this->nonceHandle = $nonceHandle;

        $this->register();

        return $this;
    }
    
    /**
     * Get route's url
     *
     * @param string|null $route
     * @return string
     */
    public function getUrl(string $route = null)
    {
        $route = $route ?? $this->route;
        $route = trailingslashit(self::REST_NAMESPACE).$route;

        return get_rest_url(null, $route, 'rest');
    }

    /**
     * Get route's nonce handle
     *
     * @return string
     */
    public function getNonceHandle()
    {
        return $this->nonceHandle;
    }


    /**
     * Register the obj
     *
     * @return void
     */
    protected function register()
    {
        $instance = $this;
        add_action(
            'rest_api_init',
            function () use ($instance) {
                register_rest_route(
                    $instance::REST_NAMESPACE,
                    $instance->route,
                    [
                        'methods'             => $instance->method,
                        'callback'            => [$instance, 'mainCallback'],
                        'permission_callback' => [$instance , 'permissionCallback'],
                    ]
                );
            },
            10,
            0
        );
    }

    /**
     * Check user permission
     *
     * @return WP_Error|true
     */
    public function permissionCallback()
    {
        if (isset($this->permission) && !current_user_can($this->permission)) {
            return new \WP_Error('401', 'Not Authorized', array( 'status' => 401 ));
        }
        if (isset($this->nonceHandle) && !check_ajax_referer($this->nonceHandle, self::NONCE_FIELD_NAME)) {
            return new \WP_Error('403', 'Not Authorized', array( 'status' => 403 ));
        }
        return true;
    }

    public function mainCallback(\WP_REST_Request $request)
    {
        try {
            return call_user_func($this->endpoint, $request);
        } catch (SendRestResponse $exception) {
            return new \WP_REST_Response($exception->getData(), $exception->getCode());
        }
    }
}
