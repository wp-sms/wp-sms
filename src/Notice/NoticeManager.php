<?php

namespace WP_SMS\Notice;

class NoticeManager
{

    /**
     * @var array
     */
    private $notices = [
        'WooCommerceMobileField' => Notices\WooCommerceMobileField::class,
        'TwoFactorMobileField'   => Notices\TwoFactorMobileField::class,
    ];


    /**
     * Init Notices
     *
     * @return void
     */
    public static function init()
    {
        $instance = new self;
        $instance->includeRequirements();
        $instance->loadNotices();
    }

    /**
     * Include requirements
     *
     * @return void
     */
    private function includeRequirements()
    {
        require_once WP_SMS_DIR . 'src/Notice/AbstractNotice.php';
    }

    /**
     * Require files in Notices folder
     *
     * @return void
     */
    private function loadNotices()
    {
        foreach ($this->notices as $fileName => $notice) {
            $file = WP_SMS_DIR . "src/Notice/Notices/{$fileName}.php";

            if (file_exists($file)) {
                require_once $file;
            }

            if (is_subclass_of($notice, AbstractNotice::class)) {
                (new $notice)->register();
            }
        }
    }
}
