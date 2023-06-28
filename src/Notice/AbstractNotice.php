<?php

namespace WP_SMS\Notice;

use WP_SMS\Option;

abstract class AbstractNotice
{
    protected $notices = [];
    protected $options;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->options = Option::getOptions();
    }

    public function render()
    {
        add_action('wp_sms_settings_page', function () {
            $nonce = wp_create_nonce('wp_sms_notice');

            foreach ($this->notices as $notice) {
                // @todo Check the notice is not dismissed
                /*if () {
                    continue;
                }

                // @todo to march the current
                if () {
                    continue;
                }*/

                Notice::notice($notice, $notice); // todo
            }
        });
    }

    /**
     * This method is responsible to dismiss the notice and update it on option.
     *
     * @return void
     */
    public function action()
    {
        if (isset($_GET['wpsms_dismiss_notice']) && wp_verify_nonce('')) {

            // todo
            update_option('wpsms_notices', $this->notices);
        }
    }

    /**
     * Register Notice
     */
    protected function registerNotice($notice, $dismiss = false, $url = false)
    {
        $this->notices[] = [
            'notice'  => $notice,
            'dismiss' => $dismiss,
            'url'     => $url
        ];
    }
}
