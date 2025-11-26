<?php

namespace WP_SMS\Admin;

use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\Gateway;
use WP_SMS\Utils\OptionUtil;
use WP_SMS\Option;

if (!defined('ABSPATH')) exit;

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

        $yesNo = function ($value) {
            return $value ? esc_html__('Enabled', 'wp-sms') : esc_html__('Disabled', 'wp-sms');
        };

        $yesNoDebug = function ($value) {
            return $value ? 'Enabled' : 'Disabled';
        };

        $raw = function ($key, $default = null) {
            return OptionUtil::get($key, $default);
        };

        $version                    = defined('WP_SMS_VERSION') ? WP_SMS_VERSION : 'N/A';
        $settings['plugin_version'] = array(
            'label' => esc_html__('Plugin Version', 'wp-sms'),
            'value' => $version === 'N/A' ? esc_html__('N/A', 'wp-sms') : $version,
            'debug' => $version === 'N/A' ? 'N/A' : $version,
        );

        $dbVersion              = get_option('wp_sms_db_version', 'Not Set');
        $settings['db_version'] = array(
            'label' => esc_html__('Database Version', 'wp-sms'),
            'value' => $dbVersion === 'Not Set' ? esc_html__('Not Set', 'wp-sms') : $dbVersion,
            'debug' => $dbVersion === 'Not Set' ? 'Not Set' : $dbVersion,
        );

        $mobileFieldSource            = $raw('add_mobile_field', 'Not Set');
        $settings['add_mobile_field'] = [
            'label' => esc_html__('Mobile Number Field Source', 'wp-sms'),
            'value' => $mobileFieldSource,
        ];

        $isRequired                        = $raw('optional_mobile_field') !== 'optional';
        $settings['optional_mobile_field'] = [
            'label' => esc_html__('Mobile Field Mandatory Status', 'wp-sms'),
            'value' => $isRequired ? esc_html__('Required', 'wp-sms') : esc_html__('Optional', 'wp-sms'),
            'debug' => $isRequired ? 'Required' : 'Optional',
        ];

        $internationalMobile              = $raw('international_mobile');
        $settings['international_mobile'] = [
            'label' => esc_html__('International Number Input', 'wp-sms'),
            'value' => $yesNo($internationalMobile),
            'debug' => $yesNoDebug($internationalMobile),
        ];

        $onlyCountries                                   = array_filter((array)$raw('international_mobile_only_countries'));
        $settings['international_mobile_only_countries'] = [
            'label' => esc_html__('Only Countries', 'wp-sms'),
            'value' => $onlyCountries ? implode(', ', $onlyCountries) : esc_html__('Not Set', 'wp-sms'),
            'debug' => $onlyCountries ? implode(', ', $onlyCountries) : 'Not Set',
        ];

        $preferredCountries                                   = array_filter((array)$raw('international_mobile_preferred_countries'));
        $settings['international_mobile_preferred_countries'] = [
            'label' => esc_html__('Preferred Countries', 'wp-sms'),
            'value' => $preferredCountries ? implode(', ', $preferredCountries) : esc_html__('Not Set', 'wp-sms'),
            'debug' => $preferredCountries ? implode(', ', $preferredCountries) : 'Not Set',
        ];

        $gatewayName              = $raw('gateway_name');
        $settings['gateway_name'] = [
            'label' => esc_html__('SMS Gateway Name', 'wp-sms'),
            'value' => !empty($gatewayName) ? $gatewayName : esc_html__('Not Set', 'wp-sms'),
            'debug' => !empty($gatewayName) ? $gatewayName : 'Not Set',
        ];

        $gatewayStatus              = Gateway::status(true);
        $settings['gateway_status'] = [
            'label' => esc_html__('SMS Gateway Status', 'wp-sms'),
            'value' => $gatewayStatus ? esc_html__('Active', 'wp-sms') : esc_html__('Inactive', 'wp-sms'),
            'debug' => $gatewayStatus ? 'Active' : 'Inactive',
        ];

        $settings['gateway_incoming_message'] = [
            'label' => esc_html__('SMS Gateway Incoming Message', 'wp-sms'),
            'value' => $yesNo($sms->supportIncoming),
            'debug' => $yesNoDebug($sms->supportIncoming),
        ];

        $settings['gateway_send_bulk_sms'] = [
            'label' => esc_html__('SMS Gateway Send Bulk SMS', 'wp-sms'),
            'value' => $yesNo($sms->bulk_send),
            'debug' => $yesNoDebug($sms->bulk_send),
        ];

        $settings['gateway_send_mms'] = [
            'label' => esc_html__('SMS Gateway Send MMS', 'wp-sms'),
            'value' => $yesNo($sms->supportMedia),
            'debug' => $yesNoDebug($sms->supportMedia),
        ];

        $deliveryMethod                  = $raw('sms_delivery_method');
        $settings['sms_delivery_method'] = [
            'label' => esc_html__('SMS Gateway Delivery Method', 'wp-sms'),
            'value' => !empty($deliveryMethod) ? $deliveryMethod : esc_html__('Not Set', 'wp-sms'),
            'debug' => !empty($deliveryMethod) ? $deliveryMethod : 'Not Set',
        ];

        $sendUnicode              = $raw('send_unicode');
        $settings['send_unicode'] = [
            'label' => esc_html__('SMS Gateway Unicode Messaging', 'wp-sms'),
            'value' => $yesNo($sendUnicode),
            'debug' => $yesNoDebug($sendUnicode),
        ];

        $cleanNumbers              = $raw('clean_numbers');
        $settings['clean_numbers'] = [
            'label' => esc_html__('SMS Gateway Number Formatting', 'wp-sms'),
            'value' => $yesNo($cleanNumbers),
            'debug' => $yesNoDebug($cleanNumbers),
        ];

        $restrictLocal    = $raw('send_only_local_numbers');
        $allowedCountries = array_filter((array)$raw('only_local_numbers_countries'));

        $settings['send_only_local_numbers'] = [
            'label' => esc_html__('SMS Gateway Restrict to Local Numbers', 'wp-sms'),
            'value' => $yesNo($restrictLocal),
            'debug' => $yesNoDebug($restrictLocal),
        ];

        if ($restrictLocal && !empty($allowedCountries)) {
            $settings['only_local_numbers_countries'] = [
                'label' => esc_html__('SMS Gateway Allowed Countries for SMS', 'wp-sms'),
                'value' => implode(', ', $allowedCountries),
            ];
        }

        $newsletterGroups                   = $raw('newsletter_form_groups');
        $settings['newsletter_form_groups'] = [
            'label' => esc_html__('SMS Newsletter Group Visibility in Form', 'wp-sms'),
            'value' => $yesNo($newsletterGroups),
            'debug' => $yesNoDebug($newsletterGroups),
        ];

        $newsletterMultiple                          = $raw('newsletter_form_multiple_select');
        $settings['newsletter_form_multiple_select'] = [
            'label' => esc_html__('SMS Newsletter Group Selection', 'wp-sms'),
            'value' => $yesNo($newsletterMultiple),
            'debug' => $yesNoDebug($newsletterMultiple),
        ];

        $newsletterVerify                   = $raw('newsletter_form_verify');
        $settings['newsletter_form_verify'] = [
            'label' => esc_html__('SMS Newsletter Subscription Confirmation', 'wp-sms'),
            'value' => $yesNo($newsletterVerify),
            'debug' => $yesNoDebug($newsletterVerify),
        ];

        $chatboxButton                      = $raw('chatbox_message_button');
        $settings['chatbox_message_button'] = [
            'label' => esc_html__('Message Button Status', 'wp-sms'),
            'value' => $yesNo($chatboxButton),
            'debug' => $yesNoDebug($chatboxButton),
        ];

        $reportStatistics                    = $raw('report_wpsms_statistics');
        $settings['report_wpsms_statistics'] = [
            'label' => esc_html__('SMS Performance Reports', 'wp-sms'),
            'value' => $yesNo($reportStatistics),
            'debug' => $yesNoDebug($reportStatistics),
        ];

        $shortUrlStatus               = $raw('short_url_status');
        $settings['short_url_status'] = [
            'label' => esc_html__('Shorten URLs', 'wp-sms'),
            'value' => $yesNo($shortUrlStatus),
            'debug' => $yesNoDebug($shortUrlStatus),
        ];

        $recaptcha                      = $raw('g_recaptcha_status');
        $settings['g_recaptcha_status'] = [
            'label' => esc_html__('Google reCAPTCHA Integration', 'wp-sms'),
            'value' => $yesNo($recaptcha),
            'debug' => $yesNoDebug($recaptcha),
        ];

        $loginWithSms          = Option::getOption('login_sms', true);
        $settings['login_sms'] = [
            'label' => esc_html__('Login With SMS', 'wp-sms'),
            'value' => $yesNo($loginWithSms),
            'debug' => $yesNoDebug($loginWithSms),
        ];

        $twoFactor                 = Option::getOption('mobile_verify', true);
        $settings['mobile_verify'] = [
            'label' => esc_html__('Two-Factor Authentication with SMS', 'wp-sms'),
            'value' => $yesNo($twoFactor),
            'debug' => $yesNoDebug($twoFactor),
        ];

        $autoRegister             = Option::getOption('register_sms', true);
        $settings['register_sms'] = [
            'label' => esc_html__('Create User on SMS Login', 'wp-sms'),
            'value' => $yesNo($autoRegister),
            'debug' => $yesNoDebug($autoRegister),
        ];

        $cf7Metabox              = $raw('cf7_metabox');
        $settings['cf7_metabox'] = [
            'label' => esc_html__('Contact Form 7 Metabox', 'wp-sms'),
            'value' => $yesNo($cf7Metabox),
            'debug' => $yesNoDebug($cf7Metabox),
        ];

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

            $eddNotifyOrderRaw                   = Option::getOption('edd_notify_order_enable', true);
            $settings['edd_notify_order_enable'] = [
                'label' => esc_html__('EDD New Order Notifications', 'wp-sms'),
                'value' => $yesNo($eddNotifyOrderRaw),
                'debug' => $yesNoDebug($eddNotifyOrderRaw),
            ];

            $eddNotifyCustomerRaw                   = Option::getOption('edd_notify_customer_enable', true);
            $settings['edd_notify_customer_enable'] = [
                'label' => esc_html__('EDD Customer Notifications', 'wp-sms'),
                'value' => $yesNo($eddNotifyCustomerRaw),
                'debug' => $yesNoDebug($eddNotifyCustomerRaw),
            ];
        }

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

            $jobDisplayMobileRaw                   = Option::getOption('job_display_mobile_number', true);
            $settings['job_display_mobile_number'] = [
                'label' => esc_html__('Display Mobile Number', 'wp-sms'),
                'value' => $yesNo($jobDisplayMobileRaw),
                'debug' => $yesNoDebug($jobDisplayMobileRaw),
            ];

            $jobNotifyStatusRaw            = Option::getOption('job_notify_status', true);
            $settings['job_notify_status'] = [
                'label' => esc_html__('New Job Notification', 'wp-sms'),
                'value' => $yesNo($jobNotifyStatusRaw),
                'debug' => $yesNoDebug($jobNotifyStatusRaw),
            ];

            $jobEmployerNotifRaw                    = Option::getOption('job_notify_employer_status', true);
            $settings['job_notify_employer_status'] = [
                'label' => esc_html__('Employer Notification', 'wp-sms'),
                'value' => $yesNo($jobEmployerNotifRaw),
                'debug' => $yesNoDebug($jobEmployerNotifRaw),
            ];
        }

        if (class_exists('Awesome_Support') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['awesome_support_integration'] = [
                'label' => esc_html__('Awesome Support Integration', 'wp-sms'),
                'value' => esc_html__('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $asOpenRaw                                = Option::getOption('as_notify_open_ticket_status', true);
            $settings['as_notify_open_ticket_status'] = [
                'label' => esc_html__('New Ticket Notification', 'wp-sms'),
                'value' => $yesNo($asOpenRaw),
                'debug' => $yesNoDebug($asOpenRaw),
            ];

            $asAdminReplyRaw                                 = Option::getOption('as_notify_admin_reply_ticket_status', true);
            $settings['as_notify_admin_reply_ticket_status'] = [
                'label' => esc_html__('Admin Reply Notification', 'wp-sms'),
                'value' => $yesNo($asAdminReplyRaw),
                'debug' => $yesNoDebug($asAdminReplyRaw),
            ];

            $asUserReplyRaw                                 = Option::getOption('as_notify_user_reply_ticket_status', true);
            $settings['as_notify_user_reply_ticket_status'] = [
                'label' => esc_html__('User Reply Notification', 'wp-sms'),
                'value' => $yesNo($asUserReplyRaw),
                'debug' => $yesNoDebug($asUserReplyRaw),
            ];

            $asUpdateRaw                                = Option::getOption('as_notify_update_ticket_status', true);
            $settings['as_notify_update_ticket_status'] = [
                'label' => esc_html__('Status Update Notification', 'wp-sms'),
                'value' => $yesNo($asUpdateRaw),
                'debug' => $yesNoDebug($asUpdateRaw),
            ];

            $asCloseRaw                                = Option::getOption('as_notify_close_ticket_status', true);
            $settings['as_notify_close_ticket_status'] = [
                'label' => esc_html__('Ticket Close Notification', 'wp-sms'),
                'value' => $yesNo($asCloseRaw),
                'debug' => $yesNoDebug($asCloseRaw),
            ];
        }

        if (function_exists('um_user') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['ultimate_member_integration'] = [
                'label' => esc_html__('Ultimate Member Integration', 'wp-sms'),
                'value' => esc_html__('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $umApprovalRaw                          = Option::getOption('um_send_sms_after_approval', true);
            $settings['um_send_sms_after_approval'] = [
                'label' => esc_html__('Ultimate Member User Approval Notification', 'wp-sms'),
                'value' => $yesNo($umApprovalRaw),
                'debug' => $yesNoDebug($umApprovalRaw),
            ];
        }

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

        if (function_exists('buddypress') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['buddypress_integration'] = [
                'label' => esc_html__('BuddyPress Integration', 'wp-sms'),
                'value' => esc_html__('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $bpWelcomeRaw                               = Option::getOption('bp_welcome_notification_enable', true);
            $settings['bp_welcome_notification_enable'] = [
                'label' => esc_html__('BuddyPress Welcome SMS', 'wp-sms'),
                'value' => $yesNo($bpWelcomeRaw),
                'debug' => $yesNoDebug($bpWelcomeRaw),
            ];

            $bpMentionRaw                  = Option::getOption('bp_mention_enable', true);
            $settings['bp_mention_enable'] = [
                'label' => esc_html__('BuddyPress Mention Alerts', 'wp-sms'),
                'value' => $yesNo($bpMentionRaw),
                'debug' => $yesNoDebug($bpMentionRaw),
            ];

            $bpPmRaw                               = Option::getOption('bp_private_message_enable', true);
            $settings['bp_private_message_enable'] = [
                'label' => esc_html__('BuddyPress Private Messages', 'wp-sms'),
                'value' => $yesNo($bpPmRaw),
                'debug' => $yesNoDebug($bpPmRaw),
            ];

            $bpActReplyRaw                           = Option::getOption('bp_comments_activity_enable', true);
            $settings['bp_comments_activity_enable'] = [
                'label' => esc_html__('BuddyPress Activity Replies', 'wp-sms'),
                'value' => $yesNo($bpActReplyRaw),
                'debug' => $yesNoDebug($bpActReplyRaw),
            ];

            $bpCmtReplyRaw                        = Option::getOption('bp_comments_reply_enable', true);
            $settings['bp_comments_reply_enable'] = [
                'label' => esc_html__('BuddyPress Comment Replies', 'wp-sms'),
                'value' => $yesNo($bpCmtReplyRaw),
                'debug' => $yesNoDebug($bpCmtReplyRaw),
            ];
        }

        if (class_exists('WooCommerce') && LicenseHelper::isPluginLicensedAndActive()) {
            $settings['woocommerce_integration'] = [
                'label' => esc_html__('WooCommerce Integration', 'wp-sms'),
                'value' => esc_html__('Enabled', 'wp-sms'),
                'debug' => 'Enabled',
            ];

            $wcMetaBoxRaw                   = Option::getOption('wc_meta_box_enable', true);
            $settings['wc_meta_box_enable'] = [
                'label' => esc_html__('WooCommerce Order Meta Box', 'wp-sms'),
                'value' => $yesNo($wcMetaBoxRaw),
                'debug' => $yesNoDebug($wcMetaBoxRaw),
            ];

            $wcNotifyProductRaw                   = Option::getOption('wc_notify_product_enable', true);
            $settings['wc_notify_product_enable'] = [
                'label' => esc_html__('WooCommerce New Product Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyProductRaw),
                'debug' => $yesNoDebug($wcNotifyProductRaw),
            ];

            $wcNotifyOrderRaw                   = Option::getOption('wc_notify_order_enable', true);
            $settings['wc_notify_order_enable'] = [
                'label' => esc_html__('WooCommerce New Order Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyOrderRaw),
                'debug' => $yesNoDebug($wcNotifyOrderRaw),
            ];

            $wcNotifyCustomerRaw                   = Option::getOption('wc_notify_customer_enable', true);
            $settings['wc_notify_customer_enable'] = [
                'label' => esc_html__('WooCommerce Customer Order Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyCustomerRaw),
                'debug' => $yesNoDebug($wcNotifyCustomerRaw),
            ];

            $wcNotifyStockRaw                   = Option::getOption('wc_notify_stock_enable', true);
            $settings['wc_notify_stock_enable'] = [
                'label' => esc_html__('WooCommerce Low Stock Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyStockRaw),
                'debug' => $yesNoDebug($wcNotifyStockRaw),
            ];

            $wcCheckoutConfirmRaw                                  = Option::getOption('wc_checkout_confirmation_checkbox_enabled', true);
            $settings['wc_checkout_confirmation_checkbox_enabled'] = [
                'label' => esc_html__('WooCommerce Checkout Confirmation Checkbox', 'wp-sms'),
                'value' => $yesNo($wcCheckoutConfirmRaw),
                'debug' => $yesNoDebug($wcCheckoutConfirmRaw),
            ];

            $wcNotifyStatusRaw                   = Option::getOption('wc_notify_status_enable', true);
            $settings['wc_notify_status_enable'] = [
                'label' => esc_html__('WooCommerce Order Status Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyStatusRaw),
                'debug' => $yesNoDebug($wcNotifyStatusRaw),
            ];

            $wcNotifyByStatusRaw                    = Option::getOption('wc_notify_by_status_enable', true);
            $settings['wc_notify_by_status_enable'] = [
                'label' => esc_html__('WooCommerce Specific Order Status Notification', 'wp-sms'),
                'value' => $yesNo($wcNotifyByStatusRaw),
                'debug' => $yesNoDebug($wcNotifyByStatusRaw),
            ];

            $statusContent                           = Option::getOption('wc_notify_by_status_content', true);
            $statusCount                             = is_array($statusContent) ? count($statusContent) : 0;
            $settings['wc_notify_by_status_content'] = [
                'label' => esc_html__('WooCommerce Configured Status Notifications', 'wp-sms'),
                'value' => $statusCount,
                'debug' => (string)$statusCount,
            ];
        }

        if ($pluginHandler->isPluginActive('wp-sms-woocommerce-pro')) {
            $settings = array_merge($settings, $this->getWooProSettings());
        }

        if ($pluginHandler->isPluginActive('wp-sms-two-way')) {
            $twoWay   = self::getTwoWayIntegrationSetting() ?? [];
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
            return array(
                'value' => esc_html__('Not Set', 'wp-sms'),
                'debug' => 'Not Set'
            );
        }

        $partsVal = array();
        $partsDbg = array();

        // Days
        if (!empty($raw['days']) && $raw['days'] !== '0') {
            $n          = (int)$raw['days'];
            $partsVal[] = sprintf(
            /* translators: %s: number of days */
                _n('%s day', '%s days', $n, 'wp-sms'),
                $raw['days']
            );
            $partsDbg[] = $raw['days'] . ' ' . ($n === 1 ? 'day' : 'days');
        }

        // Hours
        if (!empty($raw['hours']) && $raw['hours'] !== '0') {
            $n          = (int)$raw['hours'];
            $partsVal[] = sprintf(
            /* translators: %s: number of hours */
                _n('%s hour', '%s hours', $n, 'wp-sms'),
                $raw['hours']
            );
            $partsDbg[] = $raw['hours'] . ' ' . ($n === 1 ? 'hour' : 'hours');
        }

        // Minutes
        if (!empty($raw['minutes']) && $raw['minutes'] !== '0') {
            $n          = (int)$raw['minutes'];
            $partsVal[] = sprintf(
            /* translators: %s: number of minutes */
                _n('%s minute', '%s minutes', $n, 'wp-sms'),
                $raw['minutes']
            );
            $partsDbg[] = $raw['minutes'] . ' ' . ($n === 1 ? 'minute' : 'minutes');
        }

        if (empty($partsVal)) {
            return array(
                'value' => esc_html__('Not Set', 'wp-sms'),
                'debug' => 'Not Set'
            );
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

        $cartStatus                                       = $formatGeneric($woo('cart_abandonment_recovery_status'));
        $settings['woo_cart_abandonment_recovery_status'] = [
            'label' => $labels['cart_abandonment_recovery_status'],
            'value' => $cartStatus['value'],
            'debug' => $cartStatus['debug'],
        ];

        $cartAbandonmentThreshold                   = $this->formatDurationValue($woo('cart_abandonment_threshold'));
        $settings['woo_cart_abandonment_threshold'] = [
            'label' => $labels['cart_abandonment_threshold'],
            'value' => $cartAbandonmentThreshold['value'],
            'debug' => $cartAbandonmentThreshold['debug'],
        ];

        $skipOverwrite                                         = ($woo('cart_overwrite_number_during_checkout') === 'skip');
        $settings['woo_cart_overwrite_number_during_checkout'] = [
            'label' => $labels['cart_overwrite_number_during_checkout'],
            'value' => $skipOverwrite ? esc_html__('Do not update', 'wp-sms') : esc_html__('Update phone number', 'wp-sms'),
            'debug' => $skipOverwrite ? 'Do not update' : 'Update phone number',
        ];

        $cartCreateCoupon                   = $formatGeneric($woo('cart_create_coupon'));
        $settings['woo_cart_create_coupon'] = [
            'label' => $labels['cart_create_coupon'],
            'value' => $cartCreateCoupon['value'],
            'debug' => $cartCreateCoupon['debug'],
        ];

        $cartSmsTimeInterval                                     = $this->formatDurationValue($woo('cart_abandonment_send_sms_time_interval'));
        $settings['woo_cart_abandonment_send_sms_time_interval'] = [
            'label' => $labels['cart_abandonment_send_sms_time_interval'],
            'value' => $cartSmsTimeInterval['value'],
            'debug' => $cartSmsTimeInterval['debug'],
        ];

        $loginWithSmsStatus                    = $formatGeneric($woo('login_with_sms_status'));
        $settings['woo_login_with_sms_status'] = [
            'label' => $labels['login_with_sms_status'],
            'value' => $loginWithSmsStatus['value'],
            'debug' => $loginWithSmsStatus['debug'],
        ];

        $loginWithSmsForgotStatus                     = $formatGeneric($woo('login_with_sms_forgot_status'));
        $settings['woo_login_with_sms_forgot_status'] = [
            'label' => $labels['login_with_sms_forgot_status'],
            'value' => $loginWithSmsForgotStatus['value'],
            'debug' => $loginWithSmsForgotStatus['debug'],
        ];

        $resetPasswordStatus                   = $formatGeneric($woo('reset_password_status'));
        $settings['woo_reset_password_status'] = [
            'label' => $labels['reset_password_status'],
            'value' => $resetPasswordStatus['value'],
            'debug' => $resetPasswordStatus['debug'],
        ];

        $checkoutConfirmationCheckbox                           = $formatGeneric($woo('checkout_confirmation_checkbox_enabled'));
        $settings['woo_checkout_confirmation_checkbox_enabled'] = [
            'label' => $labels['checkout_confirmation_checkbox_enabled'],
            'value' => $checkoutConfirmationCheckbox['value'],
            'debug' => $checkoutConfirmationCheckbox['debug'],
        ];

        $checkoutMobileVerification                           = $formatGeneric($woo('checkout_mobile_verification_enabled'));
        $settings['woo_checkout_mobile_verification_enabled'] = [
            'label' => $labels['checkout_mobile_verification_enabled'],
            'value' => $checkoutMobileVerification['value'],
            'debug' => $checkoutMobileVerification['debug'],
        ];

        $registerUserViaSms                           = $woo('register_user_via_sms_status');
        $isEnabled                                    = in_array($registerUserViaSms, $yesVals, true);
        $settings['woo_register_user_via_sms_status'] = [
            'label' => $labels['register_user_via_sms_status'],
            'value' => $isEnabled ? esc_html__('Enabled', 'wp-sms') : esc_html__('Disabled', 'wp-sms'),
            'debug' => $isEnabled ? 'Enabled' : 'Disabled',
        ];

        $checkoutMobileVerificationSkipLoggedIn                              = $woo('checkout_mobile_verification_skip_logged_in_enabled');
        $isEnabled                                                           = in_array($checkoutMobileVerificationSkipLoggedIn, $yesVals, true);
        $settings['woo_checkout_mobile_verification_skip_logged_in_enabled'] = [
            'label' => $labels['checkout_mobile_verification_skip_logged_in_enabled'],
            'value' => $isEnabled ? esc_html__('Enabled', 'wp-sms') : esc_html__('Disabled', 'wp-sms'),
            'debug' => $isEnabled ? 'Enabled' : 'Disabled',
        ];

        $checkoutMobileVerificationCountries                              = $woo('checkout_mobile_verification_countries_whitelist');
        $hasCountries                                                     = is_array($checkoutMobileVerificationCountries) && !empty($checkoutMobileVerificationCountries);
        $joinedCountries                                                  = $hasCountries ? implode(', ', $checkoutMobileVerificationCountries) : null;
        $settings['woo_checkout_mobile_verification_countries_whitelist'] = [
            'label' => $labels['checkout_mobile_verification_countries_whitelist'],
            'value' => $hasCountries ? $joinedCountries : esc_html__('Not Set', 'wp-sms'),
            'debug' => $hasCountries ? $joinedCountries : 'Not Set',
        ];

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

        $bookingOptions = array(
            'booking_calendar_notif_admin_new_booking'              => esc_html__('Booking Calendar: Admin New Booking Notification', 'wp-sms'),
            'booking_calendar_notif_customer_new_booking'           => esc_html__('Booking Calendar: Customer New Booking Notification', 'wp-sms'),
            'booking_calendar_notif_customer_booking_approved'      => esc_html__('Booking Calendar: Booking Approved Notification', 'wp-sms'),
            'booking_calendar_notif_customer_booking_cancelled'     => esc_html__('Booking Calendar: Booking Cancelled Notification', 'wp-sms'),
            'bookingpress_notif_admin_approved_appointment'         => esc_html__('BookingPress: Admin Approved Appointment', 'wp-sms'),
            'bookingpress_notif_customer_approved_appointment'      => esc_html__('BookingPress: Customer Approved Appointment', 'wp-sms'),
            'bookingpress_notif_admin_pending_appointment'          => esc_html__('BookingPress: Admin Pending Appointment', 'wp-sms'),
            'bookingpress_notif_customer_pending_appointment'       => esc_html__('BookingPress: Customer Pending Appointment', 'wp-sms'),
            'bookingpress_notif_admin_rejected_appointment'         => esc_html__('BookingPress: Admin Rejected Appointment', 'wp-sms'),
            'bookingpress_notif_customer_rejected_appointment'      => esc_html__('BookingPress: Customer Rejected Appointment', 'wp-sms'),
            'bookingpress_notif_admin_cancelled_appointment'        => esc_html__('BookingPress: Admin Cancelled Appointment', 'wp-sms'),
            'bookingpress_notif_customer_cancelled_appointment'     => esc_html__('BookingPress: Customer Cancelled Appointment', 'wp-sms'),
            'woo_appointments_notif_admin_new_appointment'          => esc_html__('Woo Appointments: Admin New Appointment', 'wp-sms'),
            'woo_appointments_notif_admin_cancelled_appointment'    => esc_html__('Woo Appointments: Admin Cancelled Appointment', 'wp-sms'),
            'woo_appointments_notif_customer_cancelled_appointment' => esc_html__('Woo Appointments: Customer Cancelled Appointment', 'wp-sms'),
            'woo_appointments_notif_admin_rescheduled_appointment'  => esc_html__('Woo Appointments: Admin Rescheduled Appointment', 'wp-sms'),
            'woo_appointments_notif_customer_confirmed_appointment' => esc_html__('Woo Appointments: Customer Confirmed Appointment', 'wp-sms'),
            'woo_bookings_notif_admin_new_booking'                  => esc_html__('Woo Bookings: Admin New Booking', 'wp-sms'),
            'woo_bookings_notif_admin_cancelled_booking'            => esc_html__('Woo Bookings: Admin Cancelled Booking', 'wp-sms'),
            'woo_bookings_notif_customer_cancelled_booking'         => esc_html__('Woo Bookings: Customer Cancelled Booking', 'wp-sms'),
            'woo_bookings_notif_customer_confirmed_booking'         => esc_html__('Woo Bookings: Customer Confirmed Booking', 'wp-sms'),
        );

        foreach ($bookingOptions as $key => $label) {
            $optionValue = OptionUtil::get($key);

            if (is_bool($optionValue) || $optionValue === '0' || $optionValue === '1') {
                $isEnabled = (bool)$optionValue;
                $value     = $isEnabled ? esc_html__('Enabled', 'wp-sms') : esc_html__('Disabled', 'wp-sms');
                $debug     = $isEnabled ? 'Enabled' : 'Disabled';
            } else {
                $value = is_string($optionValue) ? $optionValue : esc_html__('Not Set', 'wp-sms');
                $debug = is_string($optionValue) ? $optionValue : 'Not Set';
            }

            $settings[$key] = [
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

        $fluentOptions = [
            'fluent_crm_notif_contact_subscribed'    => esc_html__('FluentCRM: Contact Subscribed Notification', 'wp-sms'),
            'fluent_crm_notif_contact_unsubscribed'  => esc_html__('FluentCRM: Contact Unsubscribed Notification', 'wp-sms'),
            'fluent_crm_notif_contact_pending'       => esc_html__('FluentCRM: Contact Pending Notification', 'wp-sms'),
            'fluent_support_notif_ticket_created'    => esc_html__('Fluent Support: Ticket Created', 'wp-sms'),
            'fluent_support_notif_customer_response' => esc_html__('Fluent Support: Customer Response', 'wp-sms'),
            'fluent_support_notif_agent_assigned'    => esc_html__('Fluent Support: Agent Assigned', 'wp-sms'),
            'fluent_support_notif_ticket_closed'     => esc_html__('Fluent Support: Ticket Closed', 'wp-sms'),
        ];

        foreach ($fluentOptions as $key => $label) {
            $optionValue = OptionUtil::get($key);
            $isToggle    = (is_bool($optionValue) || $optionValue === '0' || $optionValue === '1');
            $value       = $isToggle ? ($optionValue ? esc_html__('Enabled', 'wp-sms') : esc_html__('Disabled', 'wp-sms')) : (is_string($optionValue) ? $optionValue : esc_html__('Not Set', 'wp-sms'));
            $debug       = $isToggle ? ($optionValue ? 'Enabled' : 'Disabled') : (is_string($optionValue) ? $optionValue : 'Not Set');

            $settings[$key] = [
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

        $membershipsOptions = [
            'pmpro_notif_user_registered'       => esc_html__('Paid Memberships Pro: User Registered Notification', 'wp-sms'),
            'pmpro_notif_membership_confirmed'  => esc_html__('Paid Memberships Pro: Membership Confirmed Notification', 'wp-sms'),
            'pmpro_notif_membership_cancelled'  => esc_html__('Paid Memberships Pro: Membership Cancelled Notification', 'wp-sms'),
            'pmpro_notif_membership_expired'    => esc_html__('Paid Memberships Pro: Membership Expired Notification', 'wp-sms'),
            'sm_notif_admin_user_registered'    => esc_html__('Simple Membership: Admin Notified on User Registration', 'wp-sms'),
            'sm_notif_membership_level_updated' => esc_html__('Simple Membership: Membership Level Updated', 'wp-sms'),
            'sm_notif_membership_expired'       => esc_html__('Simple Membership: Membership Expired', 'wp-sms'),
            'sm_notif_membership_cancelled'     => esc_html__('Simple Membership: Membership Cancelled', 'wp-sms'),
            'sm_notif_admin_payment_recieved'   => esc_html__('Simple Membership: Payment Received (Admin)', 'wp-sms'),
        ];

        foreach ($membershipsOptions as $key => $label) {
            $optionValue = OptionUtil::get($key);
            $isToggle    = (is_bool($optionValue) || $optionValue === '0' || $optionValue === '1');
            $value       = $isToggle ? ($optionValue ? esc_html__('Enabled', 'wp-sms') : esc_html__('Disabled', 'wp-sms')) : (is_string($optionValue) ? $optionValue : esc_html__('Not Set', 'wp-sms'));
            $debug       = $isToggle ? ($optionValue ? 'Enabled' : 'Disabled') : (is_string($optionValue) ? $optionValue : 'Not Set');

            $settings[$key] = [
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

        $twoWayOptions = [
            'notif_new_inbox_message' => esc_html__('Two-Way: Forward Incoming SMS to Admin', 'wp-sms'),
            'email_new_inbox_message' => esc_html__('Two-Way: Forward Incoming SMS to Email', 'wp-sms'),
        ];

        foreach ($twoWayOptions as $key => $label) {
            $optionValue = OptionUtil::get($key);
            $isToggle    = (is_bool($optionValue) || $optionValue === '0' || $optionValue === '1');
            $value       = $isToggle ? ($optionValue ? esc_html__('Enabled', 'wp-sms') : esc_html__('Disabled', 'wp-sms')) : (is_string($optionValue) ? $optionValue : esc_html__('Not Set', 'wp-sms'));
            $debug       = $isToggle ? ($optionValue ? 'Enabled' : 'Disabled') : (is_string($optionValue) ? $optionValue : 'Not Set');

            $settings[$key] = [
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