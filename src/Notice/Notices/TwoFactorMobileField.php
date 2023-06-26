<?php

namespace WP_SMS\Notice\Notices;

use WP_SMS\Notice\AbstractNotice;
use WP_SMS\Helper;

class TwoFactorMobileField extends AbstractNotice
{
    protected $message = 'You need to configure the Mobile field option to use login with SMS functonality.';

    /**
     * Render the notice
     *
     * @return void
     */
    public function render()
    {
        if (isset($_GET['tab']) && $_GET['tab'] == 'pro_wordpress' && $this->options['add_mobile_field'] !== 'add_mobile_field_in_profile') {
            echo Helper::loadTemplate('admin/simple-admin-notice.php', ['message' => $this->message]);
        }
    }
}
