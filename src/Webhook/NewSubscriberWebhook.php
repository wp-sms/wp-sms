<?php

namespace WP_SMS\Webhook;

if (!defined('ABSPATH')) exit;

/**
 * Sends the "new subscriber" webhook (used by the Zapier "New Subscriber" trigger).
 *
 * The webhook only goes out for active subscribers. When the subscribe form asks
 * for a verification code, the subscriber is first saved as inactive, so the
 * webhook waits until the code is confirmed (wp_sms_verify_subscriber).
 */
class NewSubscriberWebhook extends WebhookAbstract
{
    protected $webhookType = 'new_subscriber';
    protected $webhookAction = array(
        'actionName' => 'wp_sms_add_subscriber',
        'acceptArgs' => 3
    );

    public function init()
    {
        parent::init();

        add_action('wp_sms_verify_subscriber', [$this, 'run'], 10, 3);
    }

    public function run($name, $mobile, $status = '1')
    {
        if ((string)$status !== '1') {
            return;
        }

        $webhooks = $this->fetchWebhooks();

        foreach ($webhooks as $webhook) {
            $this->execute($webhook, [
                'name'   => $name,
                'mobile' => $mobile,
            ]);
        }
    }
}
