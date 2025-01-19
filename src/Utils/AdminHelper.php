<?php

namespace WP_SMS\Utils;

use WP_STATISTICS\TimeZone;

class AdminHelper
{

    public static function validateDateRequest()
    {
        $default_days = apply_filters('wp_statistics_days_ago_request', 30);

        if (!isset($_GET['from']) && !isset($_GET['to'])) {
            return [
                'status' => true,
                'days'   => TimeZone::getListDays(['from' => TimeZone::getTimeAgo($default_days)]),
                'type'   => 'ago',
            ];
        }

        $from = sanitize_text_field($_GET['from'] ?? '');
        $to   = sanitize_text_field($_GET['to'] ?? '');

        if (!$from || !$to || !TimeZone::isValidDate($from) || !TimeZone::isValidDate($to)) {
            return ['status' => false, 'message' => __('Invalid date request.', 'wp-sms')];
        }

        return [
            'status' => true,
            'days'   => TimeZone::getListDays(['from' => $from, 'to' => $to]),
            'type'   => $to === TimeZone::getCurrentDate('Y-m-d') ? 'ago' : 'between',
        ];
    }

    public static function getTemplate($template, $args = [], $return = false)
    {
        // Extract variables for the template.
        if (is_array($args) && !empty($args)) {
            extract($args, EXTR_SKIP);
        }

        // Handle single or multiple templates.
        $templates = is_string($template) ? [$template] : $template;

        $output = '';
        foreach ($templates as $file) {
            $template_file = WP_SMS_DIR . "views/templates/{$file}.php";


            if (!file_exists($template_file)) {
                continue;
            }

            // Render or return template output.
            if ($return) {
                ob_start();
                include $template_file;
                $output .= ob_get_clean();
            } else {
                include $template_file;
            }
        }

        return $return ? $output : null;
    }
}
