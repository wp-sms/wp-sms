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

    public function getSections(): array
    {
        $isPluginActive = $this->isPluginActive();
        $inactiveNotice = $isPluginActive ? '' : ' <em>(' . __('Plugin not active', 'wp-sms-booking-integrations') . ')</em>';
        
        if (!$isPluginActive) {
            return [
                new Section([
                    'id' => 'plugin_not_active',
                    'title' => __('Plugin Not Active', 'wp-sms-booking-integrations'),
                    'subtitle' => __('WooCommerce Bookings plugin is not active', 'wp-sms-booking-integrations'),
                    'fields' => [
                        new Field([
                            'key' => 'woo_bookings_not_active',
                            'label' => __('Notice', 'wp-sms-booking-integrations'),
                            'type' => 'html',
                            'description' => __('We could not find WooCommerce Bookings plugin. Please install and activate WooCommerce Bookings plugin to use these settings.', 'wp-sms-booking-integrations'),
                            'readonly' => true,
                            'tag' => 'woobookings',
                        ]),
                    ],
                    'readonly' => true,
                    'tag' => 'woobookings',
                    'order' => 1,
                ]),
            ];
        }

        return [
            new Section([
                'id' => 'new_bookings',
                'title' => __('New Bookings', 'wp-sms-booking-integrations'),
                'subtitle' => __('Configure notifications for new bookings', 'wp-sms-booking-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'woo_bookings_notif_admin_new_booking',
                        'label' => __('Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can enable SMS notifications to alert the admin when a new booking is created', 'wp-sms-booking-integrations'),
                        'tag' => 'woobookings',
                    ]),
                    new Field([
                        'key' => 'woo_bookings_notif_admin_new_booking_receiver',
                        'label' => __('Phone number(s)', 'wp-sms-booking-integrations'),
                        'type' => 'text',
                        'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-booking-integrations'),
                        'tag' => 'woobookings',
                    ]),
                    new Field([
                        'key' => 'woo_bookings_notif_admin_new_booking_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'woobookings',
                    ]),
                ],
                'tag' => 'woobookings',
                'order' => 1,
            ]),
            new Section([
                'id' => 'booking_cancelled',
                'title' => __('Booking Cancelled', 'wp-sms-booking-integrations'),
                'subtitle' => __('Configure notifications for cancelled bookings', 'wp-sms-booking-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'woo_bookings_notif_admin_cancelled_booking',
                        'label' => __('Admin Notification Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can enable SMS notifications to alert the admin when an booking is cancelled', 'wp-sms-booking-integrations'),
                        'tag' => 'woobookings',
                    ]),
                    new Field([
                        'key' => 'woo_bookings_notif_admin_cancelled_booking_receiver',
                        'label' => __('Phone number(s)', 'wp-sms-booking-integrations'),
                        'type' => 'text',
                        'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-booking-integrations'),
                        'tag' => 'woobookings',
                    ]),
                    new Field([
                        'key' => 'woo_bookings_notif_admin_cancelled_booking_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'woobookings',
                    ]),
                    new Field([
                        'key' => 'woo_bookings_notif_customer_cancelled_booking',
                        'label' => __('Customer Notification Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can enable SMS notifications to alert the customer when an booking is cancelled', 'wp-sms-booking-integrations'),
                        'tag' => 'woobookings',
                    ]),
                    new Field([
                        'key' => 'woo_bookings_notif_customer_cancelled_booking_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'woobookings',
                    ]),
                ],
                'tag' => 'woobookings',
                'order' => 2,
            ]),
            new Section([
                'id' => 'booking_confirmed',
                'title' => __('Booking Confirmed', 'wp-sms-booking-integrations'),
                'subtitle' => __('Configure notifications for confirmed bookings', 'wp-sms-booking-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'woo_bookings_notif_customer_confirmed_booking',
                        'label' => __('Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can enable SMS notifications to alert the customer when an booking is confirmed', 'wp-sms-booking-integrations'),
                        'tag' => 'woobookings',
                    ]),
                    new Field([
                        'key' => 'woo_bookings_notif_customer_confirmed_booking_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'woobookings',
                    ]),
                ],
                'tag' => 'woobookings',
                'order' => 3,
            ]),
        ];
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