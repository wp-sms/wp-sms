<?php

namespace WP_SMS\Webhook;

class NewIncomingSmsWebhook extends WebhookAbstract
{
    protected $webhookType = 'new_incoming_sms';
    protected $webhookAction = array(
        'actionName' => 'wp_sms_two_new_incoming_message',
        'acceptArgs' => 1
    );

    public function run($incomingMessage)
    {
        $webhooks = $this->fetchWebhooks();

        foreach ($webhooks as $webhook) {
            $this->execute($webhook, [
                'sender_number' => $incomingMessage->sender_number,
                'gateway'       => $incomingMessage->gateway,
                'text'          => $incomingMessage->text,
                'command_name'  => $incomingMessage->command_name,
            ]);
        }
    }
}