<?php

namespace WP_SMS\Services\LocalizedData\Providers;

use WP_SMS\Services\LocalizedData\DataProviderInterface;
use WP_SMS\Settings\SchemaRegistry;

/**
 * Layout Data Provider
 *
 * Provides layout-related data including sidebar and header navigation.
 *
 * @package WP_SMS\Services\LocalizedData\Providers
 * @since   7.2
 */
class LayoutDataProvider implements DataProviderInterface
{
    /**
     * Get the provider's unique key
     *
     * @return string
     */
    public function getKey(): string
    {
        return 'layout';
    }

    /**
     * Get layout data (sidebar and header)
     *
     * @return array
     */
    public function getData(): array
    {
        $data = [];

        // Sidebar data
        $data['sidebar'] = SchemaRegistry::instance()->exportGroupList();

        // Header data
        $data['header'] = [
            [
                'title'       => esc_html__('Send SMS', 'wp-sms'),
                'url'         => admin_url('admin.php?page=wp-sms'),
                'icon'        => 'MessageSquarePlus',
                'description' => __('Compose and send SMS messages', 'wp-sms'),
                'isExternal'  => false,
            ],
            [
                'title'       => esc_html__('Inbox', 'wp-sms'),
                'url'         => admin_url('admin.php?page=wp-sms-inbox'),
                'icon'        => 'Inbox',
                'description' => esc_html__('View received messages', 'wp-sms'),
                'isExternal'  => false,
            ],
            [
                'title'       => esc_html__('Outbox', 'wp-sms'),
                'url'         => admin_url('admin.php?page=wp-sms-outbox'),
                'icon'        => 'Send',
                'description' => esc_html__('View sent messages', 'wp-sms'),
                'isExternal'  => false,
            ],
            [
                'title'       => esc_html__('Integrations', 'wp-sms'),
                'url'         => admin_url('admin.php?page=wp-sms-integrations'),
                'icon'        => 'Puzzle',
                'description' => esc_html__('Manage third-party integrations', 'wp-sms'),
                'isExternal'  => false,
            ],
            [
                'title'       => esc_html__('Upgrade', 'wp-sms'),
                'url'         => 'https://wp-sms-pro.com/pricing/?utm_source=wp-sms&utm_medium=link&utm_campaign=header',
                'icon'        => 'Crown',
                'description' => esc_html__('Upgrade to premium version', 'wp-sms'),
                'isExternal'  => true,
            ]
        ];

        return apply_filters('wp_sms_localized_layout_data', $data);
    }
}
