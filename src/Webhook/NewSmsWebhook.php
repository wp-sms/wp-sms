<?php

namespace WP_SMS\Webhook;

use Exception;

class NewSmsWebhook extends WebhookAbstract
{
    protected $webhookType = 'new_sms';
    protected $webhookAction = array(
        'actionName' => 'wp_sms_send',
        'acceptArgs' => 1
    );

    /**
     * @throws Exception
     */
    public function run($result)
    {
        $webhooks = $this->fetchWebhooks();

        foreach ($webhooks as $webhook) {
            $this->execute($webhook, [
                'api_response' => $result
            ]);
        }
    }
}