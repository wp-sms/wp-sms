<?php

namespace WSms\Rest;

defined('ABSPATH') || exit;

/**
 * Builds URLs for the WSMS REST namespace.
 *
 * Centralizes `rest_url()` usage so all REST URLs go through a single helper
 * that correctly handles query args regardless of permalink settings (plain
 * vs pretty). Use `add_query_arg()` — never string-concatenate `?token=...`
 * onto a rest_url(), which breaks under plain permalinks because the base
 * URL already contains a query string (`?rest_route=/...`).
 */
final class RestRoute
{
    public const NAMESPACE = 'wsms/v1';

    /**
     * Build a full URL for a WSMS REST endpoint.
     *
     * @param string               $path Endpoint path under the namespace (e.g. 'dashboard/summary').
     * @param array<string, mixed> $args Query args to append.
     */
    public static function url(string $path = '', array $args = []): string
    {
        $path = ltrim($path, '/');
        $route = self::NAMESPACE . ($path !== '' ? '/' . $path : '');
        $url = rest_url($route);

        return $args ? add_query_arg($args, $url) : $url;
    }

    /**
     * Build a URL for the namespace root (trailing slash included).
     *
     * Used when localizing config to JS clients that concatenate endpoint
     * paths onto this base. Prefer exposing `wp.apiFetch` root to JS instead
     * of this — it handles both permalink modes automatically.
     */
    public static function root(): string
    {
        return rest_url(self::NAMESPACE . '/');
    }
}
