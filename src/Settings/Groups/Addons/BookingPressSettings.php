<?php

namespace WP_SMS\Settings\Groups\Addons;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\Tags;
use WP_SMS\Settings\LucideIcons;

class BookingPressSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'addon_booking_integrations_bookingpress';
    }

    public function getLabel(): string
    {
        return __('BookingPress', 'wp-sms-booking-integrations');
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
                    'subtitle' => __('BookingPress plugin is not active', 'wp-sms-booking-integrations'),
                    'fields' => [
                        new Field([
                            'key' => 'bookingpress_not_active',
                            'label' => __('Notice', 'wp-sms-booking-integrations'),
                            'type' => 'html',
                            'description' => __('We could not find BookingPress plugin. Please install and activate BookingPress plugin to use these settings.', 'wp-sms-booking-integrations'),
                            'readonly' => true,
                            'tag' => 'bookingpress',
                        ]),
                    ],
                    'readonly' => true,
                    'tag' => 'bookingpress',
                    'order' => 1,
                ]),
            ];
        }

        return [
            new Section([
                'id' => 'approved_appointment',
                'title' => __('On Approval Appointment', 'wp-sms-booking-integrations'),
                'subtitle' => __('Configure notifications for approved appointments', 'wp-sms-booking-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'bookingpress_notif_admin_approved_appointment',
                        'label' => __('Admin Notification Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can add SMS notification for appointment approved', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                    new Field([
                        'key' => 'bookingpress_notif_admin_approved_appointment_receiver',
                        'label' => __('Phone number(s)', 'wp-sms-booking-integrations'),
                        'type' => 'text',
                        'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                    new Field([
                        'key' => 'bookingpress_notif_admin_approved_appointment_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                    new Field([
                        'key' => 'bookingpress_notif_customer_approved_appointment',
                        'label' => __('Customer Notification Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can add SMS notification for appointment approved', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                    new Field([
                        'key' => 'bookingpress_notif_customer_approved_appointment_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                ],
                'tag' => 'bookingpress',
                'order' => 1,
            ]),
            new Section([
                'id' => 'pending_appointment',
                'title' => __('On Pending Appointment', 'wp-sms-booking-integrations'),
                'subtitle' => __('Configure notifications for pending appointments', 'wp-sms-booking-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'bookingpress_notif_admin_pending_appointment',
                        'label' => __('Admin Notification Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can add SMS notification for pending appointment', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                    new Field([
                        'key' => 'bookingpress_notif_admin_pending_appointment_receiver',
                        'label' => __('Phone number(s)', 'wp-sms-booking-integrations'),
                        'type' => 'text',
                        'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                    new Field([
                        'key' => 'bookingpress_notif_admin_pending_appointment_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                    new Field([
                        'key' => 'bookingpress_notif_customer_pending_appointment',
                        'label' => __('Customer Notification Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can add SMS notification for pending appointment', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                    new Field([
                        'key' => 'bookingpress_notif_customer_pending_appointment_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                ],
                'tag' => 'bookingpress',
                'order' => 2,
            ]),
            new Section([
                'id' => 'rejected_appointment',
                'title' => __('On Rejection Appointment', 'wp-sms-booking-integrations'),
                'subtitle' => __('Configure notifications for rejected appointments', 'wp-sms-booking-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'bookingpress_notif_admin_rejected_appointment',
                        'label' => __('Admin Notification Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can add SMS notification for appointment rejected', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                    new Field([
                        'key' => 'bookingpress_notif_admin_rejected_appointment_receiver',
                        'label' => __('Phone number(s)', 'wp-sms-booking-integrations'),
                        'type' => 'text',
                        'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                    new Field([
                        'key' => 'bookingpress_notif_admin_rejected_appointment_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                    new Field([
                        'key' => 'bookingpress_notif_customer_rejected_appointment',
                        'label' => __('Customer Notification Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can add SMS notification for appointment rejected', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                    new Field([
                        'key' => 'bookingpress_notif_customer_rejected_appointment_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                ],
                'tag' => 'bookingpress',
                'order' => 3,
            ]),
            new Section([
                'id' => 'cancelled_appointment',
                'title' => __('On Cancellation Appointment', 'wp-sms-booking-integrations'),
                'subtitle' => __('Configure notifications for cancelled appointments', 'wp-sms-booking-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'bookingpress_notif_admin_cancelled_appointment',
                        'label' => __('Admin Notification Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can add SMS notification for appointment cancelled', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                    new Field([
                        'key' => 'bookingpress_notif_admin_cancelled_appointment_receiver',
                        'label' => __('Phone number(s)', 'wp-sms-booking-integrations'),
                        'type' => 'text',
                        'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                    new Field([
                        'key' => 'bookingpress_notif_admin_cancelled_appointment_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                    new Field([
                        'key' => 'bookingpress_notif_customer_cancelled_appointment',
                        'label' => __('Customer Notification Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can add SMS notification for appointment cancelled', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                    new Field([
                        'key' => 'bookingpress_notif_customer_cancelled_appointment_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'bookingpress',
                    ]),
                ],
                'tag' => 'bookingpress',
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
               class_exists('BookingPress');
    }
} 