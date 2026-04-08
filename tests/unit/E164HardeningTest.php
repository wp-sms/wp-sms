<?php

namespace unit;

use WP_SMS\Components\NumberParser;
use WP_SMS\Components\Sms;
use WP_SMS\Helper;
use WP_SMS\Install;
use WP_SMS\Newsletter;
use WP_SMS\Notification\NotificationFactory;
use WP_SMS\Option;
use WP_SMS\SmsOtp\Generator;
use WP_SMS\SmsOtp\Verifier;
use WP_UnitTestCase;

/**
 * Tier 1 E.164 hardening tests.
 *
 * Covers normalization at the four newly-wired write paths, the dispatch chokepoints,
 * the rate-limit fuzzy lookups, the static normalization cache, and the migration wizard
 * options/post_meta sweep with namespaced backup + concurrency lock.
 */
class E164HardeningTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // The default country code is required for server-side normalization of local-format
        // input. Tests that need a different config override this in their own setup.
        Option::updateOption('mobile_county_code', '+98');
        Option::updateOption('international_mobile', false);
    }

    protected function tearDown(): void
    {
        delete_transient(\WP_SMS\Controller\NumberMigrationAjax::LOCK_TRANSIENT);
        delete_option(\WP_SMS\Controller\NumberMigrationAjax::BACKUP_OPTION_KEY);
        delete_option(\WP_SMS\Controller\NumberMigrationAjax::STATUS_OPTION_KEY);
        Helper::clearRecentNormalizationFailures();

        parent::tearDown();
    }

    // ---------------------------------------------------------------------
    // Helper::normalizeToE164 round-trips
    // ---------------------------------------------------------------------

    public function testNormalizeToE164AlreadyCanonical()
    {
        $this->assertEquals('+989123456789', Helper::normalizeToE164('+989123456789'));
    }

    public function testNormalizeToE164DoublezeroPrefix()
    {
        $this->assertEquals('+989123456789', Helper::normalizeToE164('00989123456789'));
    }

    public function testNormalizeToE164TrunkZero()
    {
        $this->assertEquals('+989123456789', Helper::normalizeToE164('09123456789'));
    }

    public function testNormalizeToE164BareNational()
    {
        $this->assertEquals('+989123456789', Helper::normalizeToE164('9123456789'));
    }

    public function testNormalizeToE164PersianNumerals()
    {
        $this->assertEquals('+989123456789', Helper::normalizeToE164('۰۹۱۲۳۴۵۶۷۸۹'));
    }

    public function testNormalizeToE164ArabicNumerals()
    {
        $this->assertEquals('+989123456789', Helper::normalizeToE164('٠٩١٢٣٤٥٦٧٨٩'));
    }

    public function testNormalizeToE164FormattingCharactersStripped()
    {
        $this->assertEquals('+989123456789', Helper::normalizeToE164('0912-345 6789'));
    }

    public function testNormalizeToE164EmptyInputReturnsAsIs()
    {
        $this->assertEquals('', Helper::normalizeToE164(''));
    }

    public function testNormalizeToE164UnparsableInputFallsThrough()
    {
        // Helper is the safe wrapper — it returns the original on parse failure rather than
        // throwing, so non-phone strings flow through and downstream gateways reject them.
        $this->assertEquals('not-a-number', Helper::normalizeToE164('not-a-number'));
    }

    public function testNormalizeToE164WithInternationalInputEnabled()
    {
        Option::updateOption('international_mobile', true);
        $this->assertEquals('+989123456789', Helper::normalizeToE164('+989123456789'));
    }

    // ---------------------------------------------------------------------
    // Helper::isShortCode
    // ---------------------------------------------------------------------

    public function testIsShortCodeFiveDigits()
    {
        $this->assertTrue(Helper::isShortCode('80800'));
    }

    public function testIsShortCodeFourDigits()
    {
        $this->assertTrue(Helper::isShortCode('1234'));
    }

    public function testIsShortCodeRejectsTooLong()
    {
        $this->assertFalse(Helper::isShortCode('1234567'));
    }

    public function testIsShortCodeRejectsLeadingZero()
    {
        $this->assertFalse(Helper::isShortCode('0912'));
    }

    public function testIsShortCodeRejectsLeadingPlus()
    {
        $this->assertFalse(Helper::isShortCode('+98'));
    }

    public function testNormalizeToE164WithShortCodeGuardPassesThroughShortCodes()
    {
        $this->assertEquals('80800', Helper::normalizeToE164WithShortCodeGuard('80800'));
    }

    public function testNormalizeToE164WithShortCodeGuardNormalizesPhoneNumbers()
    {
        $this->assertEquals(
            '+989123456789',
            Helper::normalizeToE164WithShortCodeGuard('09123456789')
        );
    }

    // ---------------------------------------------------------------------
    // Tier 2 helpers
    // ---------------------------------------------------------------------

    public function testTryNormalizeToE164SuccessReportsCanonicalValue()
    {
        $result = Helper::tryNormalizeToE164('09123456789');
        $this->assertTrue($result['success']);
        $this->assertEquals('+989123456789', $result['value']);
        $this->assertNull($result['reason']);
    }

    public function testTryNormalizeToE164FailureReportsReason()
    {
        $result = Helper::tryNormalizeToE164('not-a-number');
        $this->assertFalse($result['success']);
        $this->assertNotEmpty($result['reason']);
    }

    public function testTryNormalizeToE164ShortCodePassesAsSuccess()
    {
        $result = Helper::tryNormalizeToE164('80800');
        $this->assertTrue($result['success']);
        $this->assertEquals('80800', $result['value']);
    }

    public function testRecordAndGetRecentNormalizationFailures()
    {
        Helper::recordNormalizationFailure('garbage1', 'cf7:42', 'invalid format');
        Helper::recordNormalizationFailure('garbage2', 'cf7:42', 'invalid format');
        Helper::flushNormalizationFailures();

        $failures = Helper::getRecentNormalizationFailures();
        $this->assertCount(2, $failures);
        // Most recent first.
        $this->assertEquals('garbage2', $failures[0]['original_value']);
    }

    public function testRecordNormalizationFailureDedupsWithinWindow()
    {
        // Same (source, value) within the dedup window should be silently dropped so a
        // runaway integration cannot spam the option store with thousands of writes/sec.
        Helper::recordNormalizationFailure('same-bad', 'cf7:42');
        Helper::recordNormalizationFailure('same-bad', 'cf7:42');
        Helper::recordNormalizationFailure('same-bad', 'cf7:42');
        Helper::flushNormalizationFailures();

        $this->assertCount(1, Helper::getRecentNormalizationFailures());
    }

    public function testRecordNormalizationFailureCapsAtLimit()
    {
        // Seed more than the FIFO cap; the option must not grow unbounded.
        for ($i = 0; $i < Helper::RECENT_FAILURES_LIMIT + 10; $i++) {
            Helper::recordNormalizationFailure('value-' . $i, 'src-' . $i);
        }
        Helper::flushNormalizationFailures();

        $this->assertLessThanOrEqual(
            Helper::RECENT_FAILURES_LIMIT,
            count(Helper::getRecentNormalizationFailures())
        );
    }

    public function testGetPhoneFormatExampleUsesConfiguredCountryCode()
    {
        Option::updateOption('mobile_county_code', '+44');
        $example = Helper::getPhoneFormatExample();
        $this->assertStringStartsWith('+44', $example);
    }

    public function testGetPhoneFormatExampleFallsBackWhenUnconfigured()
    {
        Option::updateOption('mobile_county_code', '');
        $example = Helper::getPhoneFormatExample();
        $this->assertStringStartsWith('+', $example);
    }

    public function testRenderPhoneHtmlWrapsInBdi()
    {
        $this->assertEquals(
            '<bdi>+989123456789</bdi>',
            Helper::renderPhoneHtml('+989123456789')
        );
    }

    public function testRenderPhoneHtmlReturnsEmptyForEmptyInput()
    {
        $this->assertEquals('', Helper::renderPhoneHtml(''));
    }

    // ---------------------------------------------------------------------
    // Static cache
    // ---------------------------------------------------------------------

    public function testNormalizeToE164StaticCacheReturnsConsistentValues()
    {
        // Repeat calls return the same value. Bulk campaigns can call normalize 10,000 times
        // for the same recipient list — this guards against re-instantiating NumberParser.
        $first  = Helper::normalizeToE164('09123456789');
        $second = Helper::normalizeToE164('09123456789');
        $third  = Helper::normalizeToE164('09123456789');

        $this->assertSame($first, $second);
        $this->assertSame($second, $third);
        $this->assertEquals('+989123456789', $first);
    }

    // ---------------------------------------------------------------------
    // Newsletter::addSubscriber stores canonical
    // ---------------------------------------------------------------------

    public function testNewsletterAddSubscriberStoresCanonical()
    {
        global $wpdb;

        $groupResult = Newsletter::addGroup('E164 Test Group ' . uniqid());
        $this->assertEquals('success', $groupResult['result']);
        $groupId = $groupResult['data']['group_ID'];

        $addResult = Newsletter::addSubscriber(
            'Test User',
            '09123456' . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT),
            json_encode([$groupId])
        );
        $this->assertEquals('success', $addResult['result'], $addResult['message'] ?? '');

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT mobile FROM {$wpdb->prefix}sms_subscribes WHERE ID = %d",
            $addResult['id']
        ));

        $this->assertNotNull($row);
        $this->assertStringStartsWith('+98', $row->mobile, 'Stored mobile should be canonical E.164');

        Newsletter::deleteSubscriberByNumber($row->mobile, json_encode([$groupId]));
        Newsletter::deleteGroup($groupId);
    }

    // ---------------------------------------------------------------------
    // OTP cross-format verification (the pre-existing bug fix)
    // ---------------------------------------------------------------------

    public function testOtpCrossFormatVerificationSucceeds()
    {
        // Generation submits in local format, verification submits in international format —
        // both must normalize to the same canonical row.
        $local         = '09123450' . str_pad((string) random_int(0, 999), 3, '0', STR_PAD_LEFT);
        $international = '+98' . substr(Helper::normalizeToE164($local), 3);

        $generator = new Generator($local, 'test-agent');
        $generator->createCode(6);
        $code = $generator->getCode();
        $this->assertNotEmpty($code);
        $this->assertNotFalse($generator->saveIntoDatabase());

        $verifier = new Verifier($international, 'test-agent');
        $this->assertTrue(
            $verifier->verify($code),
            'OTP verification should succeed when generation and verification submit different surface forms'
        );
    }

    // ---------------------------------------------------------------------
    // OTP rate limit fuzzy lookup (the security test)
    // ---------------------------------------------------------------------

    public function testOtpGeneratorRateLimitMatchesLegacyRows()
    {
        [$canonical, $legacy] = $this->makeLegacyCanonicalPair();
        $this->seedLegacyOtpRows(Install::TABLE_OTP, $legacy, ['code' => '0', 'created_at' => time()], 6);

        $generator = new Generator($canonical, 'fuzzy-test');
        $this->expectException(\WP_SMS\SmsOtp\Exceptions\OtpLimitExceededException::class);
        $generator->limitGeneration();
    }

    public function testOtpVerifierRateLimitMatchesLegacyRows()
    {
        [$canonical, $legacy] = $this->makeLegacyCanonicalPair();
        $this->seedLegacyOtpRows(
            Install::TABLE_OTP_ATTEMPTS,
            $legacy,
            ['code' => '000000', 'result' => 0, 'time' => time()],
            6
        );

        $verifier = new Verifier($canonical, 'fuzzy-test');
        $this->expectException(\WP_SMS\SmsOtp\Exceptions\TooManyAttemptsException::class);
        $verifier->limitVerification();
    }

    /**
     * Build a (canonical, legacy) pair drawn from the same underlying number, where the legacy
     * form is what pre-migration code would have stored. Tests use the canonical form to call
     * the new fuzzy lookup and assert that it still matches the legacy rows.
     */
    private function makeLegacyCanonicalPair()
    {
        $canonical = '+9891234' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $legacy    = '0' . substr($canonical, 3);
        return [$canonical, $legacy];
    }

    private function seedLegacyOtpRows($tableConst, $phoneNumber, array $extraCols, $count)
    {
        global $wpdb;
        $table = $wpdb->prefix . $tableConst;

        for ($i = 0; $i < $count; $i++) {
            $wpdb->insert($table, array_merge([
                'phone_number' => $phoneNumber,
                'agent'        => 'fuzzy-test',
            ], $extraCols));
        }
    }

    // ---------------------------------------------------------------------
    // OTP transition window (documented behavior — verification path is NOT fuzzy)
    // ---------------------------------------------------------------------

    public function testOtpVerificationTransitionWindowFails()
    {
        global $wpdb;

        // Seed an OTP row in legacy non-canonical form (mimics a user mid-flight at deploy).
        $canonical = '+9891234' . str_pad((string) random_int(0, 99999), 5, '0', STR_PAD_LEFT);
        $legacy    = '0' . substr($canonical, 3);
        $code      = '654321';

        $wpdb->insert(
            $wpdb->prefix . Install::TABLE_OTP,
            [
                'phone_number' => $legacy,
                'code'         => md5($code),
                'agent'        => 'transition-test',
                'created_at'   => time(),
            ]
        );

        // Verify with the canonical form. Per the scope decision, this fails — verification
        // proper does NOT fuzzy match. Users mid-flight at deploy must request a new OTP.
        $verifier = new Verifier($canonical, 'transition-test');
        $this->assertFalse(
            $verifier->verify($code),
            'OTP verification should NOT fuzzy match (documented trade-off for the transition window)'
        );
    }

    // ---------------------------------------------------------------------
    // Notification + Sms chokepoints
    // ---------------------------------------------------------------------

    public function testNotificationSendChokepointNormalizesRecipients()
    {
        $captured = $this->captureDispatchedRecipients(function () {
            NotificationFactory::getCustom()->send('hello', ['09123456789', '09123456788']);
        });

        $this->assertNotNull($captured);
        foreach ((array) $captured as $value) {
            $this->assertStringStartsWith('+98', $value, "Captured recipient '$value' should be canonical");
        }
    }

    public function testSmsSendChokepointPassesShortCodesThrough()
    {
        $captured = (array) $this->captureDispatchedRecipients(function () {
            Sms::send([
                'to'  => ['80800', '09123456789'],
                'msg' => 'short code test',
            ]);
        });

        $this->assertContains('80800', $captured, 'Short code should pass through unchanged');

        $hasCanonical = false;
        foreach ($captured as $value) {
            if (strpos($value, '+98') === 0) {
                $hasCanonical = true;
                break;
            }
        }
        $this->assertTrue($hasCanonical, 'Real phone number should still be normalized to canonical form');
    }

    /**
     * Run a dispatch closure with the test gateway wired up and return whatever the
     * `wp_sms_to` filter saw — that's the post-chokepoint value, just before the gateway
     * receives it.
     */
    private function captureDispatchedRecipients(callable $dispatch)
    {
        $captured = null;
        add_filter('wp_sms_to', function ($to) use (&$captured) {
            $captured = $to;
            return $to;
        }, 5);

        Option::updateOption('gateway_name', 'test');
        $GLOBALS['sms'] = \WP_SMS\Gateway::initial();

        try {
            $dispatch();
        } finally {
            remove_all_filters('wp_sms_to');
        }

        return $captured;
    }

    // ---------------------------------------------------------------------
    // Migration wizard sweeps + namespaced backup + concurrency lock
    // ---------------------------------------------------------------------

    public function testMigrationWizardSweepsAdminMobileNumberOption()
    {
        Option::updateOption('admin_mobile_number', '09123456789');

        $this->callExecuteAndAssertSuccess();

        $this->assertEquals('+989123456789', Option::getOption('admin_mobile_number'));

        $backup = get_option(\WP_SMS\Controller\NumberMigrationAjax::BACKUP_OPTION_KEY);
        $this->assertNotEmpty($backup);
        $this->assertArrayHasKey('options', $backup);
        $this->assertArrayHasKey('option:wpsms_settings:admin_mobile_number', $backup['options']);
        $this->assertEquals('09123456789', $backup['options']['option:wpsms_settings:admin_mobile_number']['original']);
    }

    public function testMigrationWizardSweepsScheduledSendToPostMeta()
    {
        $postId = self::factory()->post->create();
        update_post_meta($postId, 'wpsms_scheduled_send_to', '09123456789,09123456788');

        $this->callExecuteAndAssertSuccess();

        $migrated = get_post_meta($postId, 'wpsms_scheduled_send_to', true);
        $this->assertStringStartsWith('+98', $migrated);

        $backup = get_option(\WP_SMS\Controller\NumberMigrationAjax::BACKUP_OPTION_KEY);
        $key    = 'postmeta:' . $postId . ':wpsms_scheduled_send_to';
        $this->assertArrayHasKey('postmeta', $backup);
        $this->assertArrayHasKey($key, $backup['postmeta']);
        $this->assertEquals('09123456789,09123456788', $backup['postmeta'][$key]['original']);
    }

    public function testMigrationWizardSweepsScheduledReceiversPostMeta()
    {
        $postId = self::factory()->post->create();
        $original = ['09123456789', '09123456788'];
        update_post_meta($postId, 'wpsms_scheduled_receivers', $original);

        $this->callExecuteAndAssertSuccess();

        $migrated = get_post_meta($postId, 'wpsms_scheduled_receivers', true);
        $this->assertIsArray($migrated);
        foreach ($migrated as $value) {
            $this->assertStringStartsWith('+98', $value);
        }

        $backup = get_option(\WP_SMS\Controller\NumberMigrationAjax::BACKUP_OPTION_KEY);
        $key    = 'postmeta:' . $postId . ':wpsms_scheduled_receivers';
        $this->assertArrayHasKey($key, $backup['postmeta']);
    }

    public function testMigrationWizardConcurrencyLockBlocksDoubleExecute()
    {
        // Manually set the lock to simulate a second concurrent invocation.
        set_transient(\WP_SMS\Controller\NumberMigrationAjax::LOCK_TRANSIENT, time(), 300);

        Option::updateOption('admin_mobile_number', '09123456789');

        $exception = null;
        try {
            $this->callExecute();
        } catch (\WPDieException $e) {
            $exception = $e;
        }

        $this->assertNotNull($exception, 'Second execute call should bail with the lock error');
        // The original (legacy) value must remain — the second execute should NOT mutate it.
        $this->assertEquals('09123456789', Option::getOption('admin_mobile_number'));
    }

    public function testMigrationWizardRevertsOptionEntries()
    {
        Option::updateOption('admin_mobile_number', '09123456789');
        $this->callExecuteAndAssertSuccess();
        $this->assertEquals('+989123456789', Option::getOption('admin_mobile_number'));

        $this->callRevertAndAssertSuccess();
        $this->assertEquals('09123456789', Option::getOption('admin_mobile_number'));
    }

    public function testMigrationWizardRevertsPostMetaEntries()
    {
        $postId = self::factory()->post->create();
        update_post_meta($postId, 'wpsms_scheduled_send_to', '09123456789');
        $original = ['09123456789', '09123456788'];
        update_post_meta($postId, 'wpsms_scheduled_receivers', $original);

        $this->callExecuteAndAssertSuccess();

        $this->callRevertAndAssertSuccess();

        $this->assertEquals('09123456789', get_post_meta($postId, 'wpsms_scheduled_send_to', true));
        $this->assertEquals($original, get_post_meta($postId, 'wpsms_scheduled_receivers', true));
    }

    // ---------------------------------------------------------------------
    // Migration wizard helpers — invoke via reflection so we don't need REST plumbing
    // ---------------------------------------------------------------------

    /**
     * Invoke NumberMigrationAjax::execute directly. wp_send_json_success / _error throw
     * WPDieException in the test bootstrap, which we catch with try/finally on the caller side.
     */
    private function callExecute()
    {
        $controller = new \WP_SMS\Controller\NumberMigrationAjax();
        $reflection = new \ReflectionClass($controller);
        $method     = $reflection->getMethod('execute');
        $method->setAccessible(true);
        $method->invoke($controller);
    }

    private function callExecuteAndAssertSuccess()
    {
        try {
            $this->callExecute();
            $this->fail('execute() should have called wp_send_json_success');
        } catch (\WPDieException $e) {
            // wp_send_json_success dies — expected.
        }
    }

    private function callRevertAndAssertSuccess()
    {
        $controller = new \WP_SMS\Controller\NumberMigrationAjax();
        $reflection = new \ReflectionClass($controller);
        $method     = $reflection->getMethod('revert');
        $method->setAccessible(true);

        try {
            $method->invoke($controller);
            $this->fail('revert() should have called wp_send_json_success');
        } catch (\WPDieException $e) {
            // wp_send_json_success dies — expected.
        }
    }
}
