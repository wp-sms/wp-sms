<?php

namespace WP_SMS\Tests\Admin\LicenseManagement;

use Exception;
use WP_SMS\Admin\LicenseManagement\ApiCommunicator;
use WP_UnitTestCase;

/**
 * Coverage for #539 — a refused install asked the licence server every five minutes.
 *
 * `getProductInfo()` cached a success for a day and *any* failure for five minutes, then
 * rethrew. So an expired licence — a decision the server has already made and will make
 * again — was re-asked 288 times a day, per add-on, per subsite. One site was measured
 * sending 576 requests in a day; the fleet sent about 12,500.
 *
 * The fix is to tell the two kinds of failure apart. A 4xx is the server's answer about
 * the licence and is held for twelve hours. A timeout, a 5xx or a 429 has decided
 * nothing, stays short, and backs off.
 */
class LicenseNegativeCacheTest extends WP_UnitTestCase
{
    private const KEY = 'E0SNWPAPWYTHVPNV';

    private const SLUG = 'wp-sms-pro';

    /** @var int */
    private $requestCount = 0;

    protected function setUp(): void
    {
        parent::setUp();

        $this->requestCount = 0;
    }

    /**
     * Answer every licence request with a fixed HTTP code.
     */
    private function serve(int $code, array $body = []): void
    {
        add_filter('pre_http_request', function ($preempt, $args, $url) use ($code, $body) {
            if (strpos($url, '/product/download') === false) {
                return $preempt;
            }

            $this->requestCount++;

            return [
                'headers'  => [],
                'body'     => wp_json_encode($body ?: ['code' => 1000, 'message' => 'License has expired.']),
                'response' => ['code' => $code, 'message' => ''],
                'cookies'  => [],
                'filename' => null,
            ];
        }, 10, 3);
    }

    /**
     * Answer as WordPress does when the host cannot be reached at all.
     */
    private function serveTransportFailure(): void
    {
        add_filter('pre_http_request', function ($preempt, $args, $url) {
            if (strpos($url, '/product/download') === false) {
                return $preempt;
            }

            $this->requestCount++;

            return new \WP_Error('http_request_failed', 'cURL error 28: Operation timed out');
        }, 10, 3);
    }

    private function ask(): void
    {
        try {
            (new ApiCommunicator())->getProductInfo(self::KEY, self::SLUG);
        } catch (Exception $e) {
            // Every path under test throws; the point is how often it asks first.
        }
    }

    /**
     * How long the remembered refusal has left, in seconds.
     */
    private function refusalTtl(): int
    {
        global $wpdb;

        $prefix = is_multisite() ? '_site_transient_timeout_' : '_transient_timeout_';

        $timeout = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT option_value FROM {$wpdb->options} WHERE option_name LIKE %s",
                $prefix . 'wp_sms_license_refusal_%'
            )
        );

        return $timeout ? (int) $timeout - time() : 0;
    }

    public function test_an_expired_licence_is_asked_about_twice_a_day_not_every_five_minutes(): void
    {
        $this->serve(400);

        // Six asks in a row is what a few page loads used to produce.
        foreach (range(1, 6) as $ignored) {
            $this->ask();
        }

        $this->assertSame(1, $this->requestCount, 'A refused licence must be asked about once, not once per call.');
        $this->assertGreaterThan(11 * HOUR_IN_SECONDS, $this->refusalTtl());
        $this->assertLessThanOrEqual(ApiCommunicator::AUTHORITATIVE_CACHE_DURATION, $this->refusalTtl());
    }

    /**
     * The server being unreachable says nothing about the licence, so the wait stays
     * short — otherwise a brief outage would silence a working install for half a day.
     */
    public function test_a_timeout_is_remembered_only_briefly(): void
    {
        $this->serveTransportFailure();

        $this->ask();
        $this->ask();

        $this->assertSame(1, $this->requestCount);
        $this->assertLessThanOrEqual(ApiCommunicator::NEGATIVE_CACHE_DURATION, $this->refusalTtl());
    }

    /**
     * 429 is the server asking us to slow down. That is about the request, not the
     * licence, so it must not be cached as though the licence were refused.
     */
    public function test_a_rate_limit_backs_off_rather_than_counting_as_an_answer(): void
    {
        $this->serve(429);

        $this->ask();

        $this->assertLessThanOrEqual(ApiCommunicator::NEGATIVE_CACHE_DURATION, $this->refusalTtl());
        $this->assertLessThan(HOUR_IN_SECONDS, $this->refusalTtl());
    }

    /**
     * A customer who renews must not wait out the twelve hours. Validating a licence is
     * the moment we learn something changed, and it clears what we remembered.
     */
    public function test_validating_a_licence_forgets_the_refusal(): void
    {
        $this->serve(400);
        $this->ask();
        $this->assertSame(1, $this->requestCount);

        (new ApiCommunicator())->clearProductInfoCache(self::KEY);

        $this->ask();

        $this->assertSame(2, $this->requestCount, 'Clearing the cache must let the next ask through.');
    }

    /**
     * The server judges the address we send it, so the address is what a refusal belongs
     * to. On a subdomain network one subsite being refused must not silence another.
     */
    public function test_a_different_address_is_asked_about_separately(): void
    {
        $this->serve(400);

        $this->ask();

        $other = function () {
            return 'https://another.example.test';
        };

        add_filter('home_url', $other);
        $this->ask();
        remove_filter('home_url', $other);

        $this->assertSame(2, $this->requestCount, 'A second address must get its own answer.');
    }

    /**
     * The previous release stored an object here. An upgrade must treat that as absent
     * rather than trip over it, or the first check after updating throws.
     */
    public function test_a_cache_entry_from_the_previous_release_is_ignored(): void
    {
        $this->serve(400);
        $this->ask();

        // Rewrite whatever we stored with the old shape.
        global $wpdb;
        $wpdb->query(
            $wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name LIKE %s",
                serialize((object) ['_negative_cache' => true]),
                '%_transient_wp_sms_license_refusal_%'
            )
        );

        $this->ask();

        $this->assertSame(2, $this->requestCount, 'An unrecognised cache entry must not be trusted.');
    }
}
