<?php

namespace WP_SMS\Webhook;

if (!defined('ABSPATH')) exit;

/**
 * Sends the "new subscriber" webhook (used by the Zapier "New Subscriber" trigger).
 *
 * The webhook only goes out for active subscribers. When the subscribe form asks
 * for a verification code, the subscriber is first saved as inactive, so the
 * webhook waits until the code is confirmed (wp_sms_verify_subscriber).
 * On confirmation the name and number come from the saved subscriber row,
 * not from the verification request.
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

        add_action('wp_sms_verify_subscriber', [$this, 'runVerified'], 10, 4);
    }

    /**
     * Sends the webhook for a subscriber who just confirmed the code, using the saved row.
     *
     * @param string     $name         Name sent with the verification request (not trusted).
     * @param string     $mobile       Mobile number sent with the verification request.
     * @param string     $status       New status.
     * @param int|null   $subscriberId ID of the confirmed subscriber row.
     */
    public function runVerified($name, $mobile, $status = '1', $subscriberId = null)
    {
        if ($subscriberId) {
            $subscriber = \WP_SMS\Newsletter::getSubscriber($subscriberId);

            if ($subscriber) {
                $name   = $subscriber->name;
                $mobile = $subscriber->mobile;
            }
        }

        $this->run($name, $mobile, $status);
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
