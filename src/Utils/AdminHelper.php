<?php

namespace WP_SMS\Utils;

class AdminHelper
{
    /**
     * Date-Related Helpers
     */

    // Convert a number of days into a date range starting from today
    public static function createDateRangeOptions($page_link = false)
    {
        $date_range = array(
            10  => __('10 Days', 'wp-sms'),
            20  => __('20 Days', 'wp-sms'),
            30  => __('30 Days', 'wp-sms'),
            60  => __('2 Months', 'wp-sms'),
            90  => __('3 Months', 'wp-sms'),
            180 => __('6 Months', 'wp-sms'),
            270 => __('9 Months', 'wp-sms'),
            365 => __('1 Year', 'wp-sms')
        );

        $first_day = Helper::get_date_install_plugin();
        if ($first_day !== false) {
            $days = (int) TimeZone::getNumberDayBetween($first_day);
            if (!isset($date_range[$days])) {
                $date_range[$days] = __('All', 'wp-sms');
            }
        }

        $list = [];
        foreach ($date_range as $days => $label) {
            $link = add_query_arg(
                ['from' => TimeZone::getTimeAgo($days), 'to' => TimeZone::getCurrentDate('Y-m-d')],
                $page_link ?: remove_query_arg(['from', 'to'])
            );

            $list[$days] = [
                'title' => $label,
                'link' => esc_url($link),
                'active' => self::isActiveDateRange($days),
            ];
        }

        return $list;
    }

    public static function isActiveDateRange($days)
    {
        $request = self::validateDateRequest();
        if (!$request['status']) {
            return false;
        }

        $keys = array_keys($request['days']);
        return reset($keys) === TimeZone::getTimeAgo($days) && end($keys) === TimeZone::getCurrentDate('Y-m-d');
    }

    public static function validateDateRequest()
    {
        $default_days = apply_filters('wp_statistics_days_ago_request', 30);

        if (!isset($_GET['from']) && !isset($_GET['to'])) {
            return [
                'status' => true,
                'days' => TimeZone::getListDays(['from' => TimeZone::getTimeAgo($default_days)]),
                'type' => 'ago',
            ];
        }

        $from = sanitize_text_field($_GET['from'] ?? '');
        $to = sanitize_text_field($_GET['to'] ?? '');

        if (!$from || !$to || !TimeZone::isValidDate($from) || !TimeZone::isValidDate($to)) {
            return ['status' => false, 'message' => __('Invalid date request.', 'wp-sms')];
        }

        return [
            'status' => true,
            'days' => TimeZone::getListDays(['from' => $from, 'to' => $to]),
            'type' => $to === TimeZone::getCurrentDate('Y-m-d') ? 'ago' : 'between',
        ];
    }

    /**
     * Pagination Helpers
     */

    public static function getCurrentPage($query_var = 'pagination-page')
    {
        return isset($_GET[$query_var]) ? abs((int) $_GET[$query_var]) : 1;
    }

    public static function calculateOffset($current_page, $items_per_page)
    {
        $current_page = $current_page ?: self::getCurrentPage();
        return ($current_page * $items_per_page) - $items_per_page;
    }

    public static function renderPaginationLinks($args = [])
    {
        $defaults = [
            'item_per_page' => 25,
            'total' => 0,
            'current' => self::getCurrentPage(),
            'query_var' => 'pagination-page',
            'container' => 'pagination-wrap',
            'echo' => false,
        ];

        $args = wp_parse_args($args, $defaults);
        $total_pages = ceil($args['total'] / $args['item_per_page']);
        if ($total_pages <= 1) {
            return '';
        }

        $pagination_links = paginate_links([
            'base' => add_query_arg($args['query_var'], '%#%'),
            'total' => $total_pages,
            'current' => $args['current'],
            'prev_text' => __('Prev', 'wp-sms'),
            'next_text' => __('Next', 'wp-sms'),
            'type' => 'list',
        ]);

        $output = '<div class="' . esc_attr($args['container']) . '">' . $pagination_links . '</div>';
        if ($args['echo']) {
            echo $output; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        } else {
            return $output;
        }
    }

    /**
     * Miscellaneous Helpers
     */

    public static function renderIcon($dashicon)
    {
        return '<span class="dashicons ' . esc_attr($dashicon) . '"></span>';
    }

    public static function handleUnknownValue($value)
    {
        if (empty($value) || $value === 'Unknown') {
            return __('(not set)', 'wp-sms');
        }
        return $value;
    }

    public static function formatLocation($location = '', $region = '', $city = '')
    {
        if (!$location && !$region && !$city) {
            return __('(location not set)', 'wp-sms');
        }
        if ($location && !$region && !$city) {
            return __('(region/city not set)', 'wp-sms');
        }
        return trim("$region, $city", ', ');
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
            $template_file = WP_STATISTICS_DIR . "includes/admin/templates/{$file}.php";

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
