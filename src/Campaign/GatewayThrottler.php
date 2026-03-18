<?php

namespace WSms\Campaign;

defined('ABSPATH') || exit;

class GatewayThrottler
{
    private const DEFAULT_RATE = 100; // messages per window
    private const DEFAULT_WINDOW = 60; // seconds

    /**
     * Check if sending is allowed under the rate limit.
     *
     * @return array{allowed: bool, retry_after: int}
     */
    public function check(string $gatewayId, int $rateLimit = self::DEFAULT_RATE, int $window = self::DEFAULT_WINDOW): array
    {
        $key = 'wsms_throttle_' . $gatewayId;
        $data = get_transient($key);

        $now = time();

        if ($data === false) {
            set_transient($key, ['count' => 1, 'window_start' => $now], $window);
            return ['allowed' => true, 'retry_after' => 0];
        }

        $windowStart = $data['window_start'] ?? $now;
        $count = $data['count'] ?? 0;

        // Window expired, reset
        if (($now - $windowStart) >= $window) {
            set_transient($key, ['count' => 1, 'window_start' => $now], $window);
            return ['allowed' => true, 'retry_after' => 0];
        }

        if ($count >= $rateLimit) {
            $retryAfter = $window - ($now - $windowStart);
            return ['allowed' => false, 'retry_after' => max(1, $retryAfter)];
        }

        set_transient($key, ['count' => $count + 1, 'window_start' => $windowStart], $window);
        return ['allowed' => true, 'retry_after' => 0];
    }

    /**
     * Increment the counter by a batch amount.
     */
    public function incrementBy(string $gatewayId, int $amount, int $window = self::DEFAULT_WINDOW): void
    {
        $key = 'wsms_throttle_' . $gatewayId;
        $data = get_transient($key);
        $now = time();

        if ($data === false || ($now - ($data['window_start'] ?? $now)) >= $window) {
            set_transient($key, ['count' => $amount, 'window_start' => $now], $window);
            return;
        }

        set_transient($key, ['count' => ($data['count'] ?? 0) + $amount, 'window_start' => $data['window_start']], $window);
    }
}
