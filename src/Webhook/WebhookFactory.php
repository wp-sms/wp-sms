<?php

namespace WP_SMS\Webhook;

use WP_SMS\Option;

/**
 * Class WebhookFactory
 *
 * @package WP_SMS\Webhook
 */
class WebhookFactory
{
    /**
     * Subscribe webhook
     *
     * @param $webhookUrl
     * @param $webhookType
     * @return void
     */
    public static function subscribeWebhook($webhookUrl, $webhookType)
    {
        $webhooks = self::getWebhooks($webhookType);

        /**
         * Append webhook url
         */
        $webhooks[] = sanitize_url($webhookUrl);

        self::updateWebhook($webhookType, $webhooks);
    }

    /**
     * Unsubscribe webhook
     *
     * @param $webhookUrl
     * @param $webhookType
     * @return void
     */
    public static function unsubscribeWebhook($webhookUrl, $webhookType)
    {
        $webhooks = self::getWebhooks($webhookType);

        /**
         * Remove webhook url
         */
        if (($key = array_search($webhookUrl, $webhooks)) !== false) {
            unset($webhooks[$key]);
        }

        self::updateWebhook($webhookType, $webhooks);
    }

    /**
     * Get webhooks from database
     *
     * @param $webhookType
     * @return false|string[]
     */
    public static function getWebhooks($webhookType)
    {
        $webhookName = self::getWebhookOptionKeyByType($webhookType);
        $webhooks    = Option::getOption($webhookName);

        if ($webhooks) {
            return explode(PHP_EOL, $webhooks);
        }

        return [];
    }

    /**
     * Update webhook in database
     *
     * @param $webhookType
     * @param $webhooks
     * @return void
     */
    private static function updateWebhook($webhookType, $webhooks)
    {
        $webhookName = self::getWebhookOptionKeyByType($webhookType);
        Option::updateOption($webhookName, implode(PHP_EOL, array_unique($webhooks)));
    }

    /**
     * Get webhook option key by type
     *
     * @param $webhookType
     * @return string
     */
    private static function getWebhookOptionKeyByType($webhookType)
    {
        return sprintf('%s_webhook', $webhookType);
    }
}