<?php

namespace WSms\Mfa\Channels;

use WSms\Audit\AuditLogger;
use WSms\Enums\ChannelStatus;
use WSms\Enums\EventType;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Mfa\Contracts\SupportsEnrollmentConfirmation;
use WSms\Mfa\SecretEncryptor;
use WSms\Mfa\ValueObjects\ChallengeResult;
use WSms\Mfa\ValueObjects\EnrollmentResult;
use WSms\Dependencies\lbuchs\WebAuthn\WebAuthn;
use WSms\Dependencies\lbuchs\WebAuthn\Binary\ByteBuffer;
use WSms\Dependencies\lbuchs\WebAuthn\WebAuthnException;

defined('ABSPATH') || exit;

class PasskeyChannel implements ChannelInterface, SupportsEnrollmentConfirmation
{
    use HasUserFactor;

    private const CHALLENGE_TRANSIENT_PREFIX = 'wsms_passkey_challenge_';
    private const ENROLL_TRANSIENT_PREFIX = 'wsms_passkey_enroll_';
    private const CHALLENGE_TTL = 300; // 5 minutes

    public function __construct(
        private AuditLogger $auditLogger,
        private SecretEncryptor $encryptor,
    ) {
    }

    public function getId(): string
    {
        return 'passkey';
    }

    public function getName(): string
    {
        return 'Passkey';
    }

    public function supportsPrimaryAuth(): bool
    {
        return false;
    }

    public function supportsMfa(): bool
    {
        return true;
    }

    public function supportsAutoEnrollment(): bool
    {
        return false;
    }

    public function isAvailableForUser(int $userId): bool
    {
        return $this->isEnrolled($userId);
    }

    protected function getConfigPrefix(): string
    {
        return 'passkey';
    }

    /** {@inheritDoc} */
    public function enroll(int $userId, array $data): EnrollmentResult
    {
        if (!$this->isSecureContext()) {
            return new EnrollmentResult(false, __('Passkeys require a secure connection (HTTPS).', 'wp-sms'));
        }

        $existing = $this->getFactor($userId);
        $isAddingCredential = $existing && $existing->status === ChannelStatus::Active;
        $existingCredentials = $isAddingCredential ? ($existing->meta['credentials'] ?? []) : [];

        $webauthn = $this->createWebAuthn();
        $user = get_userdata($userId);
        $userName = $user ? $user->user_email : "user-{$userId}";
        $displayName = $user ? $user->display_name : $userName;

        $excludeIds = array_map(
            fn ($c) => $this->base64UrlDecode($c['id']),
            $existingCredentials,
        );

        $args = $webauthn->getCreateArgs(
            (string) $userId,
            $userName,
            $displayName,
            60,
            'preferred',
            'preferred',
            null,
            $excludeIds,
        );

        $challenge = $webauthn->getChallenge()->getBinaryString();
        $encodedChallenge = base64_encode($challenge);

        if ($isAddingCredential) {
            set_transient(
                self::ENROLL_TRANSIENT_PREFIX . $userId,
                wp_json_encode([
                    'challenge'   => $encodedChallenge,
                    'credentials' => $existingCredentials,
                ]),
                self::CHALLENGE_TTL,
            );
        } else {
            // Fresh enrollment — store challenge in factor meta.
            $meta = [
                'challenge'          => $encodedChallenge,
                'challenge_created_at' => time(),
                'credentials'        => $existingCredentials,
            ];

            if ($existing) {
                $this->updateFactor($existing->id, [
                    'status' => ChannelStatus::Pending->value,
                    'meta'   => wp_json_encode($meta),
                ]);
            } else {
                $this->createFactor($userId, ChannelStatus::Pending, $meta);
            }
        }

        return new EnrollmentResult(true, __('Register your passkey using your browser.', 'wp-sms'), [
            'requires_confirmation' => true,
            'creation_options'      => $args->publicKey,
        ]);
    }

    /** {@inheritDoc} */
    public function confirmEnrollment(int $userId, string $code): EnrollmentResult
    {
        $factor = $this->getFactor($userId);

        if (!$factor) {
            return new EnrollmentResult(false, __('No pending enrollment found.', 'wp-sms'));
        }

        // Check transient first (add-credential flow keeps factor Active).
        $transientKey = self::ENROLL_TRANSIENT_PREFIX . $userId;
        $transientData = get_transient($transientKey);
        $isAddingCredential = false;

        if ($transientData) {
            $enrollData = json_decode($transientData, true);
            $storedChallenge = $enrollData['challenge'] ?? '';
            $existingCredentials = $enrollData['credentials'] ?? [];
            $isAddingCredential = true;
            delete_transient($transientKey);
        } elseif ($factor->status === ChannelStatus::Pending) {
            // Fresh enrollment — challenge stored in factor meta with TTL.
            $storedChallenge = $factor->meta['challenge'] ?? '';
            $existingCredentials = $factor->meta['credentials'] ?? [];
            $challengeCreatedAt = $factor->meta['challenge_created_at'] ?? 0;

            if ($challengeCreatedAt && (time() - $challengeCreatedAt) > self::CHALLENGE_TTL) {
                return new EnrollmentResult(false, __('Enrollment challenge has expired. Please start again.', 'wp-sms'));
            }
        } else {
            return new EnrollmentResult(false, __('No pending enrollment found.', 'wp-sms'));
        }

        if (empty($storedChallenge)) {
            return new EnrollmentResult(false, __('Invalid enrollment state.', 'wp-sms'));
        }

        $attestation = json_decode($code, true);

        if (!$attestation || !isset($attestation['response'])) {
            return new EnrollmentResult(false, __('Invalid attestation response.', 'wp-sms'));
        }

        try {
            $webauthn = $this->createWebAuthn();
            $challenge = base64_decode($storedChallenge);

            $clientDataJSON = $this->base64UrlDecode($attestation['response']['clientDataJSON']);
            $attestationObject = $this->base64UrlDecode($attestation['response']['attestationObject']);

            $result = $webauthn->processCreate(
                $clientDataJSON,
                $attestationObject,
                new ByteBuffer($challenge),
                false, // requireUserVerification
                true,  // requireUserPresent
                false, // failIfRootMismatch (attestation='none')
            );

            $credentialId = $this->base64UrlEncode($result->credentialId);
            $publicKeyPem = $result->credentialPublicKey;
            $signCount = $result->signatureCounter ?? 0;
            $aaguid = $result->AAGUID instanceof ByteBuffer ? $result->AAGUID->getHex() : ($result->AAGUID ?: null);

            // Determine device type and backup status from flags.
            $deviceType = $result->isBackupEligible ? 'multiDevice' : 'singleDevice';
            $backedUp = $result->isBackedUp ?? false;

            // Extract transports from attestation response.
            $transports = $attestation['response']['transports'] ?? [];

            // Credential name from data or derive from context.
            $name = $attestation['name'] ?? $this->deriveCredentialName();

            // Encrypt the public key.
            $encryptedKey = $this->encryptor->encrypt($publicKeyPem, $userId);

            $newCredential = [
                'id'          => $credentialId,
                'public_key'  => $encryptedKey,
                'sign_count'  => $signCount,
                'device_type' => $deviceType,
                'backed_up'   => $backedUp,
                'transports'  => $transports,
                'aaguid'      => $aaguid,
                'name'        => $name,
                'created_at'  => gmdate('c'),
            ];

            $credentials = array_merge($existingCredentials, [$newCredential]);

            $this->updateFactor($factor->id, [
                'status' => ChannelStatus::Active->value,
                'meta'   => wp_json_encode(['credentials' => $credentials]),
            ]);

            $this->auditLogger->log(EventType::MfaEnrolled, 'success', $userId, [
                'channel' => $this->getId(),
            ]);

            return new EnrollmentResult(true, __('Passkey enrolled successfully.', 'wp-sms'));
        } catch (WebAuthnException $e) {
            return new EnrollmentResult(false, __('Device registration failed. Please try again.', 'wp-sms'));
        }
    }

    /** {@inheritDoc} */
    public function sendChallenge(int $userId, array $context = []): ChallengeResult
    {
        $factor = $this->getFactor($userId);

        if (!$factor || $factor->status !== ChannelStatus::Active) {
            return new ChallengeResult(false, __('Passkey not enrolled.', 'wp-sms'));
        }

        $credentials = $factor->meta['credentials'] ?? [];

        if (empty($credentials)) {
            return new ChallengeResult(false, __('No passkey credentials found.', 'wp-sms'));
        }

        $webauthn = $this->createWebAuthn();

        $credentialIds = array_map(
            fn ($c) => $this->base64UrlDecode($c['id']),
            $credentials,
        );

        $args = $webauthn->getGetArgs(
            $credentialIds,
            60,     // timeout (seconds)
            true,   // allowUsb
            true,   // allowNfc
            true,   // allowBle
            true,   // allowHybrid
            true,   // allowInternal
            'preferred', // userVerification
        );

        $challenge = $webauthn->getChallenge()->getBinaryString();

        // Store challenge in transient (single-use).
        set_transient(
            self::CHALLENGE_TRANSIENT_PREFIX . $userId,
            base64_encode($challenge),
            self::CHALLENGE_TTL,
        );

        return new ChallengeResult(true, __('Verify with your passkey.', 'wp-sms'), [
            'requires_delivery' => false,
            'assertion_options' => $args->publicKey,
            'channel_type'      => 'passkey',
        ]);
    }

    /** {@inheritDoc} */
    public function verify(int $userId, string $code, array $context = []): bool
    {
        // Retrieve and delete challenge (single-use).
        $transientKey = self::CHALLENGE_TRANSIENT_PREFIX . $userId;
        $storedChallenge = get_transient($transientKey);
        delete_transient($transientKey);

        if (!$storedChallenge) {
            $this->auditLogger->log(EventType::PasskeyFailed, 'failure', $userId, [
                'reason' => 'challenge_expired',
            ]);

            return false;
        }

        $assertion = json_decode($code, true);

        if (!$assertion || !isset($assertion['response'], $assertion['id'])) {
            $this->auditLogger->log(EventType::PasskeyFailed, 'failure', $userId, [
                'reason' => 'invalid_response',
            ]);

            return false;
        }

        $factor = $this->getFactor($userId);

        if (!$factor || $factor->status !== ChannelStatus::Active) {
            return false;
        }

        $credentials = $factor->meta['credentials'] ?? [];
        $assertionCredentialId = $assertion['id'];

        // Find matching credential by ID.
        $matchedIndex = null;
        $matchedCredential = null;

        foreach ($credentials as $index => $cred) {
            if ($cred['id'] === $assertionCredentialId) {
                $matchedIndex = $index;
                $matchedCredential = $cred;
                break;
            }
        }

        if ($matchedCredential === null) {
            $this->auditLogger->log(EventType::PasskeyFailed, 'failure', $userId, [
                'reason' => 'credential_not_found',
            ]);

            return false;
        }

        try {
            $webauthn = $this->createWebAuthn();
            $challenge = base64_decode($storedChallenge);

            $clientDataJSON = $this->base64UrlDecode($assertion['response']['clientDataJSON']);
            $authenticatorData = $this->base64UrlDecode($assertion['response']['authenticatorData']);
            $signature = $this->base64UrlDecode($assertion['response']['signature']);

            $publicKeyPem = $this->encryptor->decrypt($matchedCredential['public_key'], $userId);
            $prevSignCount = $matchedCredential['sign_count'] ?? 0;

            $webauthn->processGet(
                $clientDataJSON,
                $authenticatorData,
                $signature,
                $publicKeyPem,
                new ByteBuffer($challenge),
                $prevSignCount,
                false, // requireUserVerification
                true,  // requireUserPresent
            );

            // Update sign count.
            $newSignCount = $webauthn->getSignatureCounter();
            if ($newSignCount !== null) {
                $credentials[$matchedIndex]['sign_count'] = $newSignCount;
            }

            $this->updateFactor($factor->id, [
                'meta' => wp_json_encode(['credentials' => $credentials]),
            ]);

            $this->auditLogger->log(EventType::PasskeyVerified, 'success', $userId);

            return true;
        } catch (WebAuthnException $e) {
            $reason = match ($e->getCode()) {
                WebAuthnException::INVALID_CHALLENGE  => 'challenge_mismatch',
                WebAuthnException::INVALID_SIGNATURE  => 'signature_invalid',
                WebAuthnException::SIGNATURE_COUNTER  => 'clone_detected',
                default                               => 'verification_failed',
            };

            $this->auditLogger->log(EventType::PasskeyFailed, 'failure', $userId, [
                'reason' => $reason,
            ]);

            return false;
        }
    }

