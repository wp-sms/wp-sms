<?php

namespace WP_SMS\Admin\MarketingCampaign;

use WP_SMS\Admin\ConditionTagEvaluator;

/**
 * Reads the stored marketing campaigns and returns the banner for the dashboard header.
 */
class MarketingCampaignFactory
{
    /**
     * The marketing banner to show in the header, or null when none is running for this site.
     *
     * A campaign counts only inside its own start and end time (UTC), so a daily fetch cannot show it
     * early or keep it after it ended, and only when all its audience tags match this site. Every field
     * is read defensively: a badly formed campaign is skipped, never an error.
     *
     * @return array{id:int, url:string, title:string, tooltip:string, icon:string, background:string, text:string}|null
     */
    public static function getHeaderBanner()
    {
        $raw       = get_option('wp_sms_marketing_campaigns', []);
        $campaigns = is_array($raw) && isset($raw['data']) && is_array($raw['data']) ? $raw['data'] : [];

        foreach ($campaigns as $campaign) {
            if (!is_array($campaign) || ($campaign['type'] ?? '') !== 'promo_banner') {
                continue;
            }

            if (!self::isRunning($campaign) || !self::matchesTags($campaign)) {
                continue;
            }

            $data = is_array($campaign['data'] ?? null) ? $campaign['data'] : [];
            $url  = isset($data['url']) ? esc_url_raw((string) $data['url']) : '';
            $text = isset($data['title']) ? sanitize_text_field((string) $data['title']) : '';

            if ($url === '' || $text === '') {
                continue;
            }

            return [
                'id'         => (int) ($campaign['id'] ?? 0),
                'url'        => $url,
                'title'      => $text,
                'tooltip'    => isset($data['tooltip']) ? sanitize_text_field((string) $data['tooltip']) : '',
                'icon'       => self::icon($data['icon'] ?? ''),
                'background' => in_array($data['background_color'] ?? '', ['inherit', 'danger', 'info', 'warning', 'success'], true) ? $data['background_color'] : 'danger',
                'text'       => in_array($data['text_color'] ?? '', ['inherit', 'dark', 'light'], true) ? $data['text_color'] : 'light',
            ];
        }

        return null;
    }

    /**
     * The feed sends the icon as a JSON encoded string, for example "\"🔥\"".
     */
    private static function icon($value)
    {
        $decoded = json_decode((string) $value);

        return sanitize_text_field(is_string($decoded) ? $decoded : (string) $value);
    }

    private static function isRunning(array $campaign)
    {
        $now    = time();
        $starts = self::utcTimestamp($campaign['starts_at'] ?? null);
        $ends   = self::utcTimestamp($campaign['ends_at'] ?? null);

        return !(($starts !== null && $starts > $now) || ($ends !== null && $ends <= $now));
    }

    private static function matchesTags(array $campaign)
    {
        if (empty($campaign['tags']) || !is_array($campaign['tags'])) {
            return true;
        }

        foreach ($campaign['tags'] as $tag) {
            if (!ConditionTagEvaluator::checkConditions($tag)) {
                return false;
            }
        }

        return true;
    }

    /**
     * A date that is empty or cannot be read counts as "not set", so a bad value never hides a campaign.
     */
    private static function utcTimestamp($value)
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $time = strtotime($value);

        return $time === false ? null : $time;
    }
}
