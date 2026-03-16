<?php
/**
 * Post-scoper fix: remove autoload_files entries for packages moved to packages/.
 *
 * wp-scoper moves packages from vendor/ to packages/ but doesn't clean up
 * Composer's autoload_files references, causing "file not found" errors.
 */

$vendorDir = dirname(__DIR__) . '/vendor/composer';

foreach (['autoload_files.php', 'autoload_static.php'] as $filename) {
    $file = $vendorDir . '/' . $filename;
    if (!file_exists($file)) continue;

    $content = file_get_contents($file);
    $lines = explode("\n", $content);
    $result = [];

    // Packages that wp-scoper moves to packages/ (and deletes from vendor/)
    $movedPackages = [
        'symfony/deprecation-contracts',
        'symfony/polyfill-uuid',
        'symfony/polyfill-mbstring',
        'symfony/polyfill-ctype',
        'symfony/polyfill-intl-grapheme',
        'symfony/polyfill-intl-normalizer',
        'symfony/string/Resources',
        'league/csv/src/functions_include',
    ];

    foreach ($lines as $line) {
        $skip = false;
        foreach ($movedPackages as $pkg) {
            if (str_contains($line, $pkg)) {
                $skip = true;
                break;
            }
        }
        if (!$skip) {
            $result[] = $line;
        }
    }

    file_put_contents($file, implode("\n", $result));
}

echo "Autoload files fixed.\n";
