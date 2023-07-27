<?php

namespace WP_SMS\Notice;

abstract class AbstractNotice
{
    protected $notices = [];
    protected $staticNoticeOption = 'wpsms_notices';
    protected $flashNoticeOption = 'wpsms_flash_message';

    /**
     * This method is responsible to dismiss the notice and update it on option.
     *
     * @return void
     */
    public function action()
    {
        if (isset($_GET['wpsms_dismiss_notice']) && wp_verify_nonce($_GET['security'], 'wp_sms_notice')) {
            $notices_options                                = get_option($this->staticNoticeOption);
            $notices_options[$_GET['wpsms_dismiss_notice']] = true;

            update_option($this->staticNoticeOption, $notices_options);

            // Redirect back
            wp_redirect(sanitize_url(wp_unslash($_SERVER['HTTP_REFERER'])));
            exit;
        }
    }

    /**
     * Register Notice
     */
    public function registerNotice($id, $message, $dismiss = false, $url = false)
    {
        $this->notices[$id] = [
            'message' => $message,
            'dismiss' => $dismiss,
            'url'     => $url
        ];
    }

    /**
     * Generate a link for dismissing the notice
     */
    protected static function generateNoticeLink($id, $url, $nonce)
    {
        return add_query_arg(array(
            'security'             => $nonce,
            'wpsms_dismiss_notice' => $id,
        ), admin_url($url));
    }
}
