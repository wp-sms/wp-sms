<?php

namespace WSms\Auth;

use WSms\Support\IpResolver;

defined('ABSPATH') || exit;

class RateLimiter
{
    private const TRANSIENT_PREFIX = 'wsms_rl_';

    /**
     * Check if a request should be rate limited.
     *
     * @return array{allowed: bool, remaining: int, retry_after: int}
     */
    public function check(string $action, int $limit, int $window): array
    {
        $ip = IpResolver::resolve() ?: '0.0.0.0';
        $key = self::TRANSIENT_PREFIX . md5($action . '|' . $ip);

        $data = get_transient($key);

        if ($data === false) {
            set_transient($key, ['count' => 1, 'window_start' => time()], $window);

            return ['allowed' => true, 'remaining' => $limit - 1, 'retry_after' => 0];
        }

        if ($data['count'] >= $limit) {
            $retryAfter = ($data['window_start'] + $window) - time();

            return ['allowed' => false, 'remaining' => 0, 'retry_after' => max(0, $retryAfter)];
        }

        $data['count']++;
        $remainingTtl = ($data['window_start'] + $window) - time();
        set_transient($key, $data, max(1, $remainingTtl));

        return ['allowed' => true, 'remaining' => $limit - $data['count'], 'retry_after' => 0];
    }

    /**
     * Reset the rate limit for an action.
     */
    public function reset(string $action): void
    {
        $ip = IpResolver::resolve() ?: '0.0.0.0';
        $key = self::TRANSIENT_PREFIX . md5($action . '|' . $ip);

        delete_transient($key);
    }
}
