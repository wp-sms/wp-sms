<?php

namespace WP_SMS\Notice;

abstract class AbstractNotice
{
    protected $notices = [];

    /**
     * This method is responsible to dismiss the notice and update it on option.
     *
     * @return void
     */
    public function action()
    {
        if (isset($_GET['wpsms_dismiss_notice']) && wp_verify_nonce($_GET['security'], 'wp_sms_notice')) {
            $notices_options                                = get_option('wpsms_notices');
            $notices_options[$_GET['wpsms_dismiss_notice']] = true;
            update_option('wpsms_notices', $notices_options);
        }
    }

    /**
     * Register Notice
     */
    protected function registerNotice($id, $message, $dismiss = false, $url = false)
    {
        $this->notices[] = [
            'id'      => $id,
            'message' => $message,
            'dismiss' => $dismiss,
            'url'     => $url
        ];
    }
}
