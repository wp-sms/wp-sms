<?php

namespace WP_SMS\Services\Database\Migrations;

use Exception;

/**
 * Manages migrations related to WordPress options.
 */
class OptionMigration extends AbstractMigrationOperation
{
    /**
     * The name of the migration operation.
     *
     * @var string
     */
    protected $name = 'option';

    protected $migrationSteps = [
        '7.2.0' => [
            'migrateMainSettings',
            'migrateProSettings',
            'migrateTwoWaySettings',
            'migrateBookingIntegrationsSettings',
            'migrateFluentIntegrationsSettings'
        ],
    ];

    /**
     * Migrate main plugin settings from wpsms_settings to wp_sms_settings
     */
    public function migrateMainSettings()
    {
        try {
            $old_options = get_option('wpsms_settings', []);
            if (!empty($old_options)) {
                // Get current new options
                $new_options = get_option('wp_sms_settings', []);
                
                // Merge old options with new ones (new options take precedence)
                $merged_options = array_merge($old_options, $new_options);
                
                // Update the new option key
                update_option('wp_sms_settings', $merged_options);
                
                // TODO: Add logging for successful migration
            }
        } catch (Exception $e) {
            dd($e);
            $this->setErrorStatus($e->getMessage());
        }
    }

    /**
     * Migrate Pro settings from wps_pp_settings to wp_sms_pro_settings
     */
    public function migrateProSettings()
    {
        try {
            $old_options = get_option('wps_pp_settings', []);
            if (!empty($old_options)) {
                // Get current new options
                $new_options = get_option('wp_sms_pro_settings', []);
                
                // Merge old options with new ones (new options take precedence)
                $merged_options = array_merge($old_options, $new_options);
                
                // Update the new option key
                update_option('wp_sms_pro_settings', $merged_options);
                
                // TODO: Add logging for successful migration
            }
        } catch (Exception $e) {
            $this->setErrorStatus($e->getMessage());
        }
    }

    /**
     * Migrate Two-Way settings from wpsms_settings to wp_sms_two_way_settings
     */
    public function migrateTwoWaySettings()
    {
        try {
            $old_options = get_option('wpsms_settings', []);
            if (!empty($old_options)) {
                $two_way_settings = [];
                
                // Two-Way specific settings to migrate
                $two_way_option_keys = [
                    'gateway_name',
                    'gateway_username',
                    'gateway_password',
                    'notif_new_inbox_message',
                    'notif_new_inbox_message_template',
                    'admin_mobile_number',
                    'email_new_inbox_message'
                ];
                
                foreach ($two_way_option_keys as $key) {
                    if (isset($old_options[$key])) {
                        $two_way_settings[$key] = $old_options[$key];
                    }
                }
                
                if (!empty($two_way_settings)) {
                    // Get current new options
                    $new_options = get_option('wp_sms_two_way_settings', []);
                    
                    // Merge old options with new ones (new options take precedence)
                    $merged_options = array_merge($two_way_settings, $new_options);
                    
                    // Update the new option key
                    update_option('wp_sms_two_way_settings', $merged_options);
                    
                    // TODO: Add logging for successful migration
                }
            }
        } catch (Exception $e) {
            $this->setErrorStatus($e->getMessage());
        }
    }

