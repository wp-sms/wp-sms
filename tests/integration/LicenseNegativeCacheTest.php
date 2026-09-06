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
        // Read through the same API the code writes with. Querying wp_options directly
        // returns 0 under a persistent object cache, and on multisite site transients
        // live in wp_sitemeta — either way three tests would fail for reasons that have
        // nothing to do with the fix.
        $option = is_multisite()
            ? '_site_transient_timeout_' . $this->refusalKey()
            : '_transient_timeout_' . $this->refusalKey();

        $timeout = is_multisite() ? get_site_option($option) : get_option($option);

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
     * Some responses are about the request or the route, not the licence.
     *
     * 429 is the server asking us to slow down; a 404 is a route that moved during a
     * deploy; a 403 is an edge rule with no licence involved. Caching any of them for
     * twelve hours would black out the fleet until tomorrow, long after the rollback.
     *
     * @dataProvider undecided_response_provider
     */
    public function test_a_response_that_decides_nothing_is_remembered_briefly(int $code): void
    {
        $this->serve($code);

        $this->ask();

        // Stated as a wall-clock bound, not as the constant the code uses, so this fails
        // if the classification changes rather than moving with it.
        $this->assertGreaterThan(0, $this->refusalTtl());
        $this->assertLessThanOrEqual(310, $this->refusalTtl(), "HTTP {$code} must not be cached as an answer about the licence.");
    }

    public function undecided_response_provider(): array
    {
        return [
            'rate limited'      => [429],
            'route moved'       => [404],
            'edge rule / WAF'   => [403],
            'request timeout'   => [408],
            'server error'      => [500],
        ];
    }

    /**
     * The backoff has to survive the wait it sets, or it is not a backoff.
     *
     * The first version kept the counter inside the refusal itself. The refusal expiring
     * is the only thing that lets another attempt happen, so the counter was always gone
     * by the time it was read and the delay was a fixed five minutes forever — with the
     * cap and the clamp above it unreachable.
     */
    public function test_repeated_outages_lengthen_the_wait(): void
    {
        $this->serveTransportFailure();

        $this->ask();
        $first = $this->refusalTtl();

        // Let the wait lapse the way time would, without waiting.
        $this->expireRefusal();
        $this->ask();
        $second = $this->refusalTtl();

        $this->expireRefusal();
        $this->ask();
        $third = $this->refusalTtl();

        $this->assertSame(3, $this->requestCount);
        $this->assertGreaterThan($first, $second, 'The second failure must wait longer than the first.');
        $this->assertGreaterThan($second, $third, 'The third must wait longer again.');
        $this->assertLessThanOrEqual(ApiCommunicator::MAX_TRANSIENT_CACHE_DURATION, $third);
    }

    /**
     * And a licence that answers again starts the next outage from five minutes.
     */
    public function test_a_success_forgets_the_attempt_history(): void
    {
        $this->serveTransportFailure();
        $this->ask();
        $this->expireRefusal();
        $this->ask();
        $stretched = $this->refusalTtl();

        $this->deleteRefusal();
        remove_all_filters('pre_http_request');
        $this->serve(200, ['download_url' => 'https://example.test/x.zip']);
        (new ApiCommunicator())->getProductInfo(self::KEY, self::SLUG);

        remove_all_filters('pre_http_request');
        $this->serveTransportFailure();
        $this->ask();

        $this->assertLessThan($stretched, $this->refusalTtl(), 'A success must reset the backoff.');
    }

    /**
     * Drop the refusal the way its expiry would, leaving the attempt history alone.
     */
    private function expireRefusal(): void
    {
        $this->deleteRefusal();
    }

    private function deleteRefusal(): void
    {
        if (is_multisite()) {
            delete_site_transient($this->refusalKey());

            return;
        }

        delete_transient($this->refusalKey());
    }

    /**
     * A customer who renews must not wait out the twelve hours. Validating a licence is
     * the moment we learn something changed, and it clears what we remembered.
     */
    public function test_clearing_the_cache_forgets_the_refusal(): void
    {
        $this->serve(400);
        $this->ask();
        $this->assertSame(1, $this->requestCount);

        (new ApiCommunicator())->clearProductInfoCache(self::KEY);

        $this->ask();

        $this->assertSame(2, $this->requestCount, 'Clearing the cache must let the next ask through.');
    }

    /**
     * A network renews on one subsite. The rest must not stay refused for twelve hours.
     *
     * Refusals are keyed on the address the server judged, so one licence collects one
     * per subsite and per language, while the renewal happens at exactly one of them.
     * Clearing only that one left the others worse off than the five minutes they used
     * to wait.
     */
    public function test_clearing_reaches_every_address_the_licence_was_refused_at(): void
    {
        $second = function () {
            return 'https://second.example.test';
        };

        $this->serve(400);

        $this->ask();
        add_filter('home_url', $second);
        $this->ask();
        remove_filter('home_url', $second);

        $this->assertSame(2, $this->requestCount, 'Each address is asked once.');

        // Renew, as it happens on the first address only.
        (new ApiCommunicator())->clearProductInfoCache(self::KEY);

        add_filter('home_url', $second);
        $this->ask();
        remove_filter('home_url', $second);

        $this->assertSame(3, $this->requestCount, 'The other address must be released too.');
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
    /**
     * The previous release wrote its marker into the SUCCESS key, not a key of its own.
     *
     * That key is unchanged and `RemoteRequest` returns whatever it finds there, so
     * without a guard an upgraded site is handed `{_negative_cache: true}` as though it
     * were product info — no download_url, no version. The first version of this test
     * wrote the marker under the new key, which the old release never used, so it
     * exercised a state that cannot happen while the one that does went untested.
     */
    public function test_the_previous_releases_marker_is_not_served_as_product_info(): void
    {
        $reflection = new \ReflectionMethod(ApiCommunicator::class, 'getProductInfoCacheKey');
        $reflection->setAccessible(true);
        $successKey = $reflection->invoke(new ApiCommunicator(), self::SLUG, self::KEY);

        set_transient($successKey, (object) ['_negative_cache' => true], HOUR_IN_SECONDS);

        $this->serve(200, ['download_url' => 'https://example.test/x.zip']);

        $result = (new ApiCommunicator())->getProductInfo(self::KEY, self::SLUG);

        $this->assertNull($result, 'The old marker must never be returned as product info.');
        $this->assertFalse(get_transient($successKey), 'And it must be cleared, so the next check can succeed.');
    }

    /**
     * The key the refusal is stored under, built the way ApiCommunicator builds it.
     *
     * Duplicated deliberately: the shape of this key is the fix — a refusal belongs to
     * the address the server judged, not to the blog ID — so a test that asserted it
     * through the class could not tell a correct key from a wrong one.
     */
    private function refusalKey(string $slug = self::SLUG, string $key = self::KEY): string
    {
        return 'wp_sms_license_refusal_' . md5($slug . '_' . $key . '_' . home_url());
    }
}
