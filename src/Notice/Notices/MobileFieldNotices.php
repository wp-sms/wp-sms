<?php

namespace WP_SMS\Notice\Notices;

use WP_SMS\Notice\AbstractNotice;
use WP_SMS\Helper;

class MobileFieldNotices extends AbstractNotice
{
    /**
     * Render the notice
     */
    public function render()
    {
        $this->registerNotice(['name' => 'woocommerce_mobile_field', 'message' => 'You need to configure the Mobile field option in General settings to send SMS to customers.', 'dismiss' => true, 'tab' => 'pro_woocommerce']);
        $this->registerNotice(['name' => 'login_mobile_field', 'message' => 'You need to configure the Mobile field option to use login with SMS functionality.', 'dismiss' => true, 'tab' => 'pro_wordpress']);

        $nonce = wp_create_nonce('wp_sms_notice');

        foreach ($this->notices as $notice) {
            if (isset($_GET['tab']) && $_GET['tab'] == $notice['tab'] && !get_option('wpsms_hide_' . $notice['name'] . '_notice') && $this->options['add_mobile_field'] == 'disable') {
                $notice['nonce'] = $nonce;
                echo Helper::loadTemplate('admin/simple-admin-notice.php', $notice);
            }
        }
    }
}
