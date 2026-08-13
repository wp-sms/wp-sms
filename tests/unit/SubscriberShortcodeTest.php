<?php

namespace unit;

use WP_SMS\Shortcode\SubscriberShortcode;
use WP_UnitTestCase;

class SubscriberShortcodeTest extends WP_UnitTestCase
{
    public function testShortcodeWithoutCustomFieldsRendersWithoutWarnings()
    {
        $shortcode = new SubscriberShortcode();

        $output = $shortcode->registerSubscriberShortcodeCallback([]);

        $this->assertStringContainsString('js-wpSmsSubscribeForm', $output);
        $this->assertStringNotContainsString('js-wpSmsSubscriberCustomFields', $output);
    }
}
