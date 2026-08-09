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
}
