<?php

namespace WP_SMS\Admin\MarketingCampaign;

use Exception;
use WP_SMS\Components\RemoteRequest;

/**
 * Fetches the marketing campaigns (the banner in the dashboard header) that are managed in
 * Horizon, under Marketing Campaigns, and stores them in the WordPress database.
 */
class MarketingCampaignFetcher
{
    /**
     * API base URL for fetching marketing campaigns.
     *
     * @var string
     */
    private $apiUrl = 'https://connect.wsms.io';

    /**
     * @return bool True when the list was stored (an empty list is a valid answer: no campaign is running).
     */
    public function fetchMarketingCampaigns()
    {
        try {
            $pluginSlug    = basename(dirname(WP_SMS_MAIN_FILE));
            $url           = $this->apiUrl . '/api/v1/marketing-campaigns';
            $args          = ['plugin_slug' => $pluginSlug, 'per_page' => 20];
            $params        = [
                'timeout'     => 45,
                'redirection' => 5,
                'headers'     => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json; charset=utf-8',
                    'user-agent'   => $pluginSlug,
                ],
                'cookies'     => [],
            ];
            $remoteRequest = new RemoteRequest('GET', $url, $args, $params);
            $remoteRequest->execute(false, false);

            if ($remoteRequest->getResponseCode() !== 200) {
                return false;
            }

            $campaigns = json_decode($remoteRequest->getResponseBody(), true);

            // A broken answer keeps what is stored; only a real list, even an empty one, replaces it.
            if (!is_array($campaigns) || !isset($campaigns['data']) || !is_array($campaigns['data'])) {
                throw new Exception(sprintf('The marketing campaigns API returned an unexpected answer from %s', "{$url}?plugin_slug={$pluginSlug}"));
            }

            update_option('wp_sms_marketing_campaigns', ['data' => $campaigns['data']], false);

            return true;
        } catch (Exception $e) {
            WPSms()->log($e->getMessage(), 'error');

            return false;
        }
    }
}
