<?php

namespace WP_SMS\Services\OTP\Models;

use WP_SMS\Contracts\Abstracts\AbstractBaseModel;

class AuthEventModel extends AbstractBaseModel
{
    public ?string $event_id = null;
    public ?string $flow_id = null;
    public ?string $timestamp_utc = null;
    public ?int $user_id = null;
    public ?string $channel = null;
    public ?string $event_type = null;
    public ?string $result = null;
    public ?string $client_ip_masked = null;
    public ?string $geo_country = null;
    public ?string $wp_role = null;
    public ?string $vendor_sid = null;
    public ?string $vendor_status = null;
    public ?string $factor_id = null;
    public ?int $attempt_count = null;
    public int $retention_days = 30;
    public ?string $user_agent = null;
    public ?string $device_type = null;

    protected static function getTableName(): string
    {
        return static::table('sms_auth_events');
    }

    /**
     * Insert a new authentication event.
     *
     * @param array $data
     * @return int Inserted row ID
     */
    public static function log(array $data): int
    {
        $data['event_id'] = $data['event_id'] ?? wp_generate_uuid4();
        $data['timestamp_utc'] = gmdate('Y-m-d H:i:s');
        $data['user_agent'] = $data['user_agent'] ?? ($_SERVER['HTTP_USER_AGENT'] ?? null);
        $data['device_type'] = $data['device_type'] ?? static::detectDeviceType($data['user_agent']);
        $data['retention_days'] = $data['retention_days'] ?? 30;

        return static::insert($data);
    }

    /**
     * Detect simple device type from user agent.
     *
     * @param string|null $ua
     * @return string
     */
    protected static function detectDeviceType(?string $ua): string
    {
        if (!$ua) return 'unknown';

        $ua = strtolower($ua);

        if (strpos($ua, 'mobile') !== false && strpos($ua, 'tablet') === false) {
            return 'mobile';
        }

        if (strpos($ua, 'tablet') !== false) {
            return 'tablet';
        }

        if (strpos($ua, 'bot') !== false || strpos($ua, 'crawl') !== false) {
            return 'bot';
        }

        return 'desktop';
    }

    /**
     * Get events by flow ID.
     */
    public function getByFlow(string $flowId): array
    {
        return $this->findAll(['flow_id' => $flowId], 100, 'id DESC');
    }

    /**
     * Get recent events for a user.
     */
    public function getRecentForUser(int $userId, int $limit = 10): array
    {
        return $this->findAll(['user_id' => $userId], $limit, 'id DESC');
    }
}
