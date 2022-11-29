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
        NewSMSWebhook::boot();
        NewSubscriberWebhook::boot();
    }
}