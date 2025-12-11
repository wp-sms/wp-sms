<?php

namespace WP_SMS\Traits;

if (!defined('ABSPATH')) exit;

/**
 * Trait to handle caching logic.
 * @doc https://github.com/wp-sms/wp-sms/wiki/TransientCacheTrait.md
 */
trait TransientCacheTrait
{
    /**
     * Get the cache key for the given input.
     *
     * @param string $input
     *
     * @return string
     */
    public function getCacheKey($input)
    {
        $hash = substr(md5($input), 0, 10);
        return sprintf('wp_sms_cache_%s', $hash);
    }

    /**
     * Get the cached result for the given input.
     *
     * @param string $input
     *
     * @return mixed
     */
    public function getCachedResult($input)
    {
        $cacheKey = $this->getCacheKey($input);
        return get_transient($cacheKey);
    }

    /**
     * Set the cached result for the given input.
     *
     * @param string $input
     * @param mixed $result
     * @param int $expiration Expiration time for the cache in seconds.
     *
     * @return bool
     */
    public function setCachedResult($input, $result, $expiration = DAY_IN_SECONDS)
    {
        $cacheKey = $this->getCacheKey($input);
        return set_transient($cacheKey, $result, $expiration);
    }

    /**
     * Clear the cached result for the given query.
     *
     * @param string $query
     *
     * @return bool
     */
    public function clearCache($query)
    {
        $cacheKey = $this->getCacheKey($query);
        return delete_transient($cacheKey);
    }
}
