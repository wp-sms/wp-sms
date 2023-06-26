<?php

namespace WP_SMS\Notice\Notices;

use WP_SMS\Notice\AbstractNotice;
use WP_SMS\Helper;

class WooCommerceMobileField extends AbstractNotice
{
    protected $message = 'You need to configure the Mobile field option in General settings to send SMS to customers.';

    /**
     * Render the notice
     *
     * @return void
     */
    public function render()
    {
        if (isset($_GET['tab']) && $_GET['tab'] == 'pro_woocommerce' && $this->options['add_mobile_field'] == 'disable') {
            echo Helper::loadTemplate('admin/simple-admin-notice.php', ['message' => $this->message]);
        }
    }
}
