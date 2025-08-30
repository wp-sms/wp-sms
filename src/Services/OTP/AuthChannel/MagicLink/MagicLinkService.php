<?php

namespace WP_SMS\Services\OTP\AuthChannel\MagicLink;

use WP_SMS\Services\OTP\Models\MagicLinkModel;
use WP_SMS\Services\OTP\AuthChannel\MagicLink\MagicLinkPayload;
use WP_SMS\Services\OTP\Contracts\Interfaces\AuthChannelInterface;

class MagicLinkService implements AuthChannelInterface
{
    protected int $defaultTtl = 600;
    protected string $loginUrlBase = '/?magic_login=1';

    public function getKey(): string
    {
        return 'magic_link';
    }

    public function exists(string $flowId): bool
    {
        return MagicLinkModel::exists(['flow_id' => $flowId]);
    }

    
    /**
     * Generate a new magic login link for a given user and flow ID.
     */
    public function generate(string $flowId): string
    {
        $token = bin2hex(random_bytes(16)); // Secure 32-char token

        MagicLinkModel::createSession(
            flowId: $flowId,
            token: $token,
            expiresInSeconds: $this->defaultTtl
        );

        return $this->buildUrl($token, $flowId);
    }

    /**
     * Build a fully-qualified magic login URL.
     */
    public function buildUrl(string $token, string $flowId): string
    {
        $query = http_build_query([
            'magic_token' => $token,
            'flow_id'     => $flowId,
        ]);

        return home_url($this->loginUrlBase) . '&' . $query;
    }

    /**
     * Validate a magic link token and return the user ID if valid.
     */
    public function validate(string $flowId, string $inputToken): ?string
    {
        $record = MagicLinkModel::find(['flow_id' => $flowId]);

        if (! $record) {
            return null;
        }

        $payload = new MagicLinkPayload(
            flowId: $record['flow_id'],
            tokenHash: $record['token_hash'],
            expiresAt: strtotime($record['expires_at']),
            usedAt: $record['used_at'] ? strtotime($record['used_at']) : null,
        );

        if ($payload->isExpired() || $payload->isUsed()) {
            return null;
        }

        if (! $payload->matchesToken($inputToken)) {
            return null;
        }

        // Mark the token as used (one-time use)
        MagicLinkModel::markAsUsed($flowId);

        return $payload->flowId;
    }

    /**
     * Invalidate a magic link (e.g., expired manually).
     */
    public function invalidate(string $flowId): void
    {
        MagicLinkModel::deleteByFlowId($flowId);
    }
}
