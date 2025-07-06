<?php

namespace WP_SMS\Migrations;

use WP_SMS\Settings\Option;

class OptionMigrations
{
    /**
     * Register migration hooks
     */
    public function register()
    {
        add_action('admin_init', [$this, 'migrateOptions']);
    }

    /**
     * Migrate options from old keys to new addon-specific keys
     */
    public function migrateOptions()
    {
        // Check if migration has already been completed
        if (get_option('wp_sms_options_migrated', false)) {
            return;
        }

        // Migrate main plugin settings
        $this->migrateMainSettings();
        
        // Migrate Pro settings
        $this->migrateProSettings();
        
        // Migrate Two-Way settings
        $this->migrateTwoWaySettings();
        
        // Migrate Booking Integrations settings
        $this->migrateBookingIntegrationsSettings();
        
        // Migrate Fluent Integrations settings
        $this->migrateFluentIntegrationsSettings();

        // Mark migration as completed
        update_option('wp_sms_options_migrated', true);
    }

    /**
     * Migrate main plugin settings from wpsms_settings to wp_sms_settings
     */
    private function migrateMainSettings()
    {
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
    }

    /**
     * Migrate Pro settings from wps_pp_settings to wp_sms_pro_settings
     */
    private function migrateProSettings()
    {
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
    }

    /**
     * Migrate Two-Way settings from wpsms_settings to wp_sms_two_way_settings
     */
    private function migrateTwoWaySettings()
    {
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
    }

    /**
     * Migrate Booking Integrations settings from wpsms_settings to wp_sms_booking_integrations_settings
     */
    private function migrateBookingIntegrationsSettings()
    {
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
    }

    /**
     * Migrate Fluent Integrations settings from wpsms_settings to wp_sms_fluent_integrations_settings
     */
    private function migrateFluentIntegrationsSettings()
    {
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
    }
}