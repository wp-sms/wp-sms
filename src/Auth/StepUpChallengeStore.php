<?php

namespace WSms\Auth;

use WSms\Auth\Contracts\SessionTokenStoreInterface;
use WSms\Support\Clock;

defined('ABSPATH') || exit;

/**
 * Transient-backed store for pending step-up challenges.
 *
 * One transient per (session token, challenge id) tuple with a 5-minute TTL.
 * Binds each challenge to the WP session token that issued it — if the
 * session is destroyed, every challenge it created becomes unreachable.
 *
 * Per-session limits:
 * - Up to 5 simultaneously-active challenges; the 6th evicts the oldest
 *   (DoS hedge for an attacker holding a stolen cookie).
 * - Up to 5 verify failures on a single challenge; the 6th invalidates it.
 *
 * The store is deliberately free of any verification logic — it just holds
 * pending state. StepUpController wires it to the real auth primitives.
 */
class StepUpChallengeStore
{
    private const TRANSIENT_PREFIX = 'wsms_stepup_';
    private const INDEX_PREFIX = 'wsms_stepup_idx_';
    private const DEFAULT_TTL = 300; // 5 minutes
    public const MAX_ACTIVE_PER_SESSION = 5;
    public const MAX_ATTEMPTS_PER_CHALLENGE = 5;

    public function __construct(
        private SessionTokenStoreInterface $sessionStore,
        private Clock $clock,
    ) {
    }

    /**
     * Create a new pending challenge for the current session.
     *
     * @param array<string, mixed> $context Arbitrary per-method data (e.g. webauthn assertion options).
     * @return array{challenge_id: string, session_hash: string}|null Null when there is no session.
     */
    public function create(int $userId, string $method, array $context = []): ?array
    {
        $sessionHash = $this->currentSessionHash();
        if ($sessionHash === '') {
            return null;
        }

        $challengeId = $this->generateChallengeId();
        $entry = [
            'user_id'      => $userId,
            'method'       => $method,
            'context'      => $context,
            'attempts'     => 0,
            'created_at'   => $this->clock->now(),
            'session_hash' => $sessionHash,
        ];

        set_transient(self::TRANSIENT_PREFIX . $this->storageKey($sessionHash, $challengeId), $entry, self::DEFAULT_TTL);
        $this->pushIndex($sessionHash, $challengeId);

        return [
            'challenge_id' => $challengeId,
            'session_hash' => $sessionHash,
        ];
    }

    /**
     * Fetch a challenge entry scoped to the current session.
     *
     * @return array<string, mixed>|null
     */
    public function get(string $challengeId): ?array
    {
        $sessionHash = $this->currentSessionHash();
        if ($sessionHash === '' || $challengeId === '') {
            return null;
        }

        $data = get_transient(self::TRANSIENT_PREFIX . $this->storageKey($sessionHash, $challengeId));

        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Record a verification failure on a challenge. Returns the new attempt count.
     *
     * When the attempt count hits {@see MAX_ATTEMPTS_PER_CHALLENGE} the
     * challenge is destroyed automatically; callers can detect this via the
     * returned count ≥ the max.
     */
    public function recordFailure(string $challengeId): int
    {
        $sessionHash = $this->currentSessionHash();
        if ($sessionHash === '' || $challengeId === '') {
            return 0;
        }

        $key = self::TRANSIENT_PREFIX . $this->storageKey($sessionHash, $challengeId);
        $data = get_transient($key);

        if (!is_array($data)) {
            return 0;
        }

        $data['attempts'] = ((int) ($data['attempts'] ?? 0)) + 1;

        if ($data['attempts'] >= self::MAX_ATTEMPTS_PER_CHALLENGE) {
            delete_transient($key);
            $this->removeFromIndex($sessionHash, $challengeId);
            return $data['attempts'];
        }

        $remainingTtl = $this->remainingTtl($data['created_at'] ?? null);
        set_transient($key, $data, $remainingTtl);

        return $data['attempts'];
    }

    /**
     * Single-use consumption — call on successful verification.
     */
    public function consume(string $challengeId): void
    {
        $sessionHash = $this->currentSessionHash();
        if ($sessionHash === '' || $challengeId === '') {
            return;
        }

        delete_transient(self::TRANSIENT_PREFIX . $this->storageKey($sessionHash, $challengeId));
        $this->removeFromIndex($sessionHash, $challengeId);
    }

    private function generateChallengeId(): string
    {
        return bin2hex(random_bytes(16));
    }

    private function currentSessionHash(): string
    {
        $token = $this->sessionStore->currentToken();
        if ($token === '') {
            return '';
        }

        return hash('sha256', $token);
    }

    private function storageKey(string $sessionHash, string $challengeId): string
    {
        // Both components are hex strings, safe to concatenate for transient keys.
        return substr($sessionHash, 0, 16) . '_' . $challengeId;
    }

    private function remainingTtl(mixed $createdAt): int
    {
        if (!is_numeric($createdAt)) {
            return self::DEFAULT_TTL;
        }

        $elapsed = $this->clock->now() - (int) $createdAt;
        $remaining = self::DEFAULT_TTL - $elapsed;

        return max(1, $remaining);
    }

    /**
     * Maintain an LRU-ish index so we can enforce the per-session limit and
     * clean up without scanning the whole options table.
     */
    private function pushIndex(string $sessionHash, string $challengeId): void
    {
        $indexKey = self::INDEX_PREFIX . substr($sessionHash, 0, 16);
        $index = get_transient($indexKey);

        if (!is_array($index)) {
            $index = [];
        }

        $index[] = $challengeId;

        while (count($index) > self::MAX_ACTIVE_PER_SESSION) {
            $oldest = array_shift($index);
            delete_transient(self::TRANSIENT_PREFIX . $this->storageKey($sessionHash, $oldest));
        }

        set_transient($indexKey, $index, self::DEFAULT_TTL);
    }

    private function removeFromIndex(string $sessionHash, string $challengeId): void
    {
        $indexKey = self::INDEX_PREFIX . substr($sessionHash, 0, 16);
        $index = get_transient($indexKey);

        if (!is_array($index)) {
            return;
        }

        $filtered = array_values(array_filter($index, fn($id) => $id !== $challengeId));

        if (empty($filtered)) {
            delete_transient($indexKey);
        } else {
            set_transient($indexKey, $filtered, self::DEFAULT_TTL);
        }
    }
}
