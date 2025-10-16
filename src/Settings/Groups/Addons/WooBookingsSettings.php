<?php

namespace WP_SMS\Settings\Groups\Addons;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\Tags;
use WP_SMS\Settings\LucideIcons;

class WooBookingsSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'addon_booking_integrations_woo_bookings';
    }

    public function getLabel(): string
    {
        return __('WooCommerce Bookings', 'wp-sms-booking-integrations');
    }

    public function getIcon(): string
    {
        return LucideIcons::CALENDAR;
    }

    public function getMetaData(){
        return [
            'addon' => 'booking_integrations',
        ];
    }

    public function getSections(): array
    {
        $isPluginActive = $this->isPluginActive();
        $sections = [];

        // Always show plugin status notice first when plugin is inactive
        if (!$isPluginActive) {
            $sections[] = new Section([
                'id' => 'woo_bookings_integration',
                'title' => __('WooCommerce Bookings Integration', 'wp-sms-booking-integrations'),
                'subtitle' => __('Connect WooCommerce Bookings to enable SMS options.', 'wp-sms-booking-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'woo_bookings_not_active_notice',
                        'label' => __('Not active', 'wp-sms-booking-integrations'),
                        'type' => 'notice',
                        'description' => __('WooCommerce Bookings is not installed or active. Install and activate WooCommerce Bookings to configure SMS notifications.', 'wp-sms-booking-integrations')
                    ])
                ]
            ]);
        }

        $sections[] = new Section([
            'id' => 'new_bookings',
            'title' => __('New Bookings', 'wp-sms-booking-integrations'),
            'subtitle' => __('Configure notifications for new bookings', 'wp-sms-booking-integrations'),
            'fields' => [
                new Field([
                    'key' => 'woo_bookings_notif_admin_new_booking',
                    'label' => __('Status', 'wp-sms-booking-integrations'),
                    'type' => 'checkbox',
                    'description' => __('By this option you can enable SMS notifications to alert the admin when a new booking is created', 'wp-sms-booking-integrations'),
                    'readonly' => !$isPluginActive,
                    'tag' => 'woobookings',
                ]),
                new Field([
                    'key' => 'woo_bookings_notif_admin_new_booking_receiver',
                    'label' => __('Phone number(s)', 'wp-sms-booking-integrations'),
                    'type' => 'text',
                    'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-booking-integrations'),
                    'readonly' => !$isPluginActive,
                    'tag' => 'woobookings',
                ]),
                new Field([
                    'key' => 'woo_bookings_notif_admin_new_booking_message',
                    'label' => __('Message Body', 'wp-sms-booking-integrations'),
                    'type' => 'textarea',
                    'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                    'readonly' => !$isPluginActive,
                    'tag' => 'woobookings',
                ]),
            ],
            'readonly' => !$isPluginActive,
            'tag' => 'woobookings',
            'order' => 1,
        ]);
        $sections[] = new Section([
            'id' => 'booking_cancelled',
            'title' => __('Booking Cancelled', 'wp-sms-booking-integrations'),
            'subtitle' => __('Configure notifications for cancelled bookings', 'wp-sms-booking-integrations'),
            'fields' => [
                new Field([
                    'key' => 'woo_bookings_notif_admin_cancelled_booking',
                    'label' => __('Admin Notification Status', 'wp-sms-booking-integrations'),
                    'type' => 'checkbox',
                    'description' => __('By this option you can enable SMS notifications to alert the admin when an booking is cancelled', 'wp-sms-booking-integrations'),
                    'readonly' => !$isPluginActive,
                    'tag' => 'woobookings',
                ]),
                new Field([
                    'key' => 'woo_bookings_notif_admin_cancelled_booking_receiver',
                    'label' => __('Phone number(s)', 'wp-sms-booking-integrations'),
                    'type' => 'text',
                    'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-booking-integrations'),
                    'readonly' => !$isPluginActive,
                    'tag' => 'woobookings',
                ]),
                new Field([
                    'key' => 'woo_bookings_notif_admin_cancelled_booking_message',
                    'label' => __('Message Body', 'wp-sms-booking-integrations'),
                    'type' => 'textarea',
                    'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                    'readonly' => !$isPluginActive,
                    'tag' => 'woobookings',
                ]),
                new Field([
                    'key' => 'woo_bookings_notif_customer_cancelled_booking',
                    'label' => __('Customer Notification Status', 'wp-sms-booking-integrations'),
                    'type' => 'checkbox',
                    'description' => __('By this option you can enable SMS notifications to alert the customer when an booking is cancelled', 'wp-sms-booking-integrations'),
                    'readonly' => !$isPluginActive,
                    'tag' => 'woobookings',
                ]),
                new Field([
                    'key' => 'woo_bookings_notif_customer_cancelled_booking_message',
                    'label' => __('Message Body', 'wp-sms-booking-integrations'),
                    'type' => 'textarea',
                    'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                    'readonly' => !$isPluginActive,
                    'tag' => 'woobookings',
                ]),
            ],
            'readonly' => !$isPluginActive,
            'tag' => 'woobookings',
            'order' => 2,
        ]);
        $sections[] = new Section([
            'id' => 'booking_confirmed',
            'title' => __('Booking Confirmed', 'wp-sms-booking-integrations'),
            'subtitle' => __('Configure notifications for confirmed bookings', 'wp-sms-booking-integrations'),
            'fields' => [
                new Field([
                    'key' => 'woo_bookings_notif_customer_confirmed_booking',
                    'label' => __('Status', 'wp-sms-booking-integrations'),
                    'type' => 'checkbox',
                    'description' => __('By this option you can enable SMS notifications to alert the customer when an booking is confirmed', 'wp-sms-booking-integrations'),
                    'readonly' => !$isPluginActive,
                    'tag' => 'woobookings',
                ]),
                new Field([
                    'key' => 'woo_bookings_notif_customer_confirmed_booking_message',
                    'label' => __('Message Body', 'wp-sms-booking-integrations'),
                    'type' => 'textarea',
                    'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                    'readonly' => !$isPluginActive,
                    'tag' => 'woobookings',
                ]),
            ],
            'readonly' => !$isPluginActive,
            'tag' => 'woobookings',
            'order' => 3,
        ]);

        return $sections;
    }

    public function getFields(): array
    {
        // Legacy method - return all fields from all sections for backward compatibility
        $allFields = [];
        foreach ($this->getSections() as $section) {
            $allFields = array_merge($allFields, $section->getFields());
        }
        return $allFields;
    }

    private function isPluginActive(): bool
    {
        return class_exists('WPSmsBookingIntegrationsPlugin\WPSmsBookingIntegrationsPlugin') && 
               class_exists('WC_Bookings');
    }

    public function getOptionKeyName(): ?string
    {
        return 'booking_integrations';
    }
} 