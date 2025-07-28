<?php

namespace WP_SMS\Admin;

use WP_SMS\Admin\AjaxOptionUpdater;
use WP_SMS\Admin\NoticeHandler\Notice;

class AdminManager
{
    public function __construct()
    {
        $this->initFooterModifier();
        $this->initNoticeHandler();
        $this->initAjaxOptionUpdater();
        $this->initSiteHealthInfo();
    }

    private function initFooterModifier()
    {
        add_filter('admin_footer_text', array($this, 'modifyAdminFooterText'), 999);
        add_filter('update_footer', array($this, 'modifyAdminUpdateFooter'), 999);
    }

    private function initNoticeHandler()
    {
        add_action('admin_notices', [Notice::class, 'displayNotices']);
        add_action('admin_init', [Notice::class, 'handleDismissNotice']);
        add_action('admin_init', [Notice::class, 'handleGeneralNotices']);
    }

    private function initSiteHealthInfo()
    {
        // Initialize Site Health Info and register its hooks
        $siteHealthInfo = new SiteHealthInfo();
        $siteHealthInfo->register();
    }

    private function initAjaxOptionUpdater()
    {
        $optionUpdater = new AjaxOptionUpdater();
        $optionUpdater->init();
    }

    /**
     * Include footer
     */
    public function modifyAdminFooterText($text)
    {
        $screen = get_current_screen();

        if (apply_filters('wp_sms_enable_footer_text', true) && stripos($screen->id, 'wps_') !== false) {
            $text = sprintf(
                __('Please rate <strong>WP SMS</strong> <a href="%2$s" title="%3$s" target="_blank">★★★★★</a> on <a href="%2$s" target="_blank">WordPress.org</a> to help us spread the word. Thank you!', 'wp-sms'),
                esc_html__('WP SMS', 'wp-sms'),
                'https://wordpress.org/support/plugin/wp-sms/reviews/?filter=5#new-post',
                esc_html__('Rate WP SMS', 'wp-sms')
            );
        }
        return $text;
    }

    public function modifyAdminUpdateFooter($content)
    {
        $screen = get_current_screen();

        if (apply_filters('wp_sms_enable_footer_text', true) && stripos($screen->id, 'wps_') !== false) {
            global $wp_version;

            $content = sprintf('<p id="footer-upgrade" class="alignright">%s | %s %s</p>',
                esc_html__('WordPress', 'wp-sms') . ' ' . esc_html($wp_version),
                esc_html('WP SMS'),
                esc_attr(WP_SMS_VERSION)
            );
        }
        return $content;
    }
}