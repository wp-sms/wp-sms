<?php

namespace unit;

use ReflectionMethod;
use WP_SMS\Admin\Dashboard;
use WP_SMS\Helper;
use WP_SMS\Option;
use WP_UnitTestCase;

class DashboardTest extends WP_UnitTestCase
{
    public function testCustomGatewayHeadersAreMaskedBeforeLocalization(): void
    {
        $dashboard = Dashboard::instance();
        $method    = new ReflectionMethod($dashboard, 'maskSensitiveSettings');
        $method->setAccessible(true);

        $settings = $method->invoke($dashboard, [
            'gateway_http_headers' => 'Authorization: Bearer secret-token',
        ]);

        $this->assertSame('••••••••', $settings['gateway_http_headers']);
    }

    public function testFailureNoticeLeavesOutNumbersThatSendNow(): void
    {
        $notice = $this->failureNotice(['2025550123', '+1555019900000000']);

        $this->assertStringContainsString('1 phone number could not be normalized', $notice['message']);
        $this->assertStringNotContainsString('2025550123', $notice['message']);
        $this->assertStringContainsString('+1555019900000000', $notice['message']);
    }

    public function testFailureNoticeListsARepeatedNumberOnceWithItsReason(): void
    {
        $notice = $this->failureNotice(['+1555019900000000', '+1555019900000000', '+1555019900000000']);

        $this->assertSame(1, substr_count($notice['message'], '+1555019900000000'));
        $this->assertStringContainsString('wrong number of digits, 3 times', $notice['message']);
    }

    public function testFailureNoticeIsHiddenWhenEveryNumberSendsNow(): void
    {
        $this->assertNull($this->failureNotice(['2025550123', '2025550123']));
    }

    public function testFailureNoticeNamesTheReasonWithInternationalInputOn(): void
    {
        $notice = $this->failureNotice(['not-a-number', '2025550188'], true);

        $this->assertStringContainsString('<code>not-a-number</code> (not a phone number)', $notice['message']);
        $this->assertStringContainsString('<code>2025550188</code> (missing the country code)', $notice['message']);
    }

    private function failureNotice(array $values, bool $internationalInput = false): ?array
    {
        wp_set_current_user(self::factory()->user->create(['role' => 'administrator']));
        // 2025550123 sends only with a default country code, as on a configured site.
        Option::updateOption('mobile_county_code', '+1');
        Option::updateOption('international_mobile', $internationalInput);
        update_option(Helper::RECENT_FAILURES_OPTION, array_map(function ($value) {
            return ['original_value' => $value, 'source' => 'unknown', 'timestamp' => time(), 'reason' => null];
        }, $values));

        $method = new ReflectionMethod(Dashboard::instance(), 'getAdminNotices');
        $method->setAccessible(true);

        foreach ($method->invoke(Dashboard::instance()) as $notice) {
            if ($notice['id'] === 'recent_phone_failures') {
                return $notice;
            }
        }

        return null;
    }
}
