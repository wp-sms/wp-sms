<?php

namespace WP_SMS\Services\OTP\Channels\Otp;

use WP_SMS\Services\OTP\Models\OtpSessionModel;
use WP_User;

class OtpService
{
    protected int $defaultTtl = 300; // in seconds
    protected int $defaultCodeLength = 6;

    /**
     * Generate a new OTP and persist it in the database.
     */
    public function generate(string $flowId, int $userId): string
    {
        // Generate secure OTP
        $length = $this->defaultCodeLength;
        $length = max(4, min(10, $length));
        $hash = bin2hex(openssl_random_pseudo_bytes(16));
        $values = array_values(unpack('C*', $hash));
        $offset = ($values[count($values) - 1] & 0xF);
        $code = ($values[$offset + 0] & 0x7F) << 24
            | ($values[$offset + 1] & 0xFF) << 16
            | ($values[$offset + 2] & 0xFF) << 8
            | ($values[$offset + 3] & 0xFF);
        $otp = $code % (10 ** $length);
        $otpCode = str_pad((string) $otp, $length, '0', STR_PAD_LEFT);

        // Save to DB
        OtpSessionModel::createSession(
            flowId: $flowId,
            userId: $userId,
            code: $otpCode,
            expiresInSeconds: $this->defaultTtl
        );

        return $otpCode;
    }


    /**
     * Validate an input OTP against the stored session.
     */
    public function validate(string $flowId, string $input): bool
    {
        $record = OtpSessionModel::find(['flow_id' => $flowId]);

        if (! $record) {
            return false;
        }

        if (strtotime($record['expires_at']) < time()) {
            $this->invalidate($flowId);
            return false;
        }

        $inputHash = hash('sha256', $input);
        $isValid = hash_equals($record['code_hash'], $inputHash);

        if ($isValid) {
            $this->invalidate($flowId);
        }

        return $isValid;
    }

    /**
     * Manually invalidate an OTP session.
     */
    public function invalidate(string $flowId): void
    {
        OtpSessionModel::deleteBy(['flow_id' => $flowId]);
    }

    /**
     * Check whether a session exists and is not expired.
     */
    public function exists(string $flowId): bool
    {
        $record = OtpSessionModel::find(['flow_id' => $flowId]);

        if (! $record) {
            return false;
        }

        return strtotime($record['expires_at']) >= time();
    }
}
