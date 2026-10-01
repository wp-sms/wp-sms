<?php

namespace unit;

use WP_SMS\Components\PhoneNumberMetadata;
use WP_SMS\Controller\NumberMigrationAjax;
use WP_SMS\Option;
use WP_UnitTestCase;

class NumberMigrationDieException extends \RuntimeException
{
}

/**
 * Phone number check wizard: numbers that start with + but are missing the site's
 * country code, and WooCommerce order/subscription billing phones (issue #551).
 */
class NumberMigrationPlusNumbersTest extends WP_UnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Option::updateOption('mobile_county_code', '+1');
        Option::updateOption('international_mobile', false);
    }

    protected function tearDown(): void
    {
        delete_transient(NumberMigrationAjax::LOCK_TRANSIENT);
        delete_option(NumberMigrationAjax::BACKUP_OPTION_KEY);
        delete_option(NumberMigrationAjax::STATUS_OPTION_KEY);

        parent::tearDown();
    }

    // ---------------------------------------------------------------------
    // Phone metadata
    // ---------------------------------------------------------------------

    public function testMetadataIsAvailable()
    {
        $this->assertTrue(PhoneNumberMetadata::isAvailable());
        $this->assertTrue(PhoneNumberMetadata::hasCallingCode('1'));
        $this->assertTrue(PhoneNumberMetadata::hasCallingCode('44'));
        $this->assertFalse(PhoneNumberMetadata::hasCallingCode('0'));
    }

    /**
     * @dataProvider validNumbersProvider
     */
    public function testRealNumbersAreValid($number)
    {
        $this->assertTrue(PhoneNumberMetadata::isValidNumber($number), $number . ' should be valid');
    }

    public function validNumbersProvider()
    {
        return [
            'US'      => ['+17065810032'],
            'Russia'  => ['+79161234567'],
            'UK'      => ['+447911123456'],
            'Denmark' => ['+4520123456'],
            'Norway'  => ['+4741234567'],
            'Germany' => ['+4915123456789'],
            'Iran'    => ['+989121234567'],
        ];
    }

    public function testNumberMissingCountryCodeIsInvalid()
    {
        // Reads as +7 (Russia/Kazakhstan) with a national number that doesn't exist there.
        $this->assertFalse(PhoneNumberMetadata::isValidNumber('+7065810032'));
        $this->assertFalse(PhoneNumberMetadata::isValidNumber('+07065810032'));
        $this->assertFalse(PhoneNumberMetadata::isValidNumber('+'));
    }

    // ---------------------------------------------------------------------
    // migrateNumber rules
    // ---------------------------------------------------------------------

    public function testPlusNumberMissingUsCountryCodeIsFixed()
    {
        $this->assertSame('+17065810032', $this->migrate('+7065810032', '+1'));
    }

    public function testRealRussianNumberIsNotChangedOnUsSite()
    {
        $this->assertSame('+79161234567', $this->migrate('+79161234567', '+1'));
    }

    public function testBareUsNumberGetsCountryCode()
    {
        $this->assertSame('+17065810032', $this->migrate('7065810032', '+1'));
    }

    public function testValidUsNumberIsNotChanged()
    {
        $this->assertSame('+17065810032', $this->migrate('+17065810032', '+1'));
    }

    public function testRealForeignNumbersWithSameLengthAsUsNationalAreNotChanged()
    {
        // 10 digits after the + like a US national number, but real numbers for their own countries.
        $this->assertSame('+4520123456', $this->migrate('+4520123456', '+1'));
        $this->assertSame('+4741234567', $this->migrate('+4741234567', '+1'));
    }

    public function testPlusNumberWithTrunkZeroIsFixed()
    {
        $this->assertSame('+17065810032', $this->migrate('+07065810032', '+1'));
        $this->assertSame('+447911123456', $this->migrate('+07911123456', '+44'));
    }

    public function testPlusNumberMissingUkCountryCodeIsFixed()
    {
        $this->assertSame('+447911123456', $this->migrate('+7911123456', '+44'));
    }

    public function testInvalidPlusNumberThatCannotBeFixedIsLeftAlone()
    {
        $this->assertSame('+12345', $this->migrate('+12345', '+1'));
        $this->assertSame('+999999999999999', $this->migrate('+999999999999999', '+1'));
    }

    public function testPlusNumberFormattingIsKeptWhenValid()
    {
        $this->assertSame('+7 916 123-45-67', $this->migrate('+7 916 123-45-67', '+1'));
    }

    // ---------------------------------------------------------------------
    // Scan / preview / execute / revert
    // ---------------------------------------------------------------------

    public function testScanPreviewExecuteAndRevertFixPlusNumbers()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sms_subscribes';

        $wpdb->insert($table, ['name' => 'Wrong', 'mobile' => '+7065810032', 'status' => '1', 'date' => current_time('mysql')]);
        $wrongId = (int) $wpdb->insert_id;
        $wpdb->insert($table, ['name' => 'Russia', 'mobile' => '+79161234567', 'status' => '1', 'date' => current_time('mysql')]);
        $russiaId = (int) $wpdb->insert_id;
        $wpdb->insert($table, ['name' => 'Local', 'mobile' => '7065810033', 'status' => '1', 'date' => current_time('mysql')]);
        $localId = (int) $wpdb->insert_id;

        $scan = $this->captureJsonResponse('scan');
        $this->assertTrue($scan['success']);
        $this->assertSame(3, $scan['data']['sources']['subscribers']['total']);
        $this->assertSame(2, $scan['data']['sources']['subscribers']['need_fix']);
        $this->assertSame(1, $scan['data']['sources']['subscribers']['already_intl']);

        $preview = $this->captureJsonResponse('preview');
        $this->assertTrue($preview['success']);
        $rows = array_values(array_filter($preview['data']['preview'], function ($row) {
            return $row['source'] === 'subscribers';
        }));
        $byId = array_column($rows, null, 'id');
        $this->assertArrayHasKey($wrongId, $byId);
        $this->assertSame('+7065810032', $byId[$wrongId]['original']);
        $this->assertSame('+17065810032', $byId[$wrongId]['migrated']);
        $this->assertArrayHasKey($localId, $byId);
        $this->assertSame('+17065810033', $byId[$localId]['migrated']);
        $this->assertArrayNotHasKey($russiaId, $byId, 'A valid Russian number must not be listed');

        $this->callAndExpectDie('execute');
        $this->assertSame('+17065810032', $wpdb->get_var($wpdb->prepare("SELECT mobile FROM {$table} WHERE ID = %d", $wrongId)));
        $this->assertSame('+79161234567', $wpdb->get_var($wpdb->prepare("SELECT mobile FROM {$table} WHERE ID = %d", $russiaId)));
        $this->assertSame('+17065810033', $wpdb->get_var($wpdb->prepare("SELECT mobile FROM {$table} WHERE ID = %d", $localId)));

        $this->callAndExpectDie('revert');
        $this->assertSame('+7065810032', $wpdb->get_var($wpdb->prepare("SELECT mobile FROM {$table} WHERE ID = %d", $wrongId)));
        $this->assertSame('7065810033', $wpdb->get_var($wpdb->prepare("SELECT mobile FROM {$table} WHERE ID = %d", $localId)));
    }

    public function testExecuteMigratesEveryRowAcrossBatches()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sms_subscribes';

        // More than one batch: LIMIT/OFFSET over a shrinking result set used to skip rows here.
        $count = NumberMigrationAjax::BATCH_SIZE + 20;
        for ($i = 0; $i < $count; $i++) {
            $wpdb->insert($table, ['name' => 'N' . $i, 'mobile' => sprintf('70658%05d', $i), 'status' => '1', 'date' => current_time('mysql')]);
        }

        $this->callAndExpectDie('execute');

        $left = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE mobile NOT LIKE '+%'");
        $this->assertSame(0, $left);
    }

    public function testAdminMobileNumberWithPlusMissingCountryCodeIsFixed()
    {
        Option::updateOption('admin_mobile_number', '+7065810032');

        $this->callAndExpectDie('execute');

        $this->assertSame('+17065810032', Option::getOption('admin_mobile_number'));
    }

    public function testCsvRecipientsWithPlusNumbersAreFixed()
    {
        global $wpdb;
        $table = $wpdb->prefix . 'sms_send';

        $wpdb->insert($table, [
            'date'      => current_time('mysql'),
            'sender'    => 'test',
            'message'   => 'hello',
            'recipient' => '+7065810032,+79161234567',
            'status'    => 'success',
        ]);
        $id = (int) $wpdb->insert_id;

        $scan = $this->captureJsonResponse('scan');
        $this->assertSame(1, $scan['data']['sources']['outbox']['need_fix']);

        $this->callAndExpectDie('execute');

        $this->assertSame('+17065810032,+79161234567', $wpdb->get_var($wpdb->prepare("SELECT recipient FROM {$table} WHERE ID = %d", $id)));
    }

    // ---------------------------------------------------------------------
    // WooCommerce orders and subscriptions
    // ---------------------------------------------------------------------

    public function testWooCommerceOrderBillingPhonesAreScannedAndMigrated()
    {
        if (!function_exists('wc_create_order')) {
            $this->markTestSkipped('WooCommerce is not loaded.');
        }

        $plus  = $this->createOrder('+7065810032');
        $bare  = $this->createOrder('7065810034');
        $valid = $this->createOrder('+79161234567');

        $scan    = $this->captureJsonResponse('scan');
        $sources = $scan['data']['sources'];
        $orderSourceKey = $this->isHposEnabled() ? 'wc_orders' : 'wc_orders_legacy';
        $this->assertArrayHasKey($orderSourceKey, $sources);
        $this->assertSame(2, $sources[$orderSourceKey]['need_fix']);

        $this->callAndExpectDie('execute');

        $this->assertSame('+17065810032', wc_get_order($plus)->get_billing_phone());
        $this->assertSame('+17065810034', wc_get_order($bare)->get_billing_phone());
        $this->assertSame('+79161234567', wc_get_order($valid)->get_billing_phone());

        $this->callAndExpectDie('revert');

        $this->assertSame('+7065810032', wc_get_order($plus)->get_billing_phone());
        $this->assertSame('7065810034', wc_get_order($bare)->get_billing_phone());
    }

    public function testHposOrderAndSubscriptionAddressesAreMigrated()
    {
        global $wpdb;

        if (!class_exists('WooCommerce') || !class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class)) {
            $this->markTestSkipped('WooCommerce is not loaded.');
        }

        $ordersTable    = $wpdb->prefix . 'wc_orders';
        $addressesTable = $wpdb->prefix . 'wc_order_addresses';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $addressesTable)) !== $addressesTable) {
            $this->markTestSkipped('HPOS tables are not installed.');
        }

        $forceHpos = function () {
            return 'yes';
        };
        add_filter('pre_option_woocommerce_custom_orders_table_enabled', $forceHpos);
        add_filter('pre_option_woocommerce_custom_orders_table_data_sync_enabled', '__return_false');

        try {
            if (!\Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled()) {
                $this->markTestSkipped('Could not switch WooCommerce to HPOS in this environment.');
            }

            $ids = [];
            foreach (['shop_order' => '+7065810032', 'shop_subscription' => '7065810035'] as $type => $phone) {
                // wc_orders.id isn't auto-increment: HPOS takes the ID from a placeholder post.
                $orderId = self::factory()->post->create(['post_type' => 'shop_order_placehold']);
                $wpdb->insert($ordersTable, ['id' => $orderId, 'type' => $type, 'status' => 'wc-processing', 'currency' => 'USD']);
                $wpdb->insert($addressesTable, ['order_id' => $orderId, 'address_type' => 'billing', 'phone' => $phone, 'first_name' => 'Ann']);
                $ids[$type] = (int) $wpdb->insert_id;
            }

            $scan = $this->captureJsonResponse('scan');
            $this->assertSame(1, $scan['data']['sources']['wc_orders']['need_fix']);
            $this->assertSame(1, $scan['data']['sources']['wc_subscriptions']['need_fix']);

            $this->callAndExpectDie('execute');

            $this->assertSame('+17065810032', $wpdb->get_var($wpdb->prepare("SELECT phone FROM {$addressesTable} WHERE id = %d", $ids['shop_order'])));
            $this->assertSame('+17065810035', $wpdb->get_var($wpdb->prepare("SELECT phone FROM {$addressesTable} WHERE id = %d", $ids['shop_subscription'])));
        } finally {
            remove_filter('pre_option_woocommerce_custom_orders_table_enabled', $forceHpos);
            remove_filter('pre_option_woocommerce_custom_orders_table_data_sync_enabled', '__return_false');
        }
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function isHposEnabled()
    {
        return class_exists(\Automattic\WooCommerce\Utilities\OrderUtil::class)
            && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
    }

    private function createOrder($phone)
    {
        $order = wc_create_order();
        $order->set_billing_first_name('Test');
        $order->set_billing_phone($phone);
        $order->save();

        return $order->get_id();
    }

    private function migrate($number, $countryCode)
    {
        $controller = new NumberMigrationAjax();
        $method     = new \ReflectionMethod($controller, 'migrateNumber');
        $method->setAccessible(true);

        return $method->invoke($controller, $number, $countryCode);
    }

    private function callAndExpectDie($methodName)
    {
        $output = $this->invokeAjax($methodName, $died);
        $this->assertTrue($died, $methodName . '() should end with a JSON response');
        $decoded = json_decode($output, true);
        $this->assertTrue($decoded['success'] ?? false, $methodName . '() failed: ' . $output);
    }

    private function captureJsonResponse($methodName)
    {
        $output  = $this->invokeAjax($methodName, $died);
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded, $methodName . '() should produce JSON');

        return $decoded;
    }

    private function invokeAjax($methodName, &$died)
    {
        $controller = new NumberMigrationAjax();
        $method     = new \ReflectionMethod($controller, $methodName);
        $method->setAccessible(true);

        $doingAjax  = '__return_true';
        $dieHandler = function () {
            return function () {
                throw new NumberMigrationDieException();
            };
        };

        add_filter('wp_doing_ajax', $doingAjax);
        add_filter('wp_die_ajax_handler', $dieHandler);

        $died = false;
        ob_start();
        try {
            $method->invoke($controller);
        } catch (NumberMigrationDieException $e) {
            $died = true;
        } finally {
            $output = ob_get_clean();
            remove_filter('wp_die_ajax_handler', $dieHandler);
            remove_filter('wp_doing_ajax', $doingAjax);
        }

        return $output;
    }
}
