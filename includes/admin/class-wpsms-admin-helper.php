<?php

namespace WP_SMS\Admin;

class Helper
{

    /**
     * Show Admin Wordpress Ui Notice
     *
     * @param string $text where Show Text Notification
     * @param string $model Type Of Model from list : error / warning / success / info
     * @param boolean $close_button Check Show close Button Or false for not
     * @param boolean $echo Check Echo or return in function
     * @param string $style_extra add extra Css Style To Code
     *
     * @return string Wordpress html Notice code
     * @author Mehrshad Darzi
     */
    public static function notice($text, $model = "info", $close_button = true, $echo = true, $style_extra = 'padding:12px;')
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
     * Get WP users by role
     *
     * @param string $role
     * @param bool $count
     *
     * @return array|int
     */
    public static function getUsersList($role, $count = false)
    {
        // Check the WC mobile enabled or not
        $args = array(
            'meta_query'  => array(
                array(
                    'key'     => 'mobile',
                    'value'   => '',
                    'compare' => '!=',
                ),
            ),
            'count_total' => 'false',
            'role'        => $role,
            'fields'      => $count ? 'ID' : 'all'
        );

        $customers = get_users($args);

        if ($count) {
            return count($customers);
        }

        return $customers;
    }

}