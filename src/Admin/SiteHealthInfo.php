<?php

namespace WP_SMS\Admin;

use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\Gateway;
use WP_SMS\Option as WPSmsOptionsManager;
use WP_SMS\Utils\OptionUtil;
use WP_SMS\Option;

class SiteHealthInfo
{
    const DEBUG_INFO_SLUG = 'wp_sms';

    public function register()
    {
        add_filter('debug_information', array($this, 'addSmsPluginInfo'));
    }

    public function addSmsPluginInfo($info)
    {
        $info[self::DEBUG_INFO_SLUG] = array(
            'label'       => esc_html__('WP SMS', 'wp-sms'),
            'description' => esc_html__('This section provides debug information about your WP SMS plugin settings.', 'wp-sms'),
            'fields'      => $this->getSmsSettings(),
        );

        return $info;
    }

    protected function getSmsSettings()
    {
        global $sms;
        $settings = array();

        $pluginHandler = new PluginHandler();

        $yesNo = function ($value) {
            return $value ? __('Enabled', 'wp-sms') : __('Disabled', 'wp-sms');
        };

        $yesNoDebug = function ($value) {
            return $value ? 'Enabled' : 'Disabled';
        };

        $raw = function ($key, $default = null) {
            return OptionUtil::get($key, $default);
        };

        $woo = function ($key, $default = null) {
            return \WPSmsWooPro\Core\Helper::getOption($key, $default);
        };

        $toCamelCase = function ($string) {
            return ucwords(str_replace('_', ' ', $string));
        };


        $ver                        = defined('WP_SMS_VERSION') ? WP_SMS_VERSION : 'N/A';
        $settings['plugin_version'] = array(
            'label' => __('Plugin Version', 'wp-sms'),
            'value' => $ver === 'N/A' ? __('N/A', 'wp-sms') : $ver,
            'debug' => $ver === 'N/A' ? 'N/A' : $ver,
        );

        $dbv                    = get_option('wp_sms_db_version', 'Not Set');
        $settings['db_version'] = array(
            'label' => __('Database Version', 'wp-sms'),
            'value' => $dbv === 'Not Set' ? __('Not Set', 'wp-sms') : $dbv,
            'debug' => $dbv === 'Not Set' ? 'Not Set' : $dbv,
        );

        $mfsRaw                          = $raw('add_mobile_field', 'Not Set');
        $mfsVal                          = $mfsRaw === 'Not Set' ? __('Not Set', 'wp-sms') : $toCamelCase($mfsRaw);
        $mfsDbg                          = $mfsRaw === 'Not Set' ? 'Not Set' : $toCamelCase($mfsRaw);
        $settings['mobile_field_source'] = array(
            'label' => __('Mobile Number Field Source', 'wp-sms'),
            'value' => $mfsVal,
            'debug' => $mfsDbg,
        );

        $mandatoryRaw                       = $raw('optional_mobile_field');
        $mandatoryVal                       = ($mandatoryRaw === '0') ? __('Required', 'wp-sms') : __('Optional', 'wp-sms');
        $mandatoryDbg                       = ($mandatoryRaw === '0') ? 'Required' : 'Optional';
        $settings['mobile_field_mandatory'] = array(
            'label' => __('Mobile Field Mandatory Status', 'wp-sms'),
            'value' => $mandatoryVal,
            'debug' => $mandatoryDbg,
        );

        $onlyCountriesRaw           = (array)$raw('international_mobile_only_countries');
        $onlyCountriesVal           = $onlyCountriesRaw ? implode(', ', $onlyCountriesRaw) : __('Not Set', 'wp-sms');
        $onlyCountriesDbg           = $onlyCountriesRaw ? implode(', ', $onlyCountriesRaw) : 'Not Set';
        $settings['only_countries'] = array(
            'label' => __('Only Countries', 'wp-sms'),
            'value' => $onlyCountriesVal,
            'debug' => $onlyCountriesDbg,
        );

        $prefCountriesRaw                = (array)$raw('international_mobile_preferred_countries');
        $prefCountriesVal                = $prefCountriesRaw ? implode(', ', $prefCountriesRaw) : __('Not Set', 'wp-sms');
        $prefCountriesDbg                = $prefCountriesRaw ? implode(', ', $prefCountriesRaw) : 'Not Set';
        $settings['preferred_countries'] = array(
            'label' => __('Preferred Countries', 'wp-sms'),
            'value' => $prefCountriesVal,
            'debug' => $prefCountriesDbg,
        );

        $gwNameRaw                = $raw('gateway_name', 'Not Configured');
        $gwNameVal                = $gwNameRaw === 'Not Configured' ? __('Not Configured', 'wp-sms') : $toCamelCase($gwNameRaw);
        $gwNameDbg                = $gwNameRaw === 'Not Configured' ? 'Not Configured' : $toCamelCase($gwNameRaw);
        $settings['gateway_name'] = array(
            'label' => __('SMS Gateway Name', 'wp-sms'),
            'value' => $gwNameVal,
            'debug' => $gwNameDbg,
        );

        $gwStatusRaw                = Gateway::status(true);
        $settings['gateway_status'] = array(
            'label' => __('SMS Gateway Status', 'wp-sms'),
            'value' => $gwStatusRaw ? __('Active', 'wp-sms') : __('Inactive', 'wp-sms'),
            'debug' => $gwStatusRaw ? 'Active' : 'Inactive',
        );

        $settings['incoming_message'] = array(
            'label' => __('SMS Gateway Incoming Message', 'wp-sms'),
            'value' => $yesNo($sms->supportIncoming),
            'debug' => $yesNoDebug($sms->supportIncoming),
        );

        $settings['send_bulk_sms'] = array(
            'label' => __('SMS Gateway Send Bulk SMS', 'wp-sms'),
            'value' => $yesNo($sms->bulk_send),
            'debug' => $yesNoDebug($sms->bulk_send),
        );

        $settings['send_mms'] = array(
            'label' => __('SMS Gateway Send MMS', 'wp-sms'),
            'value' => $yesNo($sms->supportMedia),
            'debug' => $yesNoDebug($sms->supportMedia),
        );

        $deliveryOptions = array(
            'api_direct_send' => esc_html__('Send SMS Instantly: Activates immediate dispatch of messages via API upon request.', 'wp-sms'),
            'api_async_send'  => esc_html__('Scheduled SMS Delivery: Configures API to send messages at predetermined times.', 'wp-sms'),
            'api_queued_send' => esc_html__('Batch SMS Queue: Lines up messages for grouped sending, enhancing efficiency for bulk dispatch.', 'wp-sms'),
        );

        $deliveryOptionsDebug = array(
            'api_direct_send' => 'Send SMS Instantly: Activates immediate dispatch of messages via API upon request.',
            'api_async_send'  => 'Scheduled SMS Delivery: Configures API to send messages at predetermined times.',
            'api_queued_send' => 'Batch SMS Queue: Lines up messages for grouped sending, enhancing efficiency for bulk dispatch.',
        );

        $deliveryKey   = $raw('sms_delivery_method', 'not_set');
        $deliveryLabel = $deliveryOptions[$deliveryKey] ?? __('Not Set', 'wp-sms');
        $deliveryDebug = $deliveryOptionsDebug[$deliveryKey] ?? 'Not Set';

        $settings['delivery_method'] = array(
            'label' => __('SMS Gateway Delivery Method', 'wp-sms'),
            'value' => $deliveryLabel,
            'debug' => $deliveryDebug,
        );

        $sendUnicodeRaw                = $raw('send_unicode');
        $settings['unicode_messaging'] = array(
            'label' => __('SMS Gateway Unicode Messaging', 'wp-sms'),
            'value' => $yesNo($sendUnicodeRaw),
            'debug' => $yesNoDebug($sendUnicodeRaw),
        );

        $cleanNumbersRaw               = $raw('clean_numbers');
        $settings['number_formatting'] = array(
            'label' => __('SMS Gateway Number Formatting', 'wp-sms'),
            'value' => $yesNo($cleanNumbersRaw),
            'debug' => $yesNoDebug($cleanNumbersRaw),
        );

        $restrictLocal    = $raw('send_only_local_numbers');
        $allowedCountries = (array)$raw('only_local_numbers_countries');

        $restrictTextVal = $yesNo($restrictLocal);
        $restrictTextDbg = $yesNoDebug($restrictLocal);
        if ($restrictLocal && !empty($allowedCountries) && $allowedCountries[0]) {
            $restrictTextVal .= ' — ' . implode(', ', $allowedCountries);
            $restrictTextDbg .= ' — ' . implode(', ', $allowedCountries);
        }

        $settings['restrict_to_local'] = array(
            'label' => __('SMS Gateway Restrict to Local Numbers', 'wp-sms'),
            'value' => $restrictTextVal,
            'debug' => $restrictTextDbg,
        );


        $newsletterGroupsRaw          = $raw('newsletter_form_groups');
        $settings['group_visibility'] = array(
            'label' => __('SMS Newsletter Group Visibility in Form', 'wp-sms'),
            'value' => $yesNo($newsletterGroupsRaw),
            'debug' => $yesNoDebug($newsletterGroupsRaw),
        );

        $newsletterMultipleRaw       = $raw('newsletter_form_multiple_select');
        $settings['group_selection'] = array(
            'label' => __('SMS Newsletter Group Selection', 'wp-sms'),
            'value' => $yesNo($newsletterMultipleRaw),
            'debug' => $yesNoDebug($newsletterMultipleRaw),
        );

        $newsletterVerifyRaw                   = $raw('newsletter_form_verify');
        $settings['subscription_confirmation'] = array(
            'label' => __('SMS Newsletter Subscription Confirmation', 'wp-sms'),
            'value' => $yesNo($newsletterVerifyRaw),
            'debug' => $yesNoDebug($newsletterVerifyRaw),
        );

        $chatboxBtnRaw              = $raw('chatbox_message_button');
        $settings['message_button'] = array(
            'label' => __('Message Button Status', 'wp-sms'),
            'value' => $yesNo($chatboxBtnRaw),
            'debug' => $yesNoDebug($chatboxBtnRaw),
        );

        $reportStatsRaw                  = $raw('report_wpsms_statistics');
        $settings['performance_reports'] = array(
            'label' => __('SMS Performance Reports', 'wp-sms'),
            'value' => $yesNo($reportStatsRaw),
            'debug' => $yesNoDebug($reportStatsRaw),
        );

        $shortUrlRaw              = $raw('short_url_status');
        $settings['shorten_urls'] = array(
            'label' => __('Shorten URLs', 'wp-sms'),
            'value' => $yesNo($shortUrlRaw),
            'debug' => $yesNoDebug($shortUrlRaw),
        );

        $recaptchaRaw          = $raw('g_recaptcha_status');
        $settings['recaptcha'] = array(
            'label' => __('Google reCAPTCHA Integration', 'wp-sms'),
            'value' => $yesNo($recaptchaRaw),
            'debug' => $yesNoDebug($recaptchaRaw),
        );

        $loginSmsRaw                = WPSmsOptionsManager::getOption('login_sms', \true);
        $settings['login_with_sms'] = array(
            'label' => __('Login With SMS', 'wp-sms'),
            'value' => $yesNo($loginSmsRaw),
            'debug' => $yesNoDebug($loginSmsRaw),
        );

        $twoFactorRaw           = WPSmsOptionsManager::getOption('mobile_verify', \true);
        $settings['two_factor'] = array(
            'label' => __('Two-Factor Authentication with SMS', 'wp-sms'),
            'value' => $yesNo($twoFactorRaw),
            'debug' => $yesNoDebug($twoFactorRaw),
        );

        $autoRegisterRaw                    = WPSmsOptionsManager::getOption('register_sms', \true);
        $settings['auto_register_on_login'] = array(
            'label' => __('Create User on SMS Login', 'wp-sms'),
            'value' => $yesNo($autoRegisterRaw),
            'debug' => $yesNoDebug($autoRegisterRaw),
        );

        $cf7MetaboxRaw           = $raw('cf7_metabox');
        $settings['cf7_metabox'] = array(
            'label' => __('Contact Form 7 Metabox', 'wp-sms'),
            'value' => $yesNo($cf7MetaboxRaw),
            'debug' => $yesNoDebug($cf7MetaboxRaw),
        );

        return array_merge($settings, $this->getIntegrationSettings());

    }

    protected function getIntegrationSettings()
    {
        $pluginHandler = new PluginHandler();
        $yesNo         = function ($val) {
            return in_array($val, [true, '1', 1, 'yes'], true) ? __('Enabled', 'wp-sms') : __('Disabled', 'wp-sms');
        };
        $yesNoDebug    = function ($val) {
            return in_array($val, [true, '1', 1, 'yes'], true) ? 'Enabled' : 'Disabled';
        };
        $settings      = [];

        // Gravity Forms
        if (class_exists('RGFormsModel') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['gravityforms_integration'] = [
                'label' => __('Gravity Forms Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];
        }

        // Quform
        if (class_exists('Quform_Repository') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['quform_integration'] = [
                'label' => __('Quform Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];
        }

        // EDD
        if (class_exists('Easy_Digital_Downloads') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['edd_integration'] = [
                'label' => __('EDD Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $eddMobileFieldRaw            = WPSmsOptionsManager::getOption('edd_mobile_field', true);
            $settings['edd_mobile_field'] = [
                'label' => __('EDD Mobile Field', 'wp-sms'),
                'value' => $yesNo($eddMobileFieldRaw),
                'debug' => $yesNoDebug($eddMobileFieldRaw),
            ];

            $eddNotifyOrderRaw            = WPSmsOptionsManager::getOption('edd_notify_order_enable', true);
            $settings['edd_notify_order'] = [
                'label' => __('EDD New Order Notifications', 'wp-sms'),
                'value' => $yesNo($eddNotifyOrderRaw),
                'debug' => $yesNoDebug($eddNotifyOrderRaw),
            ];

            $eddNotifyCustomerRaw            = WPSmsOptionsManager::getOption('edd_notify_customer_enable', true);
            $settings['edd_notify_customer'] = [
                'label' => __('EDD Customer Notifications', 'wp-sms'),
                'value' => $yesNo($eddNotifyCustomerRaw),
                'debug' => $yesNoDebug($eddNotifyCustomerRaw),
            ];
        }

        // Job Manager
        if (class_exists('WP_Job_Manager') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['job_manager_integration'] = [
                'label' => __('WP Job Manager Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $jobMobileFieldRaw            = WPSmsOptionsManager::getOption('job_mobile_field', true);
            $settings['job_mobile_field'] = [
                'label' => __('Job Mobile Field', 'wp-sms'),
                'value' => $yesNo($jobMobileFieldRaw),
                'debug' => $yesNoDebug($jobMobileFieldRaw),
            ];

            $jobDisplayMobileRaw            = WPSmsOptionsManager::getOption('job_display_mobile_number', true);
            $settings['job_display_mobile'] = [
                'label' => __('Display Mobile Number', 'wp-sms'),
                'value' => $yesNo($jobDisplayMobileRaw),
                'debug' => $yesNoDebug($jobDisplayMobileRaw),
            ];

            $jobNotifyStatusRaw                   = WPSmsOptionsManager::getOption('job_notify_status', true);
            $settings['job_new_job_notification'] = [
                'label' => __('New Job Notification', 'wp-sms'),
                'value' => $yesNo($jobNotifyStatusRaw),
                'debug' => $yesNoDebug($jobNotifyStatusRaw),
            ];

            $jobNotifyReceiverRaw                  = WPSmsOptionsManager::getOption('job_notify_receiver', true);
            $settings['job_notification_receiver'] = [
                'label' => __('Job Notification Receiver', 'wp-sms'),
                'value' => $jobNotifyReceiverRaw,
                'debug' => $jobNotifyReceiverRaw,
            ];

            $jobEmployerNotifRaw                   = WPSmsOptionsManager::getOption('job_notify_employer_status', true);
            $settings['job_employer_notification'] = [
                'label' => __('Employer Notification', 'wp-sms'),
                'value' => $yesNo($jobEmployerNotifRaw),
                'debug' => $yesNoDebug($jobEmployerNotifRaw),
            ];
        }

        // Awesome Support
        if (class_exists('Awesome_Support') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['awesome_support_integration'] = [
                'label' => __('Awesome Support Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $asOpenRaw                              = WPSmsOptionsManager::getOption('as_notify_open_ticket_status', true);
            $settings['as_new_ticket_notification'] = [
                'label' => __('New Ticket Notification', 'wp-sms'),
                'value' => $yesNo($asOpenRaw),
                'debug' => $yesNoDebug($asOpenRaw),
            ];

            $asAdminReplyRaw                         = WPSmsOptionsManager::getOption('as_notify_admin_reply_ticket_status', true);
            $settings['as_admin_reply_notification'] = [
                'label' => __('Admin Reply Notification', 'wp-sms'),
                'value' => $yesNo($asAdminReplyRaw),
                'debug' => $yesNoDebug($asAdminReplyRaw),
            ];

            $asUserReplyRaw                         = WPSmsOptionsManager::getOption('as_notify_user_reply_ticket_status', true);
            $settings['as_user_reply_notification'] = [
                'label' => __('User Reply Notification', 'wp-sms'),
                'value' => $yesNo($asUserReplyRaw),
                'debug' => $yesNoDebug($asUserReplyRaw),
            ];

            $asUpdateRaw                               = WPSmsOptionsManager::getOption('as_notify_update_ticket_status', true);
            $settings['as_status_update_notification'] = [
                'label' => __('Status Update Notification', 'wp-sms'),
                'value' => $yesNo($asUpdateRaw),
                'debug' => $yesNoDebug($asUpdateRaw),
            ];

            $asCloseRaw                               = WPSmsOptionsManager::getOption('as_notify_close_ticket_status', true);
            $settings['as_ticket_close_notification'] = [
                'label' => __('Ticket Close Notification', 'wp-sms'),
                'value' => $yesNo($asCloseRaw),
                'debug' => $yesNoDebug($asCloseRaw),
            ];
        }

        // Ultimate Member
        if (function_exists('um_user') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['ultimate_member_integration'] = [
                'label' => __('Ultimate Member Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $umApprovalRaw                        = WPSmsOptionsManager::getOption('um_send_sms_after_approval', true);
            $settings['um_approval_notification'] = [
                'label' => __('Ultimate Member User Approval Notification', 'wp-sms'),
                'value' => $yesNo($umApprovalRaw),
                'debug' => $yesNoDebug($umApprovalRaw),
            ];
        }

        // Formidable Forms
        if (function_exists('is_plugin_active') && is_plugin_active('formidable/formidable.php')) {
            $settings['formidable_integration'] = [
                'label' => __('Formidable Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $formidableMetaboxRaw           = WPSmsOptionsManager::getOption('formidable_metabox');
            $settings['formidable_metabox'] = [
                'label' => __('Formidable Metabox', 'wp-sms'),
                'value' => $yesNo($formidableMetaboxRaw),
                'debug' => $yesNoDebug($formidableMetaboxRaw),
            ];
        }

        // Forminator
        if (class_exists('Forminator') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['forminator_integration'] = [
                'label' => __('Forminator Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];
        }

        // BuddyPress
        if (function_exists('buddypress') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['buddypress_integration'] = [
                'label' => __('BuddyPress Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $bpWelcomeRaw               = WPSmsOptionsManager::getOption('bp_welcome_notification_enable', true);
            $settings['bp_welcome_sms'] = [
                'label' => __('BuddyPress Welcome SMS', 'wp-sms'),
                'value' => $yesNo($bpWelcomeRaw),
                'debug' => $yesNoDebug($bpWelcomeRaw),
            ];

            $bpMentionRaw                        = WPSmsOptionsManager::getOption('bp_mention_enable', true);
            $settings['bp_mention_notification'] = [
                'label' => __('BuddyPress Mention Alerts', 'wp-sms'),
                'value' => $yesNo($bpMentionRaw),
                'debug' => $yesNoDebug($bpMentionRaw),
            ];

            $bpPmRaw                                     = WPSmsOptionsManager::getOption('bp_private_message_enable', true);
            $settings['bp_private_message_notification'] = [
                'label' => __('BuddyPress Private Messages', 'wp-sms'),
                'value' => $yesNo($bpPmRaw),
                'debug' => $yesNoDebug($bpPmRaw),
            ];

            $bpActReplyRaw                              = WPSmsOptionsManager::getOption('bp_comments_activity_enable', true);
            $settings['bp_activity_reply_notification'] = [
                'label' => __('BuddyPress Activity Replies', 'wp-sms'),
                'value' => $yesNo($bpActReplyRaw),
                'debug' => $yesNoDebug($bpActReplyRaw),
            ];

            $bpCmtReplyRaw                             = WPSmsOptionsManager::getOption('bp_comments_reply_enable', true);
            $settings['bp_comment_reply_notification'] = [
                'label' => __('BuddyPress Comment Replies', 'wp-sms'),
                'value' => $yesNo($bpCmtReplyRaw),
                'debug' => $yesNoDebug($bpCmtReplyRaw),
            ];
        }

        // WooCommerce
        if (class_exists('WooCommerce') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['woocommerce_integration'] = [
                'label' => __('WooCommerce Integration', 'wp-sms'),
                'value' => __('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $wcMetaBoxRaw            = WPSmsOptionsManager::getOption('wc_meta_box_enable', true);
            $settings['wc_meta_box'] = [
                'label' => __('WooCommerce Order Meta Box', 'wp-sms'),
                'value' => $yesNo($wcMetaBoxRaw),
                'debug' => $yesNoDebug($wcMetaBoxRaw),
            ];

            $wcNotifyProductRaw            = WPSmsOptionsManager::getOption('wc_notify_product_enable', true);
            $settings['wc_notify_product'] = [
                'label' => __('WooCommerce New Product Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyProductRaw),
                'debug' => $yesNoDebug($wcNotifyProductRaw),
            ];

            $wcNotifyOrderRaw            = WPSmsOptionsManager::getOption('wc_notify_order_enable', true);
            $settings['wc_notify_order'] = [
                'label' => __('WooCommerce New Order Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyOrderRaw),
                'debug' => $yesNoDebug($wcNotifyOrderRaw),
            ];

            $wcNotifyCustomerRaw            = WPSmsOptionsManager::getOption('wc_notify_customer_enable', true);
            $settings['wc_notify_customer'] = [
                'label' => __('WooCommerce Customer Order Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyCustomerRaw),
                'debug' => $yesNoDebug($wcNotifyCustomerRaw),
            ];

            $wcNotifyStockRaw            = WPSmsOptionsManager::getOption('wc_notify_stock_enable', true);
            $settings['wc_notify_stock'] = [
                'label' => __('WooCommerce Low Stock Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyStockRaw),
                'debug' => $yesNoDebug($wcNotifyStockRaw),
            ];

            $wcCheckoutConfirmRaw                 = WPSmsOptionsManager::getOption('wc_checkout_confirmation_checkbox_enabled', true);
            $settings['wc_checkout_confirmation'] = [
                'label' => __('WooCommerce Checkout Confirmation Checkbox', 'wp-sms'),
                'value' => $yesNo($wcCheckoutConfirmRaw),
                'debug' => $yesNoDebug($wcCheckoutConfirmRaw),
            ];

            $wcNotifyStatusRaw            = WPSmsOptionsManager::getOption('wc_notify_status_enable', true);
            $settings['wc_notify_status'] = [
                'label' => __('WooCommerce Order Status Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyStatusRaw),
                'debug' => $yesNoDebug($wcNotifyStatusRaw),
            ];

            $wcNotifyByStatusRaw             = WPSmsOptionsManager::getOption('wc_notify_by_status_enable', true);
            $settings['wc_notify_by_status'] = [
                'label' => __('WooCommerce Specific Order Status Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyByStatusRaw),
                'debug' => $yesNoDebug($wcNotifyByStatusRaw),
            ];

            $statusContent                      = WPSmsOptionsManager::getOption('wc_notify_by_status_content', true);
            $statusCount                        = is_array($statusContent) ? count($statusContent) : 0;
            $settings['wc_notify_status_count'] = [
                'label' => __('WooCommerce Configured Status Notifications', 'wp-sms'),
                'value' => $statusCount,
                'debug' => (string)$statusCount,
            ];
        }

        // WooCommerce Pro
        if ($pluginHandler->isPluginActive('wp-sms-woocommerce-pro')) {
            $settings = array_merge($settings, $this->getWooProSettings());
        }


        if ($pluginHandler->isPluginActive('wp-sms-two-way')) {
            $twoWay   = self::getTwoWayIntegrationSetting()['two_way'] ?? [];
            $settings = array_merge($settings, $twoWay);
        }

        if ($pluginHandler->isPluginActive('wp-sms-fluent-integrations')) {
            $fluent   = self::getFluentIntegrationSetting() ?? [];
            $settings = array_merge($settings, $fluent);
        }

        if ($pluginHandler->isPluginActive('wp-sms-membership-integrations')) {
            $membership = self::getMembershipsIntegrationSetting();
            $settings   = array_merge($settings, $membership);
        }

        if ($pluginHandler->isPluginActive('wp-sms-booking-integrations')) {
            $booking  = self::getBookingIntegrationSetting();
            $settings = array_merge($settings, $booking);
        }

        return $settings;
    }

    private function getWooProSettings()
    {
        $woo = function ($key, $default = null) {
            return \WPSmsWooPro\Core\Helper::getOption($key, $default);
        };

        $wooProFields = array(
            'cart_abandonment_recovery_status'                    => __('WooPro: Cart Abandonment Recovery', 'wp-sms'),
            'cart_abandonment_threshold'                          => __('WooPro: Cart abandonment threshold', 'wp-sms'),
            'cart_overwrite_number_during_checkout'               => __('WooPro: Cart abandonment Overwrite mobile number', 'wp-sms'),
            'cart_create_coupon'                                  => __('WooPro: Cart abandonment Create coupon', 'wp-sms'),
            'cart_abandonment_send_sms_time_interval'             => __('WooPro: Cart abandonment Send sms after', 'wp-sms'),
            'login_with_sms_status'                               => __('WooPro: Show Button in Login Page', 'wp-sms'),
            'login_with_sms_forgot_status'                        => __('WooPro: Show Button in Forgot Password Page', 'wp-sms'),
            'reset_password_status'                               => __('WooPro: Enable SMS Password Reset', 'wp-sms'),
            'checkout_confirmation_checkbox_enabled'              => __('WooPro: Confirmation Checkbox', 'wp-sms'),
            'checkout_mobile_verification_enabled'                => __('WooPro: Enable Mobile Verification', 'wp-sms'),
            'register_user_via_sms_status'                        => __('WooPro: Automatic Registration via SMS', 'wp-sms'),
            'checkout_mobile_verification_skip_logged_in_enabled' => __('WooPro: Skip Verification for Logged-In Users', 'wp-sms'),
            'checkout_mobile_verification_countries_whitelist'    => __('WooPro: Required Countries for Mobile Verification', 'wp-sms'),
        );

        foreach ($wooProFields as $key => $label) {
            $raw   = $woo($key);
            $value = '';
            $debug = '';

            if ($key === 'register_user_via_sms_status' || $key === 'checkout_mobile_verification_skip_logged_in_enabled') {
                $value = in_array($raw, array(true, '1', 1, 'yes'), true)
                    ? __('Enabled', 'wp-sms')
                    : __('Disabled', 'wp-sms');
                $debug = in_array($raw, array(true, '1', 1, 'yes'), true) ? 'Enabled' : 'Disabled';

            } elseif ($key === 'checkout_mobile_verification_countries_whitelist') {
                $value = is_array($raw) && !empty($raw)
                    ? implode(', ', $raw)
                    : __('Not Set', 'wp-sms');
                $debug = is_array($raw) && !empty($raw)
                    ? implode(', ', $raw)
                    : 'Not Set';

            } else if ($key === 'cart_overwrite_number_during_checkout') {
                $value = ($raw === 'skip')
                    ? __('Do not update', 'wp-sms')
                    : __('Update phone number', 'wp-sms');
                $debug = ($raw === 'skip') ? 'Do not update' : 'Update phone number';

            } elseif (in_array($key, array('cart_abandonment_threshold', 'cart_abandonment_send_sms_time_interval'), true)) {
                if (is_array($raw)) {
                    $partsVal = array();
                    $partsDbg = array();
                    if (!empty($raw['days']) && $raw['days'] !== '0') {
                        $partsVal[] = $raw['days'] . ' ' . _n('day', 'days', (int)$raw['days'], 'wp-sms');
                        $partsDbg[] = $raw['days'] . ' ' . ((int)$raw['days'] === 1 ? 'day' : 'days');
                    }
                    if (!empty($raw['hours']) && $raw['hours'] !== '0') {
                        $partsVal[] = $raw['hours'] . ' ' . _n('hour', 'hours', (int)$raw['hours'], 'wp-sms');
                        $partsDbg[] = $raw['hours'] . ' ' . ((int)$raw['hours'] === 1 ? 'hour' : 'hours');
                    }
                    if (!empty($raw['minutes']) && $raw['minutes'] !== '0') {
                        $partsVal[] = $raw['minutes'] . ' ' . _n('minute', 'minutes', (int)$raw['minutes'], 'wp-sms');
                        $partsDbg[] = $raw['minutes'] . ' ' . ((int)$raw['minutes'] === 1 ? 'minute' : 'minutes');
                    }
                    $value = $partsVal ? implode(', ', $partsVal) : __('Not Set', 'wp-sms');
                    $debug = $partsDbg ? implode(', ', $partsDbg) : 'Not Set';
                } else {
                    $value = __('Not Set', 'wp-sms');
                    $debug = 'Not Set';
                }

            } elseif (in_array($raw, array(true, '1', 1, 'yes'), true)) {
                $value = __('Enabled', 'wp-sms');
                $debug = 'Enabled';

            } elseif (in_array($raw, array(false, '0', 0, 'no'), true)) {
                $value = __('Disabled', 'wp-sms');
                $debug = 'Disabled';

            } elseif ($raw === null || $raw === '') {
                $value = __('Not Set', 'wp-sms');
                $debug = 'Not Set';

            } else {
                $value = (string)$raw;
                $debug = (string)$raw;
            }

            $settings['woo_' . $key] = array(
                'label' => $label,
                'value' => $value,
                'debug' => $debug,
            );
        }

        return $settings;
    }

    /**
     * Retrieves anonymized settings for supported booking integrations in structured format.
     *
     * @return array
     */
    public static function getBookingIntegrationSetting()
    {
        $settings = [];

        $options = array(
            // === Booking Calendar ===
            'booking_calendar_notif_admin_new_booking'              => __('Booking Calendar: Admin New Booking Notification', 'wp-sms'),
            'booking_calendar_notif_customer_new_booking'           => __('Booking Calendar: Customer New Booking Notification', 'wp-sms'),
            'booking_calendar_notif_customer_booking_approved'      => __('Booking Calendar: Booking Approved Notification', 'wp-sms'),
            'booking_calendar_notif_customer_booking_cancelled'     => __('Booking Calendar: Booking Cancelled Notification', 'wp-sms'),

            // === BookingPress ===
            'bookingpress_notif_admin_approved_appointment'         => __('BookingPress: Admin Approved Appointment', 'wp-sms'),
            'bookingpress_notif_customer_approved_appointment'      => __('BookingPress: Customer Approved Appointment', 'wp-sms'),
            'bookingpress_notif_admin_pending_appointment'          => __('BookingPress: Admin Pending Appointment', 'wp-sms'),
            'bookingpress_notif_customer_pending_appointment'       => __('BookingPress: Customer Pending Appointment', 'wp-sms'),
            'bookingpress_notif_admin_rejected_appointment'         => __('BookingPress: Admin Rejected Appointment', 'wp-sms'),
            'bookingpress_notif_customer_rejected_appointment'      => __('BookingPress: Customer Rejected Appointment', 'wp-sms'),
            'bookingpress_notif_admin_cancelled_appointment'        => __('BookingPress: Admin Cancelled Appointment', 'wp-sms'),
            'bookingpress_notif_customer_cancelled_appointment'     => __('BookingPress: Customer Cancelled Appointment', 'wp-sms'),

            // === Woo Appointments ===
            'woo_appointments_notif_admin_new_appointment'          => __('Woo Appointments: Admin New Appointment', 'wp-sms'),
            'woo_appointments_notif_admin_cancelled_appointment'    => __('Woo Appointments: Admin Cancelled Appointment', 'wp-sms'),
            'woo_appointments_notif_customer_cancelled_appointment' => __('Woo Appointments: Customer Cancelled Appointment', 'wp-sms'),
            'woo_appointments_notif_admin_rescheduled_appointment'  => __('Woo Appointments: Admin Rescheduled Appointment', 'wp-sms'),
            'woo_appointments_notif_customer_confirmed_appointment' => __('Woo Appointments: Customer Confirmed Appointment', 'wp-sms'),

            // === Woo Bookings ===
            'woo_bookings_notif_admin_new_booking'                  => __('Woo Bookings: Admin New Booking', 'wp-sms'),
            'woo_bookings_notif_admin_cancelled_booking'            => __('Woo Bookings: Admin Cancelled Booking', 'wp-sms'),
            'woo_bookings_notif_customer_cancelled_booking'         => __('Woo Bookings: Customer Cancelled Booking', 'wp-sms'),
            'woo_bookings_notif_customer_confirmed_booking'         => __('Woo Bookings: Customer Confirmed Booking', 'wp-sms'),
        );

        foreach ($options as $key => $label) {
            $raw      = OptionUtil::get($key);
            $isToggle = (is_bool($raw) || $raw === '0' || $raw === '1');
            $value    = $isToggle
                ? ($raw ? __('Enabled', 'wp-sms') : __('Disabled', 'wp-sms'))
                : (is_string($raw) ? $raw : __('Not Set', 'wp-sms'));
            $debug    = $isToggle
                ? ($raw ? 'Enabled' : 'Disabled')
                : (is_string($raw) ? $raw : 'Not Set');

            $settings[] = [
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $debug,
            ];
        }

        return $settings;
    }

    /**
     * Retrieves anonymized settings for FluentCRM integration in structured format.
     *
     * @return array
     */
    public static function getFluentIntegrationSetting()
    {
        $settings = [];

        $options = [
            // FluentCRM
            'fluent_crm_notif_contact_subscribed'    => __('FluentCRM: Contact Subscribed Notification', 'wp-sms'),
            'fluent_crm_notif_contact_unsubscribed'  => __('FluentCRM: Contact Unsubscribed Notification', 'wp-sms'),
            'fluent_crm_notif_contact_pending'       => __('FluentCRM: Contact Pending Notification', 'wp-sms'),

            // Fluent Support
            'fluent_support_notif_ticket_created'    => __('Fluent Support: Ticket Created', 'wp-sms-fluent-integrations'),
            'fluent_support_notif_customer_response' => __('Fluent Support: Customer Response', 'wp-sms-fluent-integrations'),
            'fluent_support_notif_agent_assigned'    => __('Fluent Support: Agent Assigned', 'wp-sms-fluent-integrations'),
            'fluent_support_notif_ticket_closed'     => __('Fluent Support: Ticket Closed', 'wp-sms-fluent-integrations'),
        ];

        foreach ($options as $key => $label) {
            $raw      = \WP_SMS\Utils\OptionUtil::get($key);
            $isToggle = (is_bool($raw) || $raw === '0' || $raw === '1');
            $value    = $isToggle
                ? ($raw ? __('Enabled', 'wp-sms') : __('Disabled', 'wp-sms'))
                : (is_string($raw) ? $raw : __('Not Set', 'wp-sms'));
            $debug    = $isToggle
                ? ($raw ? 'Enabled' : 'Disabled')
                : (is_string($raw) ? $raw : 'Not Set');

            $settings[] = [
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $debug,
            ];
        }

        return $settings;
    }

    /**
     * Retrieves settings for Memberships integration in structured format.
     *
     * @return array
     */
    public static function getMembershipsIntegrationSetting()
    {
        $settings = [];

        $options = [
            // Paid Memberships Pro
            'pmpro_notif_user_registered'       => __('Paid Memberships Pro: User Registered Notification', 'wp-sms'),
            'pmpro_notif_membership_confirmed'  => __('Paid Memberships Pro: Membership Confirmed Notification', 'wp-sms'),
            'pmpro_notif_membership_cancelled'  => __('Paid Memberships Pro: Membership Cancelled Notification', 'wp-sms'),
            'pmpro_notif_membership_expired'    => __('Paid Memberships Pro: Membership Expired Notification', 'wp-sms'),

            // Simple Membership
            'sm_notif_admin_user_registered'    => __('Simple Membership: Admin Notified on User Registration', 'wp-sms'),
            'sm_notif_membership_level_updated' => __('Simple Membership: Membership Level Updated', 'wp-sms'),
            'sm_notif_membership_expired'       => __('Simple Membership: Membership Expired', 'wp-sms'),
            'sm_notif_membership_cancelled'     => __('Simple Membership: Membership Cancelled', 'wp-sms'),
            'sm_notif_admin_payment_recieved'   => __('Simple Membership: Payment Received (Admin)', 'wp-sms'),
        ];

        foreach ($options as $key => $label) {
            $raw      = \WP_SMS\Utils\OptionUtil::get($key);
            $isToggle = (is_bool($raw) || $raw === '0' || $raw === '1');
            $value    = $isToggle
                ? ($raw ? __('Enabled', 'wp-sms') : __('Disabled', 'wp-sms'))
                : (is_string($raw) ? $raw : __('Not Set', 'wp-sms'));
            $debug    = $isToggle
                ? ($raw ? 'Enabled' : 'Disabled')
                : (is_string($raw) ? $raw : 'Not Set');

            $settings[] = [
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $debug,
            ];
        }

        return $settings;
    }

    /**
     * Retrieves settings for Two-Way integration in structured format.
     *
     * @return array
     */
    public static function getTwoWayIntegrationSetting()
    {
        $settings = [];

        $options = [
            'notif_new_inbox_message' => __('Two-Way: Forward Incoming SMS to Admin', 'wp-sms'),
            'email_new_inbox_message' => __('Two-Way: Forward Incoming SMS to Email', 'wp-sms'),
        ];

        foreach ($options as $key => $label) {
            $raw      = \WP_SMS\Utils\OptionUtil::get($key);
            $isToggle = (is_bool($raw) || $raw === '0' || $raw === '1');
            $value    = $isToggle
                ? ($raw ? __('Enabled', 'wp-sms') : __('Disabled', 'wp-sms'))
                : (is_string($raw) ? $raw : __('Not Set', 'wp-sms'));
            $debug    = $isToggle
                ? ($raw ? 'Enabled' : 'Disabled')
                : (is_string($raw) ? $raw : 'Not Set');

            $settings[] = [
                'label' => esc_html($label),
                'value' => $value,
                'debug' => $debug,
            ];
        }

        return $settings;
    }
}