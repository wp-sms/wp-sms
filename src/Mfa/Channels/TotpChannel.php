<?php

namespace WSms\Mfa\Channels;

use WSms\Audit\AuditLogger;
use WSms\Enums\ChannelStatus;
use WSms\Enums\EventType;
use WSms\Mfa\Contracts\ChannelInterface;
use WSms\Mfa\Contracts\SupportsEnrollmentConfirmation;
use WSms\Mfa\ValueObjects\ChallengeResult;
use WSms\Mfa\ValueObjects\EnrollmentResult;
use WSms\Dependencies\BaconQrCode\Renderer\Image\SvgImageBackEnd;
use WSms\Dependencies\BaconQrCode\Renderer\ImageRenderer;
use WSms\Dependencies\BaconQrCode\Renderer\RendererStyle\RendererStyle;
use WSms\Dependencies\BaconQrCode\Writer;
use WSms\Dependencies\OTPHP\TOTP;

defined('ABSPATH') || exit;

class TotpChannel implements ChannelInterface, SupportsEnrollmentConfirmation
{
    use HasUserFactor;

    public function __construct(
        private AuditLogger $auditLogger,
    ) {
    }

    public function getId(): string
    {
        return 'totp';
    }

    public function getName(): string
    {
        return 'Authenticator App';
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
        return 'totp';
    }

    /** {@inheritDoc} */
    public function enroll(int $userId, array $data): EnrollmentResult
    {
        $existing = $this->getFactor($userId);

        if ($existing && $existing->status === ChannelStatus::Active) {
            return new EnrollmentResult(false, 'Already enrolled in Authenticator App.');
        }

        $totp = TOTP::generate();
        $totp->setIssuer(get_bloginfo('name'));

        $user = get_userdata($userId);
        $totp->setLabel($user ? $user->user_email : "user-{$userId}");

        $secret = $totp->getSecret();
        $uri = $totp->getProvisioningUri();

        $qrCodeUri = $this->generateQrCodeDataUri($uri);

        if ($existing) {
            $this->updateFactor($existing->id, [
                'status' => ChannelStatus::Pending->value,
                'meta'   => wp_json_encode(['secret' => $secret]),
            ]);
        } else {
            $this->createFactor($userId, ChannelStatus::Pending, ['secret' => $secret]);
        }

        return new EnrollmentResult(true, 'Scan the QR code with your authenticator app.', [
            'requires_confirmation' => true,
            'qr_code_uri'          => $qrCodeUri,
            'secret'               => $secret,
            'issuer'               => $totp->getIssuer(),
        ]);
    }

    /** {@inheritDoc} */
    public function confirmEnrollment(int $userId, string $code): EnrollmentResult
    {
        $factor = $this->getFactor($userId);

        if (!$factor || $factor->status !== ChannelStatus::Pending) {
            return new EnrollmentResult(false, 'No pending enrollment found.');
        }

        $secret = $factor->meta['secret'] ?? '';

        if (empty($secret)) {
            return new EnrollmentResult(false, 'Invalid enrollment state.');
        }

        $totp = TOTP::createFromSecret($secret);

        if (!$totp->verify($code, null, 1)) {
            return new EnrollmentResult(false, 'Invalid verification code.');
        }

        $currentTimestamp = (int) floor(time() / $totp->getPeriod());

        $this->updateFactor($factor->id, [
            'status' => ChannelStatus::Active->value,
            'meta'   => wp_json_encode([
                'secret'              => $secret,
                'last_used_timestamp' => $currentTimestamp,
            ]),
        ]);

        $this->auditLogger->log(EventType::MfaEnrolled, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return new EnrollmentResult(true, 'Authenticator app enrolled successfully.');
    }

    /** {@inheritDoc} */
    public function sendChallenge(int $userId, array $context = []): ChallengeResult
    {
        return new ChallengeResult(true, 'Enter the code from your authenticator app.', [
            'requires_delivery' => false,
        ]);
    }

    /** {@inheritDoc} */
    public function verify(int $userId, string $code, array $context = []): bool
    {
        $factor = $this->getFactor($userId);

        if ($factor === null || $factor->status !== ChannelStatus::Active) {
            return false;
        }

        $secret = $factor->meta['secret'] ?? '';

        if (empty($secret)) {
            return false;
        }

        $totp = TOTP::createFromSecret($secret);

        $currentTimestamp = (int) floor(time() / $totp->getPeriod());
        $lastUsed = $factor->meta['last_used_timestamp'] ?? 0;

        // Anti-replay: reject if the same time window was already used.
        if ($currentTimestamp === (int) $lastUsed) {
            $this->auditLogger->log(EventType::TotpFailed, 'failure', $userId, [
                'reason' => 'replay',
            ]);

            return false;
        }

        if (!$totp->verify($code, null, 1)) {
            $this->auditLogger->log(EventType::TotpFailed, 'failure', $userId);

            return false;
        }

        // Update last used timestamp.
        $this->updateFactor($factor->id, [
            'meta' => wp_json_encode([
                'secret'              => $secret,
                'last_used_timestamp' => $currentTimestamp,
            ]),
        ]);

        $this->auditLogger->log(EventType::TotpVerified, 'success', $userId);

        return true;
    }

    /** {@inheritDoc} */
    public function unenroll(int $userId): bool
    {
        $factor = $this->getFactor($userId);

        if ($factor === null) {
            return false;
        }

        // Clear secret from meta and disable.
        $this->updateFactor($factor->id, [
            'status' => ChannelStatus::Disabled->value,
            'meta'   => wp_json_encode([]),
        ]);

        $this->auditLogger->log(EventType::MfaUnenrolled, 'success', $userId, [
            'channel' => $this->getId(),
        ]);

        return true;
    }

    private function generateQrCodeDataUri(string $uri): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle(200),
            new SvgImageBackEnd(),
        );
        $writer = new Writer($renderer);
        $svg = $writer->writeString($uri);

        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }
}
