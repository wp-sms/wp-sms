<?php

namespace WP_SMS\Admin;

use DateInterval;

class Helper
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

    /**
     * Add Flash Admin WordPress UI Notice
     *
     * @param string $text where Show Text Notification
     * @param string $model Type Of Model from list : error / warning / success / info
     * @param string $redirect Url for redirect to new page
     */
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

    /**
     * Format a date interval into human readable string
     *
     * @param DateInterval $interval
     * @see https://gist.github.com/xadim/8cf3569ee14ec943c324
     * @return string
     */
    public static function formatDateInterval(DateInterval $interval)
    {
        $result = "";
        if ($interval->y) {
            $result .= $interval->format("%y ".__("years ", "wp-sms"));
        }
        if ($interval->m) {
            $result .= $interval->format("%m ".__("months ", "wp-sms"));
        }
        if ($interval->d) {
            $result .= $interval->format("%d ".__("days ", "wp-sms"));
        }
        if ($interval->h) {
            $result .= $interval->format("%h ".__("hours ", "wp-sms"));
        }
        if ($interval->i) {
            $result .= $interval->format("%i ".__("minutes ", "wp-sms"));
        }
        if ($interval->s) {
            $result .= $interval->format("%s ".__("seconds ", "wp-sms"));
        }

        return $result;
    }
}
