<?php

namespace WP_SMS\Services\LocalizedData;

use WP_SMS\Services\LocalizedData\Providers\GlobalsDataProvider;
use WP_SMS\Services\LocalizedData\Providers\LayoutDataProvider;

/**
 * Localized Data Factory
 *
 * Factory class for creating LocalizedDataService instances for React.
 *
 * @package WP_SMS\Services\LocalizedData
 * @since   7.2
 */
class LocalizedDataFactory
{
    /**
     * Create a LocalizedDataService for React context
     *
     * @return LocalizedDataService
     */
    public static function react(): LocalizedDataService
    {
        $service = new LocalizedDataService();

        // Add React-specific providers
        $service->addProvider(new GlobalsDataProvider());
        $service->addProvider(new LayoutDataProvider()); // Provides both sidebar and header under 'layout' key

        return apply_filters('wp_sms_localized_data_service_react', $service);
    }
}