    /** {@inheritDoc} */
    public function unenroll(int $userId): bool
    {
        $factor = $this->getFactor($userId);

        if ($factor === null) {
            return false;
        }

        // Clean up any pending transients.
        delete_transient(self::CHALLENGE_TRANSIENT_PREFIX . $userId);
        delete_transient(self::ENROLL_TRANSIENT_PREFIX . $userId);

        // Clear all credentials (wipe encrypted keys) and disable.
        $this->updateFactor($factor->id, [
            'status' => ChannelStatus::Disabled->value,
            'meta'   => wp_json_encode([]),
        ]);

        $this->auditLogger->log(EventType::MfaUnenrolled, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return true;
    }

    /** {@inheritDoc} */
    public function getEnrollmentInfo(int $userId): array
    {
        $factor = $this->getFactor($userId);

        if ($factor === null) {
            return ['enrolled' => false];
        }

        $credentials = $factor->meta['credentials'] ?? [];

        return [
            'enrolled'         => $factor->status === ChannelStatus::Active,
            'status'           => $factor->status->value,
            'channel'          => $this->getId(),
            'credential_count' => count($credentials),
            'credentials'      => array_map(fn ($c) => [
                'id'          => $c['id'],
                'name'        => $c['name'] ?? 'Unknown',
                'device_type' => $c['device_type'] ?? 'unknown',
                'backed_up'   => $c['backed_up'] ?? false,
                'created_at'  => $c['created_at'] ?? null,
            ], $credentials),
        ];
    }

    /**
     * Remove a single credential by ID.
     *
     * If this is the last credential, the factor is fully unenrolled.
     *
     * @return bool True if the credential was found and removed.
     */
    public function removeCredential(int $userId, string $credentialId): bool
    {
        $factor = $this->getFactor($userId);

        if ($factor === null || $factor->status !== ChannelStatus::Active) {
            return false;
        }

        $credentials = $factor->meta['credentials'] ?? [];
        $filtered = array_values(array_filter(
            $credentials,
            fn ($c) => $c['id'] !== $credentialId,
        ));

        if (count($filtered) === count($credentials)) {
            return false; // Credential not found.
        }

        if (empty($filtered)) {
            // Last credential — unenroll entirely.
            return $this->unenroll($userId);
        }

        $this->updateFactor($factor->id, [
            'meta' => wp_json_encode(['credentials' => $filtered]),
        ]);

        return true;
    }

    private function createWebAuthn(): WebAuthn
    {
        $rpName = get_bloginfo('name') ?: 'WordPress';
        $rpId = wp_parse_url(home_url(), PHP_URL_HOST);

        return new WebAuthn($rpName, $rpId, ['none'], true);
    }

    private function isSecureContext(): bool
    {
        if (is_ssl()) {
            return true;
        }

        // Localhost is always a secure context.
        $host = wp_parse_url(home_url(), PHP_URL_HOST);

        return is_string($host)
            && (in_array($host, ['localhost', '127.0.0.1', '::1'], true)
                || str_ends_with($host, '.local'));
    }

    private function deriveCredentialName(): string
    {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';

        if (str_contains($ua, 'Chrome') && !str_contains($ua, 'Edg')) {
            $browser = 'Chrome';
        } elseif (str_contains($ua, 'Safari') && !str_contains($ua, 'Chrome')) {
            $browser = 'Safari';
        } elseif (str_contains($ua, 'Firefox')) {
            $browser = 'Firefox';
        } elseif (str_contains($ua, 'Edg')) {
            $browser = 'Edge';
        } else {
            $browser = 'Browser';
        }

        if (str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS')) {
            $os = 'macOS';
        } elseif (str_contains($ua, 'Windows')) {
            $os = 'Windows';
        } elseif (str_contains($ua, 'Android')) {
            $os = 'Android';
        } elseif (str_contains($ua, 'iPhone') || str_contains($ua, 'iPad')) {
            $os = 'iOS';
        } elseif (str_contains($ua, 'Linux')) {
            $os = 'Linux';
        } else {
            $os = 'Unknown';
        }

        return "{$browser} on {$os}";
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $data .= str_repeat('=', 4 - $remainder);
        }

        return base64_decode(strtr($data, '-_', '+/'));
    }

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
