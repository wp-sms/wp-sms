<?php

namespace WP_SMS\Notice;

use WP_SMS\Helper;

class Notice
{
    /**
     * Show Admin WordPress UI Notice
     *
     * @param string $text where Show Text Notification
     * @param string $model Type Of Model from list : error / warning / success / info
     * @param boolean $close_button Check Show close Button Or false for not
     * @param boolean $echo Check Echo or return in function
     * @param string $style_extra add extra Css Style To Code
     *
     * @return string WordPress html Notice code
     * @author Mehrshad Darzi
     */
    public static function notice($text, $model = 'info', $close_button = true, $echo = true, $style_extra = 'padding:10px 0')
    {
        $text = '
        <div class="notice notice-' . $model . '' . ($close_button === true ? " is-dismissible" : "") . '">
           <div style="' . $style_extra . '">' . $text . '</div>
        </div>
        ';

        if ($echo) {
            echo $text;
        } else {
            return $text;
        }

        echo Helper::loadTemplate('admin/notice.php', [
            'notice' => $notice,
            'nonce'  => $nonce,
            'type'   => $type
        ]);
    }

    /**
     * Add Flash Admin WordPress UI Notice (One-time)
     *
     * @param string $text where Show Text Notification
     * @param string $model Type Of Model from list : error / warning / success / info
     * @param string $redirect Url for redirect to new page
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