    /**
     * Migrate Booking Integrations settings from wpsms_settings to wp_sms_booking_integrations_settings
     */
    public function migrateBookingIntegrationsSettings()
    {
        try {
            $old_options = get_option('wpsms_settings', []);
            if (!empty($old_options)) {
                $booking_settings = [];
                
                // Booking Calendar settings
                $booking_calendar_keys = [
                    'booking_calendar_notif_admin_new_booking',
                    'booking_calendar_notif_admin_new_booking_receiver',
                    'booking_calendar_notif_admin_new_booking_message',
                    'booking_calendar_notif_customer_new_booking',
                    'booking_calendar_notif_customer_new_booking_message',
                    'booking_calendar_notif_customer_booking_approved',
                    'booking_calendar_notif_customer_booking_approved_message',
                    'booking_calendar_notif_customer_booking_cancelled',
                    'booking_calendar_notif_customer_booking_cancelled_message',
                    'booking_calendar_notif_customer_mobile_field'
                ];
                
                // WooAppointments settings
                $woo_appointments_keys = [
                    'woo_appointments_notif_admin_new_appointment',
                    'woo_appointments_notif_admin_new_appointment_receiver',
                    'woo_appointments_notif_admin_new_appointment_message',
                    'woo_appointments_notif_admin_cancelled_appointment',
                    'woo_appointments_notif_admin_cancelled_appointment_receiver',
                    'woo_appointments_notif_admin_cancelled_appointment_message',
                    'woo_appointments_notif_customer_cancelled_appointment',
                    'woo_appointments_notif_customer_cancelled_appointment_message',
                    'woo_appointments_notif_admin_rescheduled_appointment',
                    'woo_appointments_notif_admin_rescheduled_appointment_receiver',
                    'woo_appointments_notif_admin_rescheduled_appointment_message',
                    'woo_appointments_notif_customer_confirmed_appointment',
                    'woo_appointments_notif_customer_confirmed_appointment_message'
                ];
                
                // BookingPress settings
                $bookingpress_keys = [
                    'bookingpress_notif_admin_approved_appointment',
                    'bookingpress_notif_admin_approved_appointment_receiver',
                    'bookingpress_notif_admin_approved_appointment_message',
                    'bookingpress_notif_customer_approved_appointment',
                    'bookingpress_notif_customer_approved_appointment_message',
                    'bookingpress_notif_admin_pending_appointment',
                    'bookingpress_notif_admin_pending_appointment_receiver',
                    'bookingpress_notif_admin_pending_appointment_message',
                    'bookingpress_notif_customer_pending_appointment',
                    'bookingpress_notif_customer_pending_appointment_message',
                    'bookingpress_notif_admin_cancelled_appointment',
                    'bookingpress_notif_admin_cancelled_appointment_receiver',
                    'bookingpress_notif_admin_cancelled_appointment_message',
                    'bookingpress_notif_customer_cancelled_appointment',
                    'bookingpress_notif_customer_cancelled_appointment_message',
                    'bookingpress_notif_admin_rejected_appointment',
                    'bookingpress_notif_admin_rejected_appointment_receiver',
                    'bookingpress_notif_admin_rejected_appointment_message',
                    'bookingpress_notif_customer_rejected_appointment',
                    'bookingpress_notif_customer_rejected_appointment_message'
                ];
                
                // WooBookings settings
                $woo_bookings_keys = [
                    'woo_bookings_notif_admin_new_booking',
                    'woo_bookings_notif_admin_new_booking_receiver',
                    'woo_bookings_notif_admin_new_booking_message',
                    'woo_bookings_notif_admin_cancelled_booking',
                    'woo_bookings_notif_admin_cancelled_booking_receiver',
                    'woo_bookings_notif_admin_cancelled_booking_message',
                    'woo_bookings_notif_customer_cancelled_booking',
                    'woo_bookings_notif_customer_cancelled_booking_message',
                    'woo_bookings_notif_customer_confirmed_booking',
                    'woo_bookings_notif_customer_confirmed_booking_message'
                ];
                
                // Combine all booking-related keys
                $all_booking_keys = array_merge(
                    $booking_calendar_keys,
                    $woo_appointments_keys,
                    $bookingpress_keys,
                    $woo_bookings_keys
                );
                
                foreach ($all_booking_keys as $key) {
                    if (isset($old_options[$key])) {
                        $booking_settings[$key] = $old_options[$key];
                    }
                }
                
                if (!empty($booking_settings)) {
                    // Get current new options
                    $new_options = get_option('wp_sms_booking_integrations_settings', []);
                    
                    // Merge old options with new ones (new options take precedence)
                    $merged_options = array_merge($booking_settings, $new_options);
                    
                    // Update the new option key
                    update_option('wp_sms_booking_integrations_settings', $merged_options);
                    
                    // TODO: Add logging for successful migration
                }
            }
        } catch (Exception $e) {
            $this->setErrorStatus($e->getMessage());
        }
    }

    /**
     * Migrate Fluent Integrations settings from wpsms_settings to wp_sms_fluent_integrations_settings
     */
    public function migrateFluentIntegrationsSettings()
    {
        try {
            $old_options = get_option('wpsms_settings', []);
            if (!empty($old_options)) {
                $fluent_settings = [];
                
                // FluentCRM settings
                $fluent_crm_keys = [
                    'fluent_crm_notif_contact_subscribed',
                    'fluent_crm_notif_contact_unsubscribed',
                    'fluent_crm_notif_contact_pending',
                    'fluent_crm_notif_contact_subscribed_message',
                    'fluent_crm_notif_contact_unsubscribed_message',
                    'fluent_crm_notif_contact_pending_message'
                ];
                
                // FluentSupport settings
                $fluent_support_keys = [
                    'fluent_support_notif_ticket_created',
                    'fluent_support_notif_customer_response',
                    'fluent_support_notif_agent_assigned',
                    'fluent_support_notif_ticket_closed',
                    'fluent_support_notif_ticket_created_receiver',
                    'fluent_support_notif_ticket_created_message',
                    'fluent_support_notif_customer_response_receiver',
                    'fluent_support_notif_customer_response_message',
                    'fluent_support_notif_agent_assigned_receiver',
                    'fluent_support_notif_agent_assigned_message',
                    'fluent_support_notif_ticket_closed_receiver',
                    'fluent_support_notif_ticket_closed_message'
                ];
                
                // FluentForms settings (these are dynamic based on form IDs)
                $fluent_forms_keys = [];
                foreach ($old_options as $key => $value) {
                    if (strpos($key, 'fluent_forms_notif_') === 0) {
                        $fluent_forms_keys[] = $key;
                    }
                }
                
                // Combine all fluent-related keys
                $all_fluent_keys = array_merge(
                    $fluent_crm_keys,
                    $fluent_support_keys,
                    $fluent_forms_keys
                );
                
                foreach ($all_fluent_keys as $key) {
                    if (isset($old_options[$key])) {
                        $fluent_settings[$key] = $old_options[$key];
                    }
                }
                
                if (!empty($fluent_settings)) {
                    // Get current new options
                    $new_options = get_option('wp_sms_fluent_integrations_settings', []);
                    
                    // Merge old options with new ones (new options take precedence)
                    $merged_options = array_merge($fluent_settings, $new_options);
                    
                    // Update the new option key
                    update_option('wp_sms_fluent_integrations_settings', $merged_options);
                    
                    // TODO: Add logging for successful migration
                }
            }
        } catch (Exception $e) {
            $this->setErrorStatus($e->getMessage());
        }
    }
}
