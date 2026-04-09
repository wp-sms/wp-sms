<?php

namespace WSms\Tests\Support;

use WSms\Auth\Contracts\SessionTokenStoreInterface;

/**
 * In-memory SessionTokenStore for unit tests.
 *
 * Sessions are keyed by "{userId}:{token}". Use {@see setCurrentToken()} to
 * control what `currentToken()` returns and {@see seed()} to pre-populate
 * session entries.
 */
class FakeSessionTokenStore implements SessionTokenStoreInterface
{
    private string $currentToken = '';

    /** @var array<string, array<string, mixed>> */
    private array $sessions = [];

    public function currentToken(): string
    {
        return $this->currentToken;
    }

    public function setCurrentToken(string $token): void
    {
        $this->currentToken = $token;
    }

    public function get(int $userId, string $token): ?array
    {
        return $this->sessions[$this->key($userId, $token)] ?? null;
    }

    public function update(int $userId, string $token, array $data): void
    {
        $key = $this->key($userId, $token);
        $existing = $this->sessions[$key] ?? null;

        if ($existing === null) {
            return;
        }

        $this->sessions[$key] = array_merge($existing, $data);
    }

    /**
     * Pre-populate a session entry.
     *
     * @param array<string, mixed> $data
     */
    public function seed(int $userId, string $token, array $data): void
    {
        $this->sessions[$this->key($userId, $token)] = $data;
    }

    public function destroy(int $userId, string $token): void
    {
        unset($this->sessions[$this->key($userId, $token)]);
    }

    private function key(int $userId, string $token): string
    {
        return $userId . ':' . $token;
    }
}
