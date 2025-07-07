<?php

namespace WP_SMS\Settings\Groups\Addons;

use WP_SMS\Settings\Abstracts\AbstractSettingGroup;
use WP_SMS\Settings\Field;
use WP_SMS\Settings\Section;
use WP_SMS\Settings\Tags;
use WP_SMS\Settings\LucideIcons;

class WooAppointmentsSettings extends AbstractSettingGroup
{
    public function getName(): string
    {
        return 'addon_booking_integrations_woo_appointments';
    }

    public function getLabel(): string
    {
        return __('WooCommerce Appointments', 'wp-sms-booking-integrations');
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
                    'subtitle' => __('WooCommerce Appointments plugin is not active', 'wp-sms-booking-integrations'),
                    'fields' => [
                        new Field([
                            'key' => 'woo_appointments_not_active',
                            'label' => __('Notice', 'wp-sms-booking-integrations'),
                            'type' => 'html',
                            'description' => __('We could not find WooCommerce Appointments plugin. Please install and activate WooCommerce Appointments plugin to use these settings.', 'wp-sms-booking-integrations'),
                            'readonly' => true,
                            'tag' => 'wooappointments',
                        ]),
                    ],
                    'readonly' => true,
                    'tag' => 'wooappointments',
                    'order' => 1,
                ]),
            ];
        }

        return [
            new Section([
                'id' => 'new_appointments',
                'title' => __('New Appointments', 'wp-sms-booking-integrations'),
                'subtitle' => __('Configure notifications for new appointments', 'wp-sms-booking-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'woo_appointments_notif_admin_new_appointment',
                        'label' => __('Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can enable SMS notifications to alert the admin when a new appointment is created', 'wp-sms-booking-integrations'),
                        'tag' => 'wooappointments',
                    ]),
                    new Field([
                        'key' => 'woo_appointments_notif_admin_new_appointment_receiver',
                        'label' => __('Phone number(s)', 'wp-sms-booking-integrations'),
                        'type' => 'text',
                        'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-booking-integrations'),
                        'tag' => 'wooappointments',
                    ]),
                    new Field([
                        'key' => 'woo_appointments_notif_admin_new_appointment_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'wooappointments',
                    ]),
                ],
                'tag' => 'wooappointments',
                'order' => 1,
            ]),
            new Section([
                'id' => 'appointment_cancelled',
                'title' => __('Appointment Cancelled', 'wp-sms-booking-integrations'),
                'subtitle' => __('Configure notifications for cancelled appointments', 'wp-sms-booking-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'woo_appointments_notif_admin_cancelled_appointment',
                        'label' => __('Admin Notification Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can enable SMS notifications to alert the admin when an appointment is cancelled', 'wp-sms-booking-integrations'),
                        'tag' => 'wooappointments',
                    ]),
                    new Field([
                        'key' => 'woo_appointments_notif_admin_cancelled_appointment_receiver',
                        'label' => __('Phone number(s)', 'wp-sms-booking-integrations'),
                        'type' => 'text',
                        'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-booking-integrations'),
                        'tag' => 'wooappointments',
                    ]),
                    new Field([
                        'key' => 'woo_appointments_notif_admin_cancelled_appointment_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'wooappointments',
                    ]),
                    new Field([
                        'key' => 'woo_appointments_notif_customer_cancelled_appointment',
                        'label' => __('Customer Notification Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can enable SMS notifications to alert the customer when an appointment is cancelled', 'wp-sms-booking-integrations'),
                        'tag' => 'wooappointments',
                    ]),
                    new Field([
                        'key' => 'woo_appointments_notif_customer_cancelled_appointment_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'wooappointments',
                    ]),
                ],
                'tag' => 'wooappointments',
                'order' => 2,
            ]),
            new Section([
                'id' => 'appointment_rescheduled',
                'title' => __('Appointment Rescheduled', 'wp-sms-booking-integrations'),
                'subtitle' => __('Configure notifications for rescheduled appointments', 'wp-sms-booking-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'woo_appointments_notif_admin_rescheduled_appointment',
                        'label' => __('Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can enable SMS notifications to alert the admin when an appointment is rescheduled', 'wp-sms-booking-integrations'),
                        'tag' => 'wooappointments',
                    ]),
                    new Field([
                        'key' => 'woo_appointments_notif_admin_rescheduled_appointment_receiver',
                        'label' => __('Phone number(s)', 'wp-sms-booking-integrations'),
                        'type' => 'text',
                        'description' => __('Enter the mobile number(s) to receive SMS, to separate numbers, use the latin comma.', 'wp-sms-booking-integrations'),
                        'tag' => 'wooappointments',
                    ]),
                    new Field([
                        'key' => 'woo_appointments_notif_admin_rescheduled_appointment_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'wooappointments',
                    ]),
                ],
                'tag' => 'wooappointments',
                'order' => 3,
            ]),
            new Section([
                'id' => 'appointment_confirmed',
                'title' => __('Appointment Confirmed', 'wp-sms-booking-integrations'),
                'subtitle' => __('Configure notifications for confirmed appointments', 'wp-sms-booking-integrations'),
                'fields' => [
                    new Field([
                        'key' => 'woo_appointments_notif_customer_confirmed_appointment',
                        'label' => __('Status', 'wp-sms-booking-integrations'),
                        'type' => 'checkbox',
                        'description' => __('By this option you can enable SMS notifications to alert the customer when an appointment is confirmed', 'wp-sms-booking-integrations'),
                        'tag' => 'wooappointments',
                    ]),
                    new Field([
                        'key' => 'woo_appointments_notif_customer_confirmed_appointment_message',
                        'label' => __('Message Body', 'wp-sms-booking-integrations'),
                        'type' => 'textarea',
                        'description' => __('Enter the contents of the SMS message', 'wp-sms-booking-integrations'),
                        'tag' => 'wooappointments',
                    ]),
                ],
                'tag' => 'wooappointments',
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
               class_exists('WC_Appointments');
    }

    public function getOptionKeyName(): ?string
    {
        return 'booking_integrations';
    }
} 