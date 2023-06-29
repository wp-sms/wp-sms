<?php

namespace WP_SMS\Admin;

/**
 * @deprecated This class is deprecated and the methods moved to main Helper
 * So we keep this file for just for backward compatibility and will be removed on future.
 */
class Helper
{
    public static function notice($text, $model = "info", $close_button = true, $echo = true, $style_extra = 'padding:10px 0')
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
    }

    public static function addFlashNotice($text, $model = "success", $redirect = false)
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
