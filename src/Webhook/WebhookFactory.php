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
        $webhookName     = sprintf('%s_webhook', $webhookType);
        $webhooks        = Option::getOption($webhookName);
        $webhookUrlArray = explode(PHP_EOL, $webhooks);

        /**
         * Append webhook url
         */
        $webhookUrlArray[] = sanitize_url($webhookUrl);

        Option::updateOption($webhookName, implode(PHP_EOL, array_unique($webhookUrlArray)));
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
        $webhookName     = sprintf('%s_webhook', $webhookType);
        $webhooks        = Option::getOption($webhookName);
        $webhookUrlArray = explode(PHP_EOL, $webhooks);

        /**
         * Remove webhook url
         */
        unset($webhookUrlArray[array_search($webhookUrl, $webhookUrlArray)]);

        Option::updateOption($webhookName, implode(PHP_EOL, $webhookUrlArray));
    }
}