<?php

namespace unit;

use WP_UnitTestCase;

class CompatibilityMetadataTest extends WP_UnitTestCase
{
    public function testReadmeDeclaresWordPress71Compatibility(): void
    {
        $readme = file_get_contents(dirname(__DIR__, 2) . '/readme.txt');

        $this->assertMatchesRegularExpression('/^Tested up to:\s*7\.1$/m', $readme);
    }

    public function testTranslationTemplateMatchesPluginVersion(): void
    {
        $pot = file_get_contents(dirname(__DIR__, 2) . '/languages/wp-sms.pot');

        $this->assertSame(1, preg_match('/^"Project-Id-Version: .* ([0-9.]+)\\\\n"$/m', $pot, $matches));
        $this->assertSame(WP_SMS_VERSION, $matches[1]);
        $this->assertStringContainsString('"X-Domain: wp-sms\\n"', $pot);
        $this->assertStringContainsString(
            '"Report-Msgid-Bugs-To: https://wordpress.org/support/plugin/wp-sms\\n"',
            $pot
        );
    }
}