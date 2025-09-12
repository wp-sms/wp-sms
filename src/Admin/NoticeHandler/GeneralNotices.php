<?php

namespace WP_SMS\Admin\NoticeHandler;

use WP_SMS\Helper;
use WP_SMS\Utils\Request;
use WP_SMS\Utils\OptionUtil as Option;
use WP_SMS\Traits\TransientCacheTrait;

class GeneralNotices
{
    use TransientCacheTrait;

    /**
     * List Of Admin Notice
     *
     * @var array
     */
    private $coreNotices = [];

    /**
     * Initialize the notices.
     *
     * @return void
     */
    public function init()
    {
        $this->coreNotices = apply_filters('wp_sms_admin_notices', $this->coreNotices);

        if (!is_admin()) {
            return;
        }

        if (!Request::isFrom('ajax') && !Option::get('hide_notices') && Helper::userAccess('manage')) {
            foreach ($this->coreNotices as $notice) {
                if (method_exists($this, $notice)) {
                    call_user_func([$this, $notice]);
                }
            }
        }
    }
}
