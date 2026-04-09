<?php

namespace WSms\Auth;

use WSms\Auth\Contracts\SessionTokenStoreInterface;
use WSms\Support\Clock;

defined('ABSPATH') || exit;

/**
 * Tracks per-session "fresh auth" timestamps so sensitive operations can
 * require a recent re-authentication independent of the WP auth cookie
 * lifetime.
 *
 * Semantics mirror Better Auth 1.6+: the window is anchored to the moment
 * the user actually proved identity (login or step-up), not to the last
 * request. Activity does NOT extend the window — this is the regression
 * guarded by the test at
 * packages/better-auth/src/api/routes/session-api.test.ts:68-120 upstream.
 *
 * Storage is per WP_Session_Tokens entry, so browser A's freshness does not
 * affect browser B even though they share a user_id.
 */
class FreshnessManager
{
    /** Key under which the fresh-auth timestamp lives inside the session entry. */
    public const SESSION_KEY = 'wsms_fresh_auth_at';

    /**
     * Per-request memo of the current session entry, keyed by
     * "{userId}:{token}". Cleared on markFresh so a subsequent isFresh
     * call within the same request sees the updated timestamp.
     *
     * @var array<string, array<string, mixed>|null>
     */
    private array $sessionCache = [];

    public function __construct(
        private SessionTokenStoreInterface $store,
        private Clock $clock,
    ) {
    }

    /**
     * Stamp the current session as just-authenticated.
     *
     * No-ops when the caller has no cookie-backed session token (e.g. app
     * password auth) — callers should not need to care, but Tier 1/2 gates
     * will naturally fail for such requests.
     */
    public function markFresh(int $userId): void
    {
        if ($userId <= 0) {
            return;
        }

        $token = $this->store->currentToken();
        if ($token === '') {
            return;
        }

        $this->store->update($userId, $token, [
            self::SESSION_KEY => $this->clock->now(),
        ]);

        // Invalidate the per-request cache so subsequent reads see the
        // just-written timestamp.
        unset($this->sessionCache[$userId . ':' . $token]);
    }

    /**
     * Is the current session still inside the fresh-auth window?
     *
     * Fallback chain when `wsms_fresh_auth_at` is missing on the session:
     *   1. Use WP's native `login` field (stamped at session creation by
     *      WP_Session_Tokens::create). This gives us a grace period on
     *      deploy day and for sessions created by third-party plugins
     *      (Jetpack, WP.com SSO, etc.) that do not route through our
     *      completeLogin().
     *   2. If neither field is present, return false.
     *
     * A window of 0 or below means "always step up" — every call returns
     * false and sensitive ops always require re-authentication.
     */
    public function isFresh(int $userId, int $maxAgeSeconds): bool
    {
        if ($maxAgeSeconds <= 0) {
            return false;
        }

        $timestamp = $this->currentTimestamp($userId);
        if ($timestamp === null) {
            return false;
        }

        return ($this->clock->now() - $timestamp) <= $maxAgeSeconds;
    }

    /**
     * Age in seconds since the last fresh auth, or null when unknown.
     *
     * Used by the frontend so it can show "your session is N minutes stale"
     * banners and preemptively trigger the step-up modal.
     */
    public function getFreshAge(int $userId): ?int
    {
        $timestamp = $this->currentTimestamp($userId);
        if ($timestamp === null) {
            return null;
        }

        return max(0, $this->clock->now() - $timestamp);
    }

    /**
     * Resolve the freshness timestamp for the current session in one place.
     *
     * Fetches (and memoizes) the session entry on first call per request,
     * then applies the `wsms_fresh_auth_at` → `login` fallback chain.
     */
    private function currentTimestamp(int $userId): ?int
    {
        if ($userId <= 0) {
            return null;
        }

        $token = $this->store->currentToken();
        if ($token === '') {
            return null;
        }

        $cacheKey = $userId . ':' . $token;
        if (!array_key_exists($cacheKey, $this->sessionCache)) {
            $this->sessionCache[$cacheKey] = $this->store->get($userId, $token);
        }

        $session = $this->sessionCache[$cacheKey];
        if ($session === null) {
            return null;
        }

        if (isset($session[self::SESSION_KEY]) && is_numeric($session[self::SESSION_KEY])) {
            return (int) $session[self::SESSION_KEY];
        }

        if (isset($session['login']) && is_numeric($session['login'])) {
            return (int) $session['login'];
        }

        return null;
    }
}
