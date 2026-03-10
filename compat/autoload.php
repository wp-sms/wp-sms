<?php

/**
 * Backward compatibility autoloader.
 *
 * Maps the legacy WP_SMS\ namespace to compat/classes/ so that old add-ons
 * that reference these classes don't cause fatal errors.
 */

defined('ABSPATH') || exit;

spl_autoload_register(function (string $class): void {
    // Handle the global WP_SMS class (no namespace)
    if ($class === 'WP_SMS') {
        $file = __DIR__ . '/classes/WP_SMS.php';
        if (file_exists($file)) {
            require $file;
        }
        return;
    }

    $prefix = 'WP_SMS\\';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $file = __DIR__ . '/classes/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
