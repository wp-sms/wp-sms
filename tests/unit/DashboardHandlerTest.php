<?php

namespace unit;

use ReflectionMethod;
use WP_SMS\Service\Assets\Handlers\DashboardHandler;
use WP_UnitTestCase;

class DashboardHandlerTest extends WP_UnitTestCase
{
    private const TRANSLATION_HANDLE = 'wsms-dashboard-i18n';

    /**
     * @var DashboardHandler|null
     */
    private $handler;

    public function tearDown(): void
    {
        if ($this->handler) {
            remove_action('admin_enqueue_scripts', [$this->handler, 'enqueue']);
        }

        wp_dequeue_script(self::TRANSLATION_HANDLE);
        wp_deregister_script(self::TRANSLATION_HANDLE);

        parent::tearDown();
    }

    public function testDashboardTranslationsUseGeneratedScriptWithWordPressI18n(): void
    {
        $this->handler = new DashboardHandler();
        $method        = new ReflectionMethod($this->handler, 'enqueueScriptTranslations');
        $method->setAccessible(true);
        $method->invoke($this->handler);

        global $wp_scripts;
        $script = $wp_scripts->registered[self::TRANSLATION_HANDLE] ?? null;

        $this->assertNotNull($script);
        $this->assertTrue(wp_script_is(self::TRANSLATION_HANDLE, 'enqueued'));
        $this->assertSame(WP_SMS_URL . 'public/dashboard/i18n-strings.js', $script->src);
        $this->assertSame(['wp-i18n'], $script->deps);
        $this->assertFalse($wp_scripts->get_data(self::TRANSLATION_HANDLE, 'group'));
        $this->assertSame('wp-sms', $script->textdomain);
    }

    public function testDashboardTranslationsUseShippedPersianJsonWithoutLanguagePack(): void
    {
        $translations = $this->printPersianTranslations();

        $this->assertNotFalse($translations);
        $this->assertStringContainsString('Insert:', $translations);
        $this->assertStringContainsString(wp_json_encode('درج:'), $translations);
    }

    public function testDashboardTranslationsLetTheWordPressOrgPackWinOverShippedStrings(): void
    {
        $this->assertSame('33e9817343444a49324a275ae3f87b89', md5('public/dashboard/i18n-strings.js'));

        $dir       = WP_LANG_DIR . '/plugins';
        $pack      = $dir . '/wp-sms-fa_IR-' . md5('public/dashboard/i18n-strings.js') . '.json';
        $unrelated = $dir . '/wp-sms-fa_IR-unrelated-script.json';
        wp_mkdir_p($dir);
        file_put_contents($pack, wp_json_encode([
            'domain'      => 'messages',
            'locale_data' => [
                'messages' => [
                    ''             => ['domain' => 'messages', 'lang' => 'fa_IR'],
                    'Success Rate' => ['نرخ موفقیت'],
                    'Insert:'      => ['از بسته وردپرس'],
                ],
            ],
        ]));
        file_put_contents($unrelated, wp_json_encode([
            'domain'      => 'messages',
            'locale_data' => [
                'messages' => [
                    ''        => ['domain' => 'messages', 'lang' => 'fa_IR'],
                    'Insert:' => ['از فایل نامرتبط'],
                ],
            ],
        ]));

        try {
            $translations = $this->printPersianTranslations();
        } finally {
            unlink($pack);
            unlink($unrelated);
        }

        $this->assertNotFalse($translations);
        // A string only the pack has.
        $this->assertStringContainsString(wp_json_encode('نرخ موفقیت'), $translations);
        // A string both have: the pack wins.
        $this->assertStringContainsString(wp_json_encode('از بسته وردپرس'), $translations);
        $this->assertStringNotContainsString(wp_json_encode('درج:'), $translations);
        // A string only the shipped file has.
        $this->assertStringContainsString(wp_json_encode('ارسال پیام'), $translations);
    }

    /**
     * Enqueue the dashboard translation anchor and print its translations for fa_IR.
     *
     * @return string|false
     */
    private function printPersianTranslations()
    {
        $this->handler = new DashboardHandler();
        $method        = new ReflectionMethod($this->handler, 'enqueueScriptTranslations');
        $method->setAccessible(true);
        $method->invoke($this->handler);

        $localeFilter = static function () {
            return 'fa_IR';
        };
        add_filter('pre_determine_locale', $localeFilter);

        try {
            return wp_scripts()->print_translations(self::TRANSLATION_HANDLE, false);
        } finally {
            remove_filter('pre_determine_locale', $localeFilter);
            remove_filter('load_script_translations', [$this->handler, 'mergeLanguagePackScriptTranslations'], 10);
        }
    }
}
