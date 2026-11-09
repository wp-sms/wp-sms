<?php

namespace unit;

use ReflectionMethod;
use WP_SMS;
use WP_UnitTestCase;

/**
 * The Persian strings shipped in languages/ load on a Persian site, and the
 * WordPress.org language pack wins where both have a string.
 */
class TextDomainTest extends WP_UnitTestCase
{
    private $packFile;

    public function tearDown(): void
    {
        remove_all_filters('pre_determine_locale');
        remove_all_filters('plugin_locale');
        if ($this->packFile && file_exists($this->packFile)) {
            unlink($this->packFile);
        }
        unload_textdomain('wp-sms');
        parent::tearDown();
    }

    private function loadPersian(): void
    {
        add_filter('pre_determine_locale', static function () {
            return 'fa_IR';
        });
        unload_textdomain('wp-sms');
        $method = new ReflectionMethod(WP_SMS::class, 'loadTextDomain');
        $method->setAccessible(true);
        $method->invoke(WP_SMS::get_instance());
    }

    public function testShippedPersianStringsLoad(): void
    {
        $this->loadPersian();

        $this->assertSame('شماره خط شما در پنل کاوه‌نگار.', __('Your line number from the Kavenegar panel.', 'wp-sms'));
    }

    public function testLanguagePackWinsOverShippedStrings(): void
    {
        // A pack .mo built from a one-string .po: the same string with another translation.
        $po = wp_tempnam('wp-sms-pack') . '.po';
        file_put_contents($po, "msgid \"\"\nmsgstr \"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"Your line number from the Kavenegar panel.\"\nmsgstr \"از بسته وردپرس\"\n");
        wp_mkdir_p(WP_LANG_DIR . '/plugins');
        $this->packFile = WP_LANG_DIR . '/plugins/wp-sms-fa_IR.mo';

        require_once ABSPATH . WPINC . '/pomo/po.php';
        $translations = new \PO();
        $translations->import_from_file($po);
        $mo = new \MO();
        $mo->merge_with($translations);
        $mo->export_to_file($this->packFile);
        unlink($po);

        $this->loadPersian();

        $this->assertSame('از بسته وردپرس', __('Your line number from the Kavenegar panel.', 'wp-sms'));
        // Strings the pack does not have still come from the shipped file.
        $this->assertSame('آزمایش اتصال', __('Test Connection', 'wp-sms'));
    }

    public function testPluginLocaleControlsExplicitCatalogLoads(): void
    {
        add_filter('plugin_locale', static function ($locale, $domain) {
            return $domain === 'wp-sms' ? 'en_US' : $locale;
        }, 10, 2);

        $this->loadPersian();

        $this->assertSame('Test Connection', __('Test Connection', 'wp-sms'));
    }

    public function testLanguagePackWinsOnWordPressBeforeFortySix(): void
    {
        $po = wp_tempnam('wp-sms-pack') . '.po';
        file_put_contents($po, "msgid \"\"\nmsgstr \"Content-Type: text/plain; charset=UTF-8\\n\"\n\nmsgid \"Your line number from the Kavenegar panel.\"\nmsgstr \"از بسته وردپرس\"\n");
        wp_mkdir_p(WP_LANG_DIR . '/plugins');
        $this->packFile = WP_LANG_DIR . '/plugins/wp-sms-fa_IR.mo';

        require_once ABSPATH . WPINC . '/pomo/po.php';
        $translations = new \PO();
        $translations->import_from_file($po);
        $mo = new \MO();
        $mo->merge_with($translations);
        $mo->export_to_file($this->packFile);
        unlink($po);

        add_filter('pre_determine_locale', static function () {
            return 'fa_IR';
        });

        unload_textdomain('wp-sms');
        load_textdomain('wp-sms', WP_SMS_DIR . 'languages/wp-sms-fa_IR.mo');

        $relativeFile = WP_PLUGIN_DIR . '/' . dirname(plugin_basename(WP_SMS_MAIN_FILE)) . '/languages/wp-sms-fa_IR.mo';
        $override = static function ($loaded, $domain, $mofile) use ($relativeFile) {
            return $domain === 'wp-sms' && $mofile === $relativeFile ? true : $loaded;
        };
        add_filter('override_load_textdomain', $override, 10, 3);

        $oldVersion = $GLOBALS['wp_version'];
        $GLOBALS['wp_version'] = '4.5';

        try {
            $method = new ReflectionMethod(WP_SMS::class, 'loadTextDomain');
            $method->setAccessible(true);
            $method->invoke(WP_SMS::get_instance());
        } finally {
            $GLOBALS['wp_version'] = $oldVersion;
            remove_filter('override_load_textdomain', $override, 10);
        }

        $this->assertSame('از بسته وردپرس', __('Your line number from the Kavenegar panel.', 'wp-sms'));
        $this->assertSame('آزمایش اتصال', __('Test Connection', 'wp-sms'));
    }
}
