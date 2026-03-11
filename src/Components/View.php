<?php

namespace WSms\Components;

defined('ABSPATH') || exit;

/**
 * Loads PHP view templates from the views/ directory.
 *
 * @since 8.0
 */
class View
{
    /**
     * Load a view file and optionally pass data to it.
     *
     * @param string|array $view  View path(s) relative to views/ (without .php).
     * @param array        $args  Associative array of data extracted into the view scope.
     * @param bool         $return Whether to return the output instead of echoing.
     *
     * @return string|void Rendered HTML when $return is true.
     */
    public static function load($view, array $args = [], bool $return = false)
    {
        $views = is_array($view) ? $view : [$view];

        if ($return) {
            ob_start();
        }

        foreach ($views as $v) {
            $path = WP_SMS_DIR . "views/{$v}.php";

            if (!file_exists($path)) {
                continue;
            }

            if (!empty($args)) {
                extract($args);
            }

            include $path;
        }

        if ($return) {
            return ob_get_clean();
        }
    }
}
