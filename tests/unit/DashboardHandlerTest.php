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

    public function testDashboardTranslationsLoadWordPressOrgLocaleJson(): void
    {
        $this->handler = new DashboardHandler();
        $method        = new ReflectionMethod($this->handler, 'enqueueScriptTranslations');
        $method->setAccessible(true);
        $method->invoke($this->handler);

        $expectedFilename   = 'wp-sms-fa_IR-' . md5('public/dashboard/i18n-strings.js') . '.json';
        $localeFilter       = static function () {
            return 'fa_IR';
        };
        // Test checkouts are nested below the canonical plugin directory. Normalize
        // the source to the path WordPress.org hashes in a released wp-sms package.
        $relativePathFilter = static function ($relative, $src) {
            $translationPath = '/public/dashboard/i18n-strings.js';

            return substr($src, -strlen($translationPath)) === $translationPath
                ? ltrim($translationPath, '/')
                : $relative;
        };
        $translationFilter = static function ($translations, $file, $handle, $domain) use ($expectedFilename) {
            if (
                $handle !== self::TRANSLATION_HANDLE ||
                $domain !== 'wp-sms' ||
                $file !== WP_LANG_DIR . '/plugins/' . $expectedFilename
            ) {
                return $translations;
            }

            return wp_json_encode([
                'domain'      => 'messages',
                'locale_data' => [
                    'messages' => [
                        ''             => ['domain' => 'messages', 'lang' => 'fa_IR'],
                        'Success Rate' => ['نرخ موفقیت'],
                    ],
                ],
            ]);
        };

        add_filter('pre_determine_locale', $localeFilter);
        add_filter('load_script_textdomain_relative_path', $relativePathFilter, 10, 2);
        add_filter('pre_load_script_translations', $translationFilter, 10, 4);

        try {
            $translations = wp_scripts()->print_translations(self::TRANSLATION_HANDLE, false);
        } finally {
            remove_filter('pre_determine_locale', $localeFilter);
            remove_filter('load_script_textdomain_relative_path', $relativePathFilter, 10);
            remove_filter('pre_load_script_translations', $translationFilter, 10);
        }

        $this->assertSame('33e9817343444a49324a275ae3f87b89', md5('public/dashboard/i18n-strings.js'));
        $this->assertNotFalse($translations);
        $this->assertStringContainsString('Success Rate', $translations);
        $this->assertStringContainsString(wp_json_encode('نرخ موفقیت'), $translations);
    }
}
