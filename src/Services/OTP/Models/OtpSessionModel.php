<?php

namespace WP_SMS\Services\OTP\Models;

use WP_SMS\Contracts\Abstracts\AbstractBaseModel;
use WP_SMS\Utils\DateUtils;

class OtpSessionModel extends AbstractBaseModel
{
    public ?string $flow_id = null;
    public ?string $identifier = null;
    public ?string $identifier_type = null;
    public ?string $code_hash = null;
    public ?string $expires_at = null;
    public ?int $attempt_count = null;
    public ?string $channel = null;
    public ?string $created_at = null;

    protected static function getTableName(): string
    {
        return static::table('sms_otp_sessions');
    }

    /**
     * Create a new OTP session.
     */
    public static function createSession(string $flowId, string $code, int $expiresInSeconds, ?string $phone = null, ?string $email = null, string $channel = 'sms'): string
    {
        $now = current_time('mysql');
        $expires = gmdate('Y-m-d H:i:s', time() + $expiresInSeconds);
        $hash = hash('sha256', $code);

        $data = [
            'flow_id'    => $flowId,
            'otp_hash'  => $hash,
            'expires_at' => $expires,
            'channel'    => $channel,
            'created_at' => $now,
        ];

        if ($phone) {
            $data['identifier'] = $phone;
            $data['identifier_type'] = 'phone';
        }
        if ($email) {
            $data['identifier'] = $email;
            $data['identifier_type'] = 'email';
        }

        static::insert($data);

        return $flowId;
    }

    /**
     * Validate OTP session by flow ID and code.
     */
    public function validateSession(string $flowId, string $inputCode): bool
    {
        $record = $this->find(['flow_id' => $flowId]);

        if (!$record) {
            return false;
        }

        if (strtotime($record['expires_at']) < time()) {
            return false;
        }

        return hash_equals($record['code_hash'], hash('sha256', $inputCode));
    }

    /**
     * Destroy OTP session by flow ID.
     */
    public function destroySession(string $flowId): void
    {
        static::deleteBy(['flow_id' => $flowId]);
    }

    /**
     * Check if there's an unexpired session for a phone number
     */
    public static function hasUnexpiredSession(string $phone): bool
    {
        $sessions = static::findAll(['identifier' => $phone, 'identifier_type' => 'phone']);
        
        foreach ($sessions as $session) {
            if (strtotime($session['expires_at']) > time()) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if there's an unexpired session for an email
     */
    public static function hasUnexpiredSessionByEmail(string $email): bool
    {
        $sessions = static::findAll(['identifier' => $email, 'identifier_type' => 'email']);
        
        foreach ($sessions as $session) {
            if (strtotime($session['expires_at']) > time()) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get the most recent unexpired session for a phone number
     */
    public static function getMostRecentUnexpiredSession(string $phone): ?array
    {
        $sessions = static::findAll(['identifier' => $phone, 'identifier_type' => 'phone']);
        
        $unexpiredSessions = array_filter($sessions, function($session) {
            return strtotime($session['expires_at']) > time();
        });
        
        if (empty($unexpiredSessions)) {
            return null;
        }
        
        // Sort by created_at descending and return the most recent
        usort($unexpiredSessions, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $unexpiredSessions[0];
    }

    /**
     * Get the most recent unexpired session for an email
     */
    public static function getMostRecentUnexpiredSessionByEmail(string $email): ?array
    {
        $sessions = static::findAll(['identifier' => $email, 'identifier_type' => 'email']);
        
        $unexpiredSessions = array_filter($sessions, function($session) {
            return strtotime($session['expires_at']) > time();
        });
        
        if (empty($unexpiredSessions)) {
            return null;
        }
        
        // Sort by created_at descending and return the most recent
        usort($unexpiredSessions, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return $unexpiredSessions[0];
    }

    public static function getByFlowId(string $flowId)
    {
        return static::find(['flow_id' => $flowId]);
    }
}
