<?php

namespace WP_SMS\Services\Assets;

use WP_SMS\Services\Assets\Handlers\ReactHandler;

/**
 * Assets Factory.
 *
 * Factory class for creating and managing assets instances.
 * Provides methods to load React, Legacy and frontend assets.
 *
 * @package WP_SMS\Service\Assets
 * @since   7.2
 */
class AssetsFactory
{
    /**
     * Load React admin assets.
     *
     * @return ReactHandler|null React assets instance
     */
    public static function React()
    {
        if (!class_exists(ReactHandler::class)) {
            return null;
        }

        return new ReactHandler();
    }
}