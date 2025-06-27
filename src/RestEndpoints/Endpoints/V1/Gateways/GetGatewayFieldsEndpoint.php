<?php

namespace WP_SMS\RestEndpoints\Endpoints\V1\Gateways;

use WP_SMS\Gateway;
use WP_SMS\RestEndpoints\Abstracts\AbstractSettingsEndpoint;
use WP_REST_Request;

/**
 * REST API: /wpsms/v1/gateways/fields/{gateway_name}
 * Returns gateway-specific fields for the React app
 */
class GetGatewayFieldsEndpoint extends AbstractSettingsEndpoint
{
    /**
     * Register the gateway fields endpoint.
     */
    public static function register()
    {
        register_rest_route('wpsms/v1', '/gateways/fields/(?P<gateway_name>[a-zA-Z0-9_-]+)', [
            'methods'             => 'GET',
            'callback'            => [__CLASS__, 'getGatewayFields'],
            'permission_callback' => [__CLASS__, 'permissions_check'],
        ]);
    }

    /**
     * GET /gateways/fields/{gateway_name}
     */
    public static function getGatewayFields(WP_REST_Request $request): \WP_REST_Response
    {
        $gatewayName = $request->get_param('gateway_name');
        
        if (empty($gatewayName) || $gatewayName === 'default') {
            return self::success([]);
        }

        try {
            $gatewayFields = self::getGatewaySpecificFields($gatewayName);
            return self::success($gatewayFields);
        } catch (\Exception $e) {
            return self::error($e->getMessage(), 500);
        }
    }

    /**
     * Get gateway-specific fields for a given gateway
     *
     * @param string $gatewayName
     * @return array
     */
    protected static function getGatewaySpecificFields(string $gatewayName): array
    {
        // Try to load the gateway class
        $gatewayClass = '\\WP_SMS\\Gateway\\' . $gatewayName;
        
        if (!class_exists($gatewayClass)) {
            // Try to load from file
            $gatewayFile = WP_SMS_DIR . 'includes/gateways/class-wpsms-gateway-' . $gatewayName . '.php';
            if (file_exists($gatewayFile)) {
                include_once $gatewayFile;
            } else {
                // Try pro gateway
                $proGatewayFile = WP_PLUGIN_DIR . '/wp-sms-pro/includes/gateways/class-wpsms-pro-gateway-' . $gatewayName . '.php';
                if (file_exists($proGatewayFile)) {
                    include_once $proGatewayFile;
                }
            }
        }

        if (!class_exists($gatewayClass)) {
            return [];
        }

        // Create a temporary instance to get the fields
        $gateway = new $gatewayClass();
        
        if (empty($gateway->gatewayFields)) {
            return [];
        }

        $fields = [];
        foreach ($gateway->gatewayFields as $key => $value) {
            if ($gateway->{$key} !== false) {
                $field = [
                    'id'        => $value['id'],
                    'name'      => $value['name'],
                    'desc'      => $value['desc'],
                    'type'      => isset($value['type']) ? $value['type'] : 'text',
                    'className' => isset($value['className']) ? $value['className'] : '',
                ];

                if (isset($value['options']) && is_array($value['options'])) {
                    $field['options'] = $value['options'];
                }

                $fields[] = $field;
            }
        }

        return $fields;
    }
} 