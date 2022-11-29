<?php

namespace WP_SMS\Webhook;

class NewSubscriberWebhook extends WebhookAbstract
{
    protected $webhookType = 'new_subscriber';
    protected $webhookAction = array(
        'actionName' => 'wp_sms_add_subscriber',
        'acceptArgs' => 2
    );

    public function run($name, $mobile)
    {
        $webhooks = $this->fetchWebhooks();

        foreach ($webhooks as $webhook) {
            $this->execute($webhook, [
                'name'   => $name,
                'mobile' => $mobile,
            ]);
        }
    }
}