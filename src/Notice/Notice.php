<?php

namespace WP_SMS\Notice;

use WP_SMS\Helper;

class Notice
{
    /**
     * Show Admin Notice
     */
    public static function notice($message, $type = 'info', $dismiss = true, $link = '')
    {
        echo Helper::loadTemplate('admin/notice.php', [
            'message' => $message,
            'type'    => $type,
            'dismiss' => $dismiss,
            'link'    => $link
        ]);
    }

    /**
     * Add Flash Admin WordPress UI Notice (One-time)
     */
    public static function flashNotice($text, $model = 'success', $redirect = false)
    {
        update_option('wpsms_flash_message', [
            'text'  => $text,
            'model' => $model
        ]);

        if ($redirect) {
            wp_redirect($redirect);
            exit;
        }
    }
}
