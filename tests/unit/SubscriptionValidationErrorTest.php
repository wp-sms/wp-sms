<?php

namespace unit;

use WP_SMS\Controller\PublicSubscribeAjax;
use WP_UnitTestCase;

class SubscriptionValidationErrorTest extends WP_UnitTestCase
{
    public function testAllowsSafeLinksAndLineBreaks()
    {
        $message = 'You are unsubscribed.<br><br><a href="sms:+120****8404?body=START" target="_blank" rel="noopener">Text START</a>';

        $this->assertSame($message, PublicSubscribeAjax::sanitizeValidationErrorMessage($message));
    }

    public function testStripsUnsafeHtmlAndAttributes()
    {
        $message   = '<script>alert(1)</script><a href="javascript:alert(1)" onclick="alert(1)">Text START</a>';
        $sanitized = PublicSubscribeAjax::sanitizeValidationErrorMessage($message);

        $this->assertStringNotContainsString('<script', $sanitized);
        $this->assertStringNotContainsString('javascript:', $sanitized);
        $this->assertStringNotContainsString('onclick', $sanitized);
        $this->assertStringContainsString('<a href="alert(1)">Text START</a>', $sanitized);
    }

    public function testKeepsPlainTextMessagesUnchanged()
    {
        $message = 'This mobile is already registered, please choose another one.';

        $this->assertSame($message, PublicSubscribeAjax::sanitizeValidationErrorMessage($message));
    }
}
