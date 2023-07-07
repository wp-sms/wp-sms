<?php

namespace WP_SMS\Webhook;

class WebhookManager
{
    public function init()
    {
        $this->registerWebhooks();
    }

    public function registerWebhooks()
    {
        NewSmsWebhook::boot();
        NewSubscriberWebhook::boot();
        NewIncomingSmsWebhook::boot();
    }
}