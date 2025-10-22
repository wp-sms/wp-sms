<?php

namespace WP_SMS\Admin;

use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\Gateway;
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
            return $value ? esc_html__('Enabled', 'wp-sms') : esc_html__('Disabled', 'wp-sms');
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
            'label' => esc_html__('Plugin Version', 'wp-sms'),
            'value' => $ver === 'N/A' ? esc_html__('N/A', 'wp-sms') : $ver,
            'debug' => $ver === 'N/A' ? 'N/A' : $ver,
        );

        $dbv                    = get_option('wp_sms_db_version', 'Not Set');
        $settings['db_version'] = array(
            'label' => esc_html__('Database Version', 'wp-sms'),
            'value' => $dbv === 'Not Set' ? esc_html__('Not Set', 'wp-sms') : $dbv,
            'debug' => $dbv === 'Not Set' ? 'Not Set' : $dbv,
        );

        $mfsRaw                          = $raw('add_mobile_field', 'Not Set');
        $mfsVal                          = $mfsRaw === 'Not Set' ? esc_html__('Not Set', 'wp-sms') : $toCamelCase($mfsRaw);
        $mfsDbg                          = $mfsRaw === 'Not Set' ? 'Not Set' : $toCamelCase($mfsRaw);
        $settings['mobile_field_source'] = array(
            'label' => esc_html__('Mobile Number Field Source', 'wp-sms'),
            'value' => $mfsVal,
            'debug' => $mfsDbg,
        );

        $mandatoryRaw                       = $raw('optional_mobile_field');
        $mandatoryVal                       = ($mandatoryRaw === '0') ? esc_html__('Required', 'wp-sms') : esc_html__('Optional', 'wp-sms');
        $mandatoryDbg                       = ($mandatoryRaw === '0') ? 'Required' : 'Optional';
        $settings['mobile_field_mandatory'] = array(
            'label' => esc_html__('Mobile Field Mandatory Status', 'wp-sms'),
            'value' => $mandatoryVal,
            'debug' => $mandatoryDbg,
        );

        $onlyCountriesRaw           = array_filter((array)$raw('international_mobile_only_countries'));
        $onlyCountriesVal           = !empty($onlyCountriesRaw) ? implode(', ', $onlyCountriesRaw) : esc_html__('Not Set', 'wp-sms');
        $onlyCountriesDbg           = !empty($onlyCountriesRaw) ? implode(', ', $onlyCountriesRaw) : 'Not Set';
        $settings['only_countries'] = array(
            'label' => esc_html__('Only Countries', 'wp-sms'),
            'value' => $onlyCountriesVal,
            'debug' => $onlyCountriesDbg,
        );

        $prefCountriesRaw                = array_filter((array)$raw('international_mobile_preferred_countries'));
        $prefCountriesVal                = !empty($prefCountriesRaw) ? implode(', ', $prefCountriesRaw) : esc_html__('Not Set', 'wp-sms');
        $prefCountriesDbg                = !empty($prefCountriesRaw) ? implode(', ', $prefCountriesRaw) : 'Not Set';
        $settings['preferred_countries'] = array(
            'label' => esc_html__('Preferred Countries', 'wp-sms'),
            'value' => $prefCountriesVal,
            'debug' => $prefCountriesDbg,
        );

        $gwNameRaw                = $raw('gateway_name', 'Not Configured');
        $gwNameVal                = $gwNameRaw === 'Not Configured' ? esc_html__('Not Configured', 'wp-sms') : $toCamelCase($gwNameRaw);
        $gwNameDbg                = $gwNameRaw === 'Not Configured' ? 'Not Configured' : $toCamelCase($gwNameRaw);
        $settings['gateway_name'] = array(
            'label' => esc_html__('SMS Gateway Name', 'wp-sms'),
            'value' => $gwNameVal,
            'debug' => $gwNameDbg,
        );

        $gwStatusRaw                = Gateway::status(true);
        $settings['gateway_status'] = array(
            'label' => esc_html__('SMS Gateway Status', 'wp-sms'),
            'value' => $gwStatusRaw ? esc_html__('Active', 'wp-sms') : esc_html__('Inactive', 'wp-sms'),
            'debug' => $gwStatusRaw ? 'Active' : 'Inactive',
        );

        $settings['incoming_message'] = array(
            'label' => esc_html__('SMS Gateway Incoming Message', 'wp-sms'),
            'value' => $yesNo($sms->supportIncoming),
            'debug' => $yesNoDebug($sms->supportIncoming),
        );

        $settings['send_bulk_sms'] = array(
            'label' => esc_html__('SMS Gateway Send Bulk SMS', 'wp-sms'),
            'value' => $yesNo($sms->bulk_send),
            'debug' => $yesNoDebug($sms->bulk_send),
        );

        $settings['send_mms'] = array(
            'label' => esc_html__('SMS Gateway Send MMS', 'wp-sms'),
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
        $deliveryLabel = $deliveryOptions[$deliveryKey] ?? esc_html__('Not Set', 'wp-sms');
        $deliveryDebug = $deliveryOptionsDebug[$deliveryKey] ?? 'Not Set';

        $settings['delivery_method'] = array(
            'label' => esc_html__('SMS Gateway Delivery Method', 'wp-sms'),
            'value' => $deliveryLabel,
            'debug' => $deliveryDebug,
        );

        $sendUnicodeRaw                = $raw('send_unicode');
        $settings['unicode_messaging'] = array(
            'label' => esc_html__('SMS Gateway Unicode Messaging', 'wp-sms'),
            'value' => $yesNo($sendUnicodeRaw),
            'debug' => $yesNoDebug($sendUnicodeRaw),
        );

        $cleanNumbersRaw               = $raw('clean_numbers');
        $settings['number_formatting'] = array(
            'label' => esc_html__('SMS Gateway Number Formatting', 'wp-sms'),
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
            'label' => esc_html__('SMS Gateway Restrict to Local Numbers', 'wp-sms'),
            'value' => $restrictTextVal,
            'debug' => $restrictTextDbg,
        );


        $newsletterGroupsRaw          = $raw('newsletter_form_groups');
        $settings['group_visibility'] = array(
            'label' => esc_html__('SMS Newsletter Group Visibility in Form', 'wp-sms'),
            'value' => $yesNo($newsletterGroupsRaw),
            'debug' => $yesNoDebug($newsletterGroupsRaw),
        );

        $newsletterMultipleRaw       = $raw('newsletter_form_multiple_select');
        $settings['group_selection'] = array(
            'label' => esc_html__('SMS Newsletter Group Selection', 'wp-sms'),
            'value' => $yesNo($newsletterMultipleRaw),
            'debug' => $yesNoDebug($newsletterMultipleRaw),
        );

        $newsletterVerifyRaw                   = $raw('newsletter_form_verify');
        $settings['subscription_confirmation'] = array(
            'label' => esc_html__('SMS Newsletter Subscription Confirmation', 'wp-sms'),
            'value' => $yesNo($newsletterVerifyRaw),
            'debug' => $yesNoDebug($newsletterVerifyRaw),
        );

        $chatboxBtnRaw              = $raw('chatbox_message_button');
        $settings['message_button'] = array(
            'label' => esc_html__('Message Button Status', 'wp-sms'),
            'value' => $yesNo($chatboxBtnRaw),
            'debug' => $yesNoDebug($chatboxBtnRaw),
        );

        $reportStatsRaw                  = $raw('report_wpsms_statistics');
        $settings['performance_reports'] = array(
            'label' => esc_html__('SMS Performance Reports', 'wp-sms'),
            'value' => $yesNo($reportStatsRaw),
            'debug' => $yesNoDebug($reportStatsRaw),
        );

        $shortUrlRaw              = $raw('short_url_status');
        $settings['shorten_urls'] = array(
            'label' => esc_html__('Shorten URLs', 'wp-sms'),
            'value' => $yesNo($shortUrlRaw),
            'debug' => $yesNoDebug($shortUrlRaw),
        );

        $recaptchaRaw          = $raw('g_recaptcha_status');
        $settings['recaptcha'] = array(
            'label' => esc_html__('Google reCAPTCHA Integration', 'wp-sms'),
            'value' => $yesNo($recaptchaRaw),
            'debug' => $yesNoDebug($recaptchaRaw),
        );

        $loginSmsRaw                = Option::getOption('login_sms', \true);
        $settings['login_with_sms'] = array(
            'label' => esc_html__('Login With SMS', 'wp-sms'),
            'value' => $yesNo($loginSmsRaw),
            'debug' => $yesNoDebug($loginSmsRaw),
        );

        $twoFactorRaw           = Option::getOption('mobile_verify', \true);
        $settings['two_factor'] = array(
            'label' => esc_html__('Two-Factor Authentication with SMS', 'wp-sms'),
            'value' => $yesNo($twoFactorRaw),
            'debug' => $yesNoDebug($twoFactorRaw),
        );

        $autoRegisterRaw                    = Option::getOption('register_sms', \true);
        $settings['auto_register_on_login'] = array(
            'label' => esc_html__('Create User on SMS Login', 'wp-sms'),
            'value' => $yesNo($autoRegisterRaw),
            'debug' => $yesNoDebug($autoRegisterRaw),
        );

        $cf7MetaboxRaw           = $raw('cf7_metabox');
        $settings['cf7_metabox'] = array(
            'label' => esc_html__('Contact Form 7 Metabox', 'wp-sms'),
            'value' => $yesNo($cf7MetaboxRaw),
            'debug' => $yesNoDebug($cf7MetaboxRaw),
        );

        return array_merge($settings, $this->getIntegrationSettings());

    }

    protected function getIntegrationSettings()
    {
        $pluginHandler = new PluginHandler();
        $yesNo         = function ($val) {
            return in_array($val, [true, '1', 1, 'yes'], true) ? esc_html__('Enabled', 'wp-sms') : esc_html__('Disabled', 'wp-sms');
        };
        $yesNoDebug    = function ($val) {
            return in_array($val, [true, '1', 1, 'yes'], true) ? 'Enabled' : 'Disabled';
        };
        $settings      = [];
        $options       = Option::getOptions(true);

        // Gravity Forms
        if (class_exists('RGFormsModel') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['gravityforms_integration'] = [
                'label' => esc_html__('Gravity Forms Integration', 'wp-sms'),
                'value' => esc_html__('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $gfForms   = \RGFormsModel::get_forms(null, 'title');
            $formsData = [];

            if (!empty($gfForms)) {
                foreach ($gfForms as $form) {
                    $formsData[(int)$form->id] = $form->title;
                }
            }

            $pattern = '/^gf_notify_enable_form_(\d+)$/';
            $forms   = $this->getActiveFormsByPattern($pattern, $formsData, $options);

            $settings['gravityforms_sms_enabled_forms'] = [
                'label' => esc_html__('SMS Notifications Gravityforms', 'wp-sms'),
                'value' => $forms['value'],
                'debug' => $forms['debug'],
            ];
        }

        // Quform
        if (class_exists('Quform_Repository') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['quform_integration'] = [
                'label' => esc_html__('Quform Integration', 'wp-sms'),
                'value' => esc_html__('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $quformRepository = new \Quform_Repository();
            $quforms          = $quformRepository->allForms();
            $formsData        = [];

            if (!empty($quforms)) {
                foreach ($quforms as $form) {
                    $formsData[(int)$form['id']] = $form['name'];
                }
            }

            $pattern = '/^qf_notify_enable_form_(\d+)$/';
            $forms   = $this->getActiveFormsByPattern($pattern, $formsData, $options);

            $settings['quform_sms_enabled_forms'] = [
                'label' => esc_html__('SMS Notifications Quforms', 'wp-sms'),
                'value' => $forms['value'],
                'debug' => $forms['debug'],
            ];
        }

        // EDD
        if (class_exists('Easy_Digital_Downloads') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['edd_integration'] = [
                'label' => esc_html__('EDD Integration', 'wp-sms'),
                'value' => esc_html__('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $eddMobileFieldRaw            = Option::getOption('edd_mobile_field', true);
            $settings['edd_mobile_field'] = [
                'label' => esc_html__('EDD Mobile Field', 'wp-sms'),
                'value' => $yesNo($eddMobileFieldRaw),
                'debug' => $yesNoDebug($eddMobileFieldRaw),
            ];

            $eddNotifyOrderRaw            = Option::getOption('edd_notify_order_enable', true);
            $settings['edd_notify_order'] = [
                'label' => esc_html__('EDD New Order Notifications', 'wp-sms'),
                'value' => $yesNo($eddNotifyOrderRaw),
                'debug' => $yesNoDebug($eddNotifyOrderRaw),
            ];

            $eddNotifyCustomerRaw            = Option::getOption('edd_notify_customer_enable', true);
            $settings['edd_notify_customer'] = [
                'label' => esc_html__('EDD Customer Notifications', 'wp-sms'),
                'value' => $yesNo($eddNotifyCustomerRaw),
                'debug' => $yesNoDebug($eddNotifyCustomerRaw),
            ];
        }

        // Job Manager
        if (class_exists('WP_Job_Manager') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['job_manager_integration'] = [
                'label' => esc_html__('WP Job Manager Integration', 'wp-sms'),
                'value' => esc_html__('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $jobMobileFieldRaw            = Option::getOption('job_mobile_field', true);
            $settings['job_mobile_field'] = [
                'label' => esc_html__('Job Mobile Field', 'wp-sms'),
                'value' => $yesNo($jobMobileFieldRaw),
                'debug' => $yesNoDebug($jobMobileFieldRaw),
            ];

            $jobDisplayMobileRaw            = Option::getOption('job_display_mobile_number', true);
            $settings['job_display_mobile'] = [
                'label' => esc_html__('Display Mobile Number', 'wp-sms'),
                'value' => $yesNo($jobDisplayMobileRaw),
                'debug' => $yesNoDebug($jobDisplayMobileRaw),
            ];

            $jobNotifyStatusRaw                   = Option::getOption('job_notify_status', true);
            $settings['job_new_job_notification'] = [
                'label' => esc_html__('New Job Notification', 'wp-sms'),
                'value' => $yesNo($jobNotifyStatusRaw),
                'debug' => $yesNoDebug($jobNotifyStatusRaw),
            ];

            $jobEmployerNotifRaw                   = Option::getOption('job_notify_employer_status', true);
            $settings['job_employer_notification'] = [
                'label' => esc_html__('Employer Notification', 'wp-sms'),
                'value' => $yesNo($jobEmployerNotifRaw),
                'debug' => $yesNoDebug($jobEmployerNotifRaw),
            ];
        }

        // Awesome Support
        if (class_exists('Awesome_Support') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['awesome_support_integration'] = [
                'label' => esc_html__('Awesome Support Integration', 'wp-sms'),
                'value' => esc_html__('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $asOpenRaw                              = Option::getOption('as_notify_open_ticket_status', true);
            $settings['as_new_ticket_notification'] = [
                'label' => esc_html__('New Ticket Notification', 'wp-sms'),
                'value' => $yesNo($asOpenRaw),
                'debug' => $yesNoDebug($asOpenRaw),
            ];

            $asAdminReplyRaw                         = Option::getOption('as_notify_admin_reply_ticket_status', true);
            $settings['as_admin_reply_notification'] = [
                'label' => esc_html__('Admin Reply Notification', 'wp-sms'),
                'value' => $yesNo($asAdminReplyRaw),
                'debug' => $yesNoDebug($asAdminReplyRaw),
            ];

            $asUserReplyRaw                         = Option::getOption('as_notify_user_reply_ticket_status', true);
            $settings['as_user_reply_notification'] = [
                'label' => esc_html__('User Reply Notification', 'wp-sms'),
                'value' => $yesNo($asUserReplyRaw),
                'debug' => $yesNoDebug($asUserReplyRaw),
            ];

            $asUpdateRaw                               = Option::getOption('as_notify_update_ticket_status', true);
            $settings['as_status_update_notification'] = [
                'label' => esc_html__('Status Update Notification', 'wp-sms'),
                'value' => $yesNo($asUpdateRaw),
                'debug' => $yesNoDebug($asUpdateRaw),
            ];

            $asCloseRaw                               = Option::getOption('as_notify_close_ticket_status', true);
            $settings['as_ticket_close_notification'] = [
                'label' => esc_html__('Ticket Close Notification', 'wp-sms'),
                'value' => $yesNo($asCloseRaw),
                'debug' => $yesNoDebug($asCloseRaw),
            ];
        }

        // Ultimate Member
        if (function_exists('um_user') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['ultimate_member_integration'] = [
                'label' => esc_html__('Ultimate Member Integration', 'wp-sms'),
                'value' => esc_html__('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $umApprovalRaw                        = Option::getOption('um_send_sms_after_approval', true);
            $settings['um_approval_notification'] = [
                'label' => esc_html__('Ultimate Member User Approval Notification', 'wp-sms'),
                'value' => $yesNo($umApprovalRaw),
                'debug' => $yesNoDebug($umApprovalRaw),
            ];
        }

        // Formidable Forms
        if (function_exists('is_plugin_active') && is_plugin_active('formidable/formidable.php')) {
            $settings['formidable_integration'] = [
                'label' => esc_html__('Formidable Integration', 'wp-sms'),
                'value' => esc_html__('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $formidableMetaboxRaw           = Option::getOption('formidable_metabox');
            $settings['formidable_metabox'] = [
                'label' => esc_html__('Formidable Metabox', 'wp-sms'),
                'value' => $yesNo($formidableMetaboxRaw),
                'debug' => $yesNoDebug($formidableMetaboxRaw),
            ];
        }

        // Forminator
        if (class_exists('Forminator') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['forminator_integration'] = [
                'label' => esc_html__('Forminator Integration', 'wp-sms'),
                'value' => esc_html__('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $forminatorForms = \Forminator_API::get_forms(null, 1, 20, "publish");
            $formsData       = [];

            if (!empty($forminatorForms)) {
                foreach ($forminatorForms as $form) {
                    $formsData[(int)$form->id] = $form->name;
                }
            }

            $options = Option::getOptions();
            $pattern = '/^forminator_notify_enable_form_(\d+)$/';
            $forms   = $this->getActiveFormsByPattern($pattern, $formsData, $options);

            $settings['forminator_sms_enabled_forms'] = [
                'label' => esc_html__('SMS Notifications Forminator forms', 'wp-sms'),
                'value' => $forms['value'],
                'debug' => $forms['debug'],
            ];
        }

        // BuddyPress
        if (function_exists('buddypress') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['buddypress_integration'] = [
                'label' => esc_html__('BuddyPress Integration', 'wp-sms'),
                'value' => esc_html__('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $bpWelcomeRaw               = Option::getOption('bp_welcome_notification_enable', true);
            $settings['bp_welcome_sms'] = [
                'label' => esc_html__('BuddyPress Welcome SMS', 'wp-sms'),
                'value' => $yesNo($bpWelcomeRaw),
                'debug' => $yesNoDebug($bpWelcomeRaw),
            ];

            $bpMentionRaw                        = Option::getOption('bp_mention_enable', true);
            $settings['bp_mention_notification'] = [
                'label' => esc_html__('BuddyPress Mention Alerts', 'wp-sms'),
                'value' => $yesNo($bpMentionRaw),
                'debug' => $yesNoDebug($bpMentionRaw),
            ];

            $bpPmRaw                                     = Option::getOption('bp_private_message_enable', true);
            $settings['bp_private_message_notification'] = [
                'label' => esc_html__('BuddyPress Private Messages', 'wp-sms'),
                'value' => $yesNo($bpPmRaw),
                'debug' => $yesNoDebug($bpPmRaw),
            ];

            $bpActReplyRaw                              = Option::getOption('bp_comments_activity_enable', true);
            $settings['bp_activity_reply_notification'] = [
                'label' => esc_html__('BuddyPress Activity Replies', 'wp-sms'),
                'value' => $yesNo($bpActReplyRaw),
                'debug' => $yesNoDebug($bpActReplyRaw),
            ];

            $bpCmtReplyRaw                             = Option::getOption('bp_comments_reply_enable', true);
            $settings['bp_comment_reply_notification'] = [
                'label' => esc_html__('BuddyPress Comment Replies', 'wp-sms'),
                'value' => $yesNo($bpCmtReplyRaw),
                'debug' => $yesNoDebug($bpCmtReplyRaw),
            ];
        }

        // WooCommerce
        if (class_exists('WooCommerce') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['woocommerce_integration'] = [
                'label' => esc_html__('WooCommerce Integration', 'wp-sms'),
                'value' => esc_html__('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $wcMetaBoxRaw            = Option::getOption('wc_meta_box_enable', true);
            $settings['wc_meta_box'] = [
                'label' => esc_html__('WooCommerce Order Meta Box', 'wp-sms'),
                'value' => $yesNo($wcMetaBoxRaw),
                'debug' => $yesNoDebug($wcMetaBoxRaw),
            ];

            $wcNotifyProductRaw            = Option::getOption('wc_notify_product_enable', true);
            $settings['wc_notify_product'] = [
                'label' => esc_html__('WooCommerce New Product Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyProductRaw),
                'debug' => $yesNoDebug($wcNotifyProductRaw),
            ];

            $wcNotifyOrderRaw            = Option::getOption('wc_notify_order_enable', true);
            $settings['wc_notify_order'] = [
                'label' => esc_html__('WooCommerce New Order Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyOrderRaw),
                'debug' => $yesNoDebug($wcNotifyOrderRaw),
            ];

            $wcNotifyCustomerRaw            = Option::getOption('wc_notify_customer_enable', true);
            $settings['wc_notify_customer'] = [
                'label' => esc_html__('WooCommerce Customer Order Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyCustomerRaw),
                'debug' => $yesNoDebug($wcNotifyCustomerRaw),
            ];

            $wcNotifyStockRaw            = Option::getOption('wc_notify_stock_enable', true);
            $settings['wc_notify_stock'] = [
                'label' => esc_html__('WooCommerce Low Stock Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyStockRaw),
                'debug' => $yesNoDebug($wcNotifyStockRaw),
            ];

            $wcCheckoutConfirmRaw                 = Option::getOption('wc_checkout_confirmation_checkbox_enabled', true);
            $settings['wc_checkout_confirmation'] = [
                'label' => esc_html__('WooCommerce Checkout Confirmation Checkbox', 'wp-sms'),
                'value' => $yesNo($wcCheckoutConfirmRaw),
                'debug' => $yesNoDebug($wcCheckoutConfirmRaw),
            ];

            $wcNotifyStatusRaw            = Option::getOption('wc_notify_status_enable', true);
            $settings['wc_notify_status'] = [
                'label' => esc_html__('WooCommerce Order Status Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyStatusRaw),
                'debug' => $yesNoDebug($wcNotifyStatusRaw),
            ];

            $wcNotifyByStatusRaw             = Option::getOption('wc_notify_by_status_enable', true);
            $settings['wc_notify_by_status'] = [
                'label' => esc_html__('WooCommerce Specific Order Status Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyByStatusRaw),
                'debug' => $yesNoDebug($wcNotifyByStatusRaw),
            ];

            $statusContent                      = Option::getOption('wc_notify_by_status_content', true);
            $statusCount                        = is_array($statusContent) ? count($statusContent) : 0;
            $settings['wc_notify_status_count'] = [
                'label' => esc_html__('WooCommerce Configured Status Notifications', 'wp-sms'),
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

    /**
     * Format duration array (days, hours, minutes) into human readable string.
     *
     * @param mixed $raw
     * @return array ['value' => string, 'debug' => string]
     */
    private function formatDurationValue($raw)
    {
        if (!is_array($raw)) {
            return array('value' => esc_html__('Not Set', 'wp-sms'), 'debug' => 'Not Set');
        }

        $partsVal = array();
        $partsDbg = array();

        $append = function ($key, $singular, $plural) use ($raw, &$partsVal, &$partsDbg) {
            if (!empty($raw[$key]) && $raw[$key] !== '0') {
                $n          = (int)$raw[$key];
                $partsVal[] = $raw[$key] . ' ' . _n($singular, $plural, $n, 'wp-sms');
                $partsDbg[] = $raw[$key] . ' ' . ($n === 1 ? $singular : $plural);
            }
        };

        $append('days', 'day', 'days');
        $append('hours', 'hour', 'hours');
        $append('minutes', 'minute', 'minutes');

        if (empty($partsVal)) {
            return array('value' => esc_html__('Not Set', 'wp-sms'), 'debug' => 'Not Set');
        }

        return array(
            'value' => implode(', ', $partsVal),
            'debug' => implode(', ', $partsDbg),
        );
    }

    private function getWooProSettings()
    {
        $woo = function ($key, $default = null) {
            return \WPSmsWooPro\Core\Helper::getOption($key, $default);
        };

        $labels = array(
            'cart_abandonment_recovery_status'                    => esc_html__('WooPro: Cart Abandonment Recovery', 'wp-sms'),
            'cart_abandonment_threshold'                          => esc_html__('WooPro: Cart abandonment threshold', 'wp-sms'),
            'cart_overwrite_number_during_checkout'               => esc_html__('WooPro: Cart abandonment Overwrite mobile number', 'wp-sms'),
            'cart_create_coupon'                                  => esc_html__('WooPro: Cart abandonment Create coupon', 'wp-sms'),
            'cart_abandonment_send_sms_time_interval'             => esc_html__('WooPro: Cart abandonment Send sms after', 'wp-sms'),
            'login_with_sms_status'                               => esc_html__('WooPro: Show Button in Login Page', 'wp-sms'),
            'login_with_sms_forgot_status'                        => esc_html__('WooPro: Show Button in Forgot Password Page', 'wp-sms'),
            'reset_password_status'                               => esc_html__('WooPro: Enable SMS Password Reset', 'wp-sms'),
            'checkout_confirmation_checkbox_enabled'              => esc_html__('WooPro: Confirmation Checkbox', 'wp-sms'),
            'checkout_mobile_verification_enabled'                => esc_html__('WooPro: Enable Mobile Verification', 'wp-sms'),
            'register_user_via_sms_status'                        => esc_html__('WooPro: Automatic Registration via SMS', 'wp-sms'),
            'checkout_mobile_verification_skip_logged_in_enabled' => esc_html__('WooPro: Skip Verification for Logged-In Users', 'wp-sms'),
            'checkout_mobile_verification_countries_whitelist'    => esc_html__('WooPro: Required Countries for Mobile Verification', 'wp-sms'),
        );

        $yesVals = array(true, '1', 1, 'yes');
        $noVals  = array(false, '0', 0, 'no');

        $formatGeneric = function ($raw) use ($yesVals, $noVals) {
            if (in_array($raw, $yesVals, true)) {
                return array('value' => esc_html__('Enabled', 'wp-sms'), 'debug' => 'Enabled');
            }
            if (in_array($raw, $noVals, true)) {
                return array('value' => esc_html__('Disabled', 'wp-sms'), 'debug' => 'Disabled');
            }
            if ($raw === null || $raw === '') {
                return array('value' => esc_html__('Not Set', 'wp-sms'), 'debug' => 'Not Set');
            }
            $str = (string)$raw;
            return array('value' => $str, 'debug' => $str);
        };

        $settings = array();

        // cart_abandonment_recovery_status (generic)
        $raw                                              = $woo('cart_abandonment_recovery_status');
        $res                                              = $formatGeneric($raw);
        $settings['woo_cart_abandonment_recovery_status'] = array(
            'label' => $labels['cart_abandonment_recovery_status'],
            'value' => $res['value'],
            'debug' => $res['debug'],
        );

        // cart_abandonment_threshold (duration)
        $raw                                        = $woo('cart_abandonment_threshold');
        $res                                        = $this->formatDurationValue($raw);
        $settings['woo_cart_abandonment_threshold'] = array(
            'label' => $labels['cart_abandonment_threshold'],
            'value' => $res['value'],
            'debug' => $res['debug'],
        );

        // cart_overwrite_number_during_checkout (special)
        $raw                                                   = $woo('cart_overwrite_number_during_checkout');
        $skip                                                  = ($raw === 'skip');
        $res                                                   = array(
            'value' => $skip ? esc_html__('Do not update', 'wp-sms') : esc_html__('Update phone number', 'wp-sms'),
            'debug' => $skip ? 'Do not update' : 'Update phone number',
        );
        $settings['woo_cart_overwrite_number_during_checkout'] = array(
            'label' => $labels['cart_overwrite_number_during_checkout'],
            'value' => $res['value'],
            'debug' => $res['debug'],
        );

        // cart_create_coupon (generic)
        $raw                                = $woo('cart_create_coupon');
        $res                                = $formatGeneric($raw);
        $settings['woo_cart_create_coupon'] = array(
            'label' => $labels['cart_create_coupon'],
            'value' => $res['value'],
            'debug' => $res['debug'],
        );

        // cart_abandonment_send_sms_time_interval (duration)
        $raw                                                     = $woo('cart_abandonment_send_sms_time_interval');
        $res                                                     = $this->formatDurationValue($raw);
        $settings['woo_cart_abandonment_send_sms_time_interval'] = array(
            'label' => $labels['cart_abandonment_send_sms_time_interval'],
            'value' => $res['value'],
            'debug' => $res['debug'],
        );

        // login_with_sms_status (generic)
        $raw                                   = $woo('login_with_sms_status');
        $res                                   = $formatGeneric($raw);
        $settings['woo_login_with_sms_status'] = array(
            'label' => $labels['login_with_sms_status'],
            'value' => $res['value'],
            'debug' => $res['debug'],
        );

        // login_with_sms_forgot_status (generic)
        $raw                                          = $woo('login_with_sms_forgot_status');
        $res                                          = $formatGeneric($raw);
        $settings['woo_login_with_sms_forgot_status'] = array(
            'label' => $labels['login_with_sms_forgot_status'],
            'value' => $res['value'],
            'debug' => $res['debug'],
        );

        // reset_password_status (generic)
        $raw                                   = $woo('reset_password_status');
        $res                                   = $formatGeneric($raw);
        $settings['woo_reset_password_status'] = array(
            'label' => $labels['reset_password_status'],
            'value' => $res['value'],
            'debug' => $res['debug'],
        );

        // checkout_confirmation_checkbox_enabled (generic)
        $raw                                                    = $woo('checkout_confirmation_checkbox_enabled');
        $res                                                    = $formatGeneric($raw);
        $settings['woo_checkout_confirmation_checkbox_enabled'] = array(
            'label' => $labels['checkout_confirmation_checkbox_enabled'],
            'value' => $res['value'],
            'debug' => $res['debug'],
        );

        // checkout_mobile_verification_enabled (generic)
        $raw                                                  = $woo('checkout_mobile_verification_enabled');
        $res                                                  = $formatGeneric($raw);
        $settings['woo_checkout_mobile_verification_enabled'] = array(
            'label' => $labels['checkout_mobile_verification_enabled'],
            'value' => $res['value'],
            'debug' => $res['debug'],
        );

        // register_user_via_sms_status (enabled/disabled only)
        $raw                                          = $woo('register_user_via_sms_status');
        $enabled                                      = in_array($raw, $yesVals, true);
        $res                                          = array(
            'value' => $enabled ? esc_html__('Enabled', 'wp-sms') : esc_html__('Disabled', 'wp-sms'),
            'debug' => $enabled ? 'Enabled' : 'Disabled',
        );
        $settings['woo_register_user_via_sms_status'] = array(
            'label' => $labels['register_user_via_sms_status'],
            'value' => $res['value'],
            'debug' => $res['debug'],
        );

        // checkout_mobile_verification_skip_logged_in_enabled (enabled/disabled only)
        $raw                                                                 = $woo('checkout_mobile_verification_skip_logged_in_enabled');
        $enabled                                                             = in_array($raw, $yesVals, true);
        $res                                                                 = array(
            'value' => $enabled ? esc_html__('Enabled', 'wp-sms') : esc_html__('Disabled', 'wp-sms'),
            'debug' => $enabled ? 'Enabled' : 'Disabled',
        );
        $settings['woo_checkout_mobile_verification_skip_logged_in_enabled'] = array(
            'label' => $labels['checkout_mobile_verification_skip_logged_in_enabled'],
            'value' => $res['value'],
            'debug' => $res['debug'],
        );

        // checkout_mobile_verification_countries_whitelist (list or Not Set)
        $raw                                                              = $woo('checkout_mobile_verification_countries_whitelist');
        $has                                                              = is_array($raw) && !empty($raw);
        $joined                                                           = $has ? implode(', ', $raw) : null;
        $res                                                              = array(
            'value' => $has ? $joined : esc_html__('Not Set', 'wp-sms'),
            'debug' => $has ? $joined : 'Not Set',
        );
        $settings['woo_checkout_mobile_verification_countries_whitelist'] = array(
            'label' => $labels['checkout_mobile_verification_countries_whitelist'],
            'value' => $res['value'],
            'debug' => $res['debug'],
        );

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
            'booking_calendar_notif_admin_new_booking'              => esc_html__('Booking Calendar: Admin New Booking Notification', 'wp-sms'),
            'booking_calendar_notif_customer_new_booking'           => esc_html__('Booking Calendar: Customer New Booking Notification', 'wp-sms'),
            'booking_calendar_notif_customer_booking_approved'      => esc_html__('Booking Calendar: Booking Approved Notification', 'wp-sms'),
            'booking_calendar_notif_customer_booking_cancelled'     => esc_html__('Booking Calendar: Booking Cancelled Notification', 'wp-sms'),

            // === BookingPress ===
            'bookingpress_notif_admin_approved_appointment'         => esc_html__('BookingPress: Admin Approved Appointment', 'wp-sms'),
            'bookingpress_notif_customer_approved_appointment'      => esc_html__('BookingPress: Customer Approved Appointment', 'wp-sms'),
            'bookingpress_notif_admin_pending_appointment'          => esc_html__('BookingPress: Admin Pending Appointment', 'wp-sms'),
            'bookingpress_notif_customer_pending_appointment'       => esc_html__('BookingPress: Customer Pending Appointment', 'wp-sms'),
            'bookingpress_notif_admin_rejected_appointment'         => esc_html__('BookingPress: Admin Rejected Appointment', 'wp-sms'),
            'bookingpress_notif_customer_rejected_appointment'      => esc_html__('BookingPress: Customer Rejected Appointment', 'wp-sms'),
            'bookingpress_notif_admin_cancelled_appointment'        => esc_html__('BookingPress: Admin Cancelled Appointment', 'wp-sms'),
            'bookingpress_notif_customer_cancelled_appointment'     => esc_html__('BookingPress: Customer Cancelled Appointment', 'wp-sms'),

            // === Woo Appointments ===
            'woo_appointments_notif_admin_new_appointment'          => esc_html__('Woo Appointments: Admin New Appointment', 'wp-sms'),
            'woo_appointments_notif_admin_cancelled_appointment'    => esc_html__('Woo Appointments: Admin Cancelled Appointment', 'wp-sms'),
            'woo_appointments_notif_customer_cancelled_appointment' => esc_html__('Woo Appointments: Customer Cancelled Appointment', 'wp-sms'),
            'woo_appointments_notif_admin_rescheduled_appointment'  => esc_html__('Woo Appointments: Admin Rescheduled Appointment', 'wp-sms'),
            'woo_appointments_notif_customer_confirmed_appointment' => esc_html__('Woo Appointments: Customer Confirmed Appointment', 'wp-sms'),

            // === Woo Bookings ===
            'woo_bookings_notif_admin_new_booking'                  => esc_html__('Woo Bookings: Admin New Booking', 'wp-sms'),
            'woo_bookings_notif_admin_cancelled_booking'            => esc_html__('Woo Bookings: Admin Cancelled Booking', 'wp-sms'),
            'woo_bookings_notif_customer_cancelled_booking'         => esc_html__('Woo Bookings: Customer Cancelled Booking', 'wp-sms'),
            'woo_bookings_notif_customer_confirmed_booking'         => esc_html__('Woo Bookings: Customer Confirmed Booking', 'wp-sms'),
        );

        foreach ($options as $key => $label) {
            $raw      = OptionUtil::get($key);
            $isToggle = (is_bool($raw) || $raw === '0' || $raw === '1');
            $value    = $isToggle
                ? ($raw ? esc_html__('Enabled', 'wp-sms') : esc_html__('Disabled', 'wp-sms'))
                : (is_string($raw) ? $raw : esc_html__('Not Set', 'wp-sms'));
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
            'fluent_crm_notif_contact_subscribed'    => esc_html__('FluentCRM: Contact Subscribed Notification', 'wp-sms'),
            'fluent_crm_notif_contact_unsubscribed'  => esc_html__('FluentCRM: Contact Unsubscribed Notification', 'wp-sms'),
            'fluent_crm_notif_contact_pending'       => esc_html__('FluentCRM: Contact Pending Notification', 'wp-sms'),

            // Fluent Support
            'fluent_support_notif_ticket_created'    => esc_html__('Fluent Support: Ticket Created', 'wp-sms'),
            'fluent_support_notif_customer_response' => esc_html__('Fluent Support: Customer Response', 'wp-sms'),
            'fluent_support_notif_agent_assigned'    => esc_html__('Fluent Support: Agent Assigned', 'wp-sms'),
            'fluent_support_notif_ticket_closed'     => esc_html__('Fluent Support: Ticket Closed', 'wp-sms'),
        ];

        foreach ($options as $key => $label) {
            $raw      = OptionUtil::get($key);
            $isToggle = (is_bool($raw) || $raw === '0' || $raw === '1');
            $value    = $isToggle
                ? ($raw ? esc_html__('Enabled', 'wp-sms') : esc_html__('Disabled', 'wp-sms'))
                : (is_string($raw) ? $raw : esc_html__('Not Set', 'wp-sms'));
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
            'pmpro_notif_user_registered'       => esc_html__('Paid Memberships Pro: User Registered Notification', 'wp-sms'),
            'pmpro_notif_membership_confirmed'  => esc_html__('Paid Memberships Pro: Membership Confirmed Notification', 'wp-sms'),
            'pmpro_notif_membership_cancelled'  => esc_html__('Paid Memberships Pro: Membership Cancelled Notification', 'wp-sms'),
            'pmpro_notif_membership_expired'    => esc_html__('Paid Memberships Pro: Membership Expired Notification', 'wp-sms'),

            // Simple Membership
            'sm_notif_admin_user_registered'    => esc_html__('Simple Membership: Admin Notified on User Registration', 'wp-sms'),
            'sm_notif_membership_level_updated' => esc_html__('Simple Membership: Membership Level Updated', 'wp-sms'),
            'sm_notif_membership_expired'       => esc_html__('Simple Membership: Membership Expired', 'wp-sms'),
            'sm_notif_membership_cancelled'     => esc_html__('Simple Membership: Membership Cancelled', 'wp-sms'),
            'sm_notif_admin_payment_recieved'   => esc_html__('Simple Membership: Payment Received (Admin)', 'wp-sms'),
        ];

        foreach ($options as $key => $label) {
            $raw      = OptionUtil::get($key);
            $isToggle = (is_bool($raw) || $raw === '0' || $raw === '1');
            $value    = $isToggle
                ? ($raw ? esc_html__('Enabled', 'wp-sms') : esc_html__('Disabled', 'wp-sms'))
                : (is_string($raw) ? $raw : esc_html__('Not Set', 'wp-sms'));
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
            'notif_new_inbox_message' => esc_html__('Two-Way: Forward Incoming SMS to Admin', 'wp-sms'),
            'email_new_inbox_message' => esc_html__('Two-Way: Forward Incoming SMS to Email', 'wp-sms'),
        ];

        foreach ($options as $key => $label) {
            $raw      = OptionUtil::get($key);
            $isToggle = (is_bool($raw) || $raw === '0' || $raw === '1');
            $value    = $isToggle
                ? ($raw ? esc_html__('Enabled', 'wp-sms') : esc_html__('Disabled', 'wp-sms'))
                : (is_string($raw) ? $raw : esc_html__('Not Set', 'wp-sms'));
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
     * Retrieve a list of active forms.
     *
     * @param string $keyPattern
     * @param array $formsData
     * @param array|null $options
     *
     * @return array
     */
    private function getActiveFormsByPattern($keyPattern, $formsData, $options)
    {
        $options = (array)$options;
        $keys    = preg_grep($keyPattern, array_keys($options)) ?: [];
        $titles  = [];

        foreach ($keys as $key) {
            $rawVal = $options[$key] ?? null;

            if (!in_array($rawVal, array(true, '1', 1, 'yes', 'on'), true)) {
                continue;
            }

            $id = 0;
            if (preg_match($keyPattern, $key, $m) && isset($m[1])) {
                $id = (int)$m[1];
            }

            $title = $formsData[$id] ?? '';
            if ($title === '') {
                continue;
            }

            $titles[] = trim($title);
        }

        $titles = array_unique($titles);

        $value = !empty($titles) ? implode(', ', $titles) : esc_html__('Not Set', 'wp-sms');
        $debug = !empty($titles) ? implode(', ', $titles) : 'Not Set';

        return array(
            'value' => $value,
            'debug' => $debug,
        );
    }
}