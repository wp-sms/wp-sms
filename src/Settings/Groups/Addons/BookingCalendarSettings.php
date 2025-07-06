<?php

namespace WP_SMS\Settings\Groups\Addons;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\Tags;
use WP_SMS\Settings\LucideIcons;

class BookingCalendarSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'addon_booking_integrations_booking_calendar';
    }

    public function getLabel(): string
    {
        return __('Booking Calendar', 'wp-sms-booking-integrations');
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
                    'subtitle' => __('Booking Calendar plugin is not active', 'wp-sms-booking-integrations'),
                    'fields' => [
                        new Field([
                            'key' => 'booking_calendar_not_active',
                            'label' => __('Notice', 'wp-sms-booking-integrations'),
                            'type' => 'html',
                            'description' => __('We could not find Booking Calendar plugin. Please install and activate Booking Calendar plugin to use these settings.', 'wp-sms-booking-integrations'),
                            'readonly' => true,
                            'tag' => 'bookingcalendar',
                        ]),
                    ],
                    'readonly' => true,
                    'tag' => 'bookingcalendar',
                    'order' => 1,
                ]),
            ];
        }

        return [
            new Section([
                'id' => 'customer_mobile_field',
                'title' => __('Customer Mobile Field', 'wp-sms-booking-integrations'),
                'subtitle' => __('Configure the mobile field for customer notifications', 'wp-sms-booking-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'booking_calendar_notif_customer_mobile_field',
                        'label' => __('Send to field', 'wp-sms-booking-integrations'),
                        'type' => 'select',
                        'options' => $this->getBookingFields(),
                        'description' => __('Select the field to send the SMS to the customer', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingcalendar',
                    ]),
                ],
                'tag' => 'bookingcalendar',
                'order' => 1,
            ]),
            new Section([
                'id' => 'new_booking',
                'title' => __('New Booking', 'wp-sms-booking-integrations'),
                'subtitle' => __('Configure notifications for new bookings', 'wp-sms-booking-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'booking_calendar_notif_admin_new_booking',
                        'label' => __('Admin Notification Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can enable SMS notifications to alert the admin when a new booking is created', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingcalendar',
                    ]),
                    new Field([
                        'key' => 'booking_calendar_notif_admin_new_booking_receiver',
                        'label' => __('Phone number(s)', 'wp-sms-booking-integrations'),
                        'type' => 'text',
                        'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingcalendar',
                    ]),
                    new Field([
                        'key' => 'booking_calendar_notif_admin_new_booking_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingcalendar',
                    ]),
                    new Field([
                        'key' => 'booking_calendar_notif_customer_new_booking',
                        'label' => __('Customer Notification Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can enable SMS notifications to alert the customer when a new booking is created', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingcalendar',
                    ]),
                    new Field([
                        'key' => 'booking_calendar_notif_customer_new_booking_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingcalendar',
                    ]),
                ],
                'tag' => 'bookingcalendar',
                'order' => 2,
            ]),
            new Section([
                'id' => 'booking_approved',
                'title' => __('Booking Approved', 'wp-sms-booking-integrations'),
                'subtitle' => __('Configure notifications for approved bookings', 'wp-sms-booking-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'booking_calendar_notif_customer_booking_approved',
                        'label' => __('Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can enable SMS notifications to alert the customer when a booking is approved', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingcalendar',
                    ]),
                    new Field([
                        'key' => 'booking_calendar_notif_customer_booking_approved_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingcalendar',
                    ]),
                ],
                'tag' => 'bookingcalendar',
                'order' => 3,
            ]),
            new Section([
                'id' => 'booking_cancelled',
                'title' => __('Booking Cancelled', 'wp-sms-booking-integrations'),
                'subtitle' => __('Configure notifications for cancelled bookings', 'wp-sms-booking-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'booking_calendar_notif_customer_booking_cancelled',
                        'label' => __('Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can enable SMS notifications to alert the customer when a booking is cancelled', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingcalendar',
                    ]),
                    new Field([
                        'key' => 'booking_calendar_notif_customer_booking_cancelled_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingcalendar',
                    ]),
                ],
                'tag' => 'bookingcalendar',
                'order' => 4,
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
               (file_exists(WP_PLUGIN_DIR . '/booking/wpdev-booking.php') || class_exists('Booking_Calendar'));
    }

    private function getBookingFields(): array
    {
        if (!function_exists('\wpbc_simple_form__db__get_visual_form_structure')) {
            return [];
        }

        $fields    = \wpbc_simple_form__db__get_visual_form_structure();
        $fieldData = [];

        foreach ($fields as $field) {
            if ($field['type'] == 'text' && $field['active'] == 'On') {
                $fieldData[$field['name']] = $field['label'];
            }
        }

        return $fieldData;
    }
} 