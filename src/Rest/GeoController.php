<?php

namespace WSms\Rest;

use WP_REST_Response;
use WSms\Support\GeoCountryResolver;

defined('ABSPATH') || exit;

class GeoController extends Controller
{
    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/geo-country', [
            'methods'             => 'GET',
            'callback'            => fn () => new WP_REST_Response([
                'country' => GeoCountryResolver::resolve(),
            ]),
            'permission_callback' => '__return_true',
        ]);
    }
}
