<?php

namespace unit;

use WP_SMS\Newsletter;
use WP_SMS\Webhook\WebhookFactory;
use WP_UnitTestCase;

class NewSubscriberWebhookTest extends WP_UnitTestCase
{
    protected $webhookUrl = 'https://hooks.example.com/new-subscriber';
    protected $requests = [];

    protected function setUp(): void
    {
        parent::setUp();

        WebhookFactory::subscribeWebhook($this->webhookUrl, 'new_subscriber');

        $this->requests = [];
        add_filter('pre_http_request', [$this, 'captureRequest'], 10, 3);
    }

    protected function tearDown(): void
    {
        remove_filter('pre_http_request', [$this, 'captureRequest'], 10);
        WebhookFactory::unsubscribeWebhook($this->webhookUrl, 'new_subscriber');

        parent::tearDown();
    }

    public function captureRequest($preempt, $args, $url)
    {
        if (trim($url) === $this->webhookUrl) {
            $this->requests[] = json_decode($args['body'], true);

            return ['headers' => [], 'body' => '', 'response' => ['code' => 200, 'message' => 'OK'], 'cookies' => [], 'filename' => null];
        }

        return $preempt;
    }

    public function test_inactive_subscriber_does_not_send_webhook()
    {
        Newsletter::addSubscriber('Pending Person', '+15555550101', 0, '0');

        $this->assertCount(0, $this->requests);
    }

    public function test_verified_subscriber_sends_webhook_once()
    {
        Newsletter::addSubscriber('Pending Person', '+15555550102', 0, '0');
        $this->assertCount(0, $this->requests);

        do_action('wp_sms_verify_subscriber', 'Pending Person', '+15555550102', 1, 1);

        $this->assertCount(1, $this->requests);
        $this->assertSame(['name' => 'Pending Person', 'mobile' => '+15555550102'], $this->requests[0]);
    }

    public function test_active_subscriber_sends_webhook()
    {
        Newsletter::addSubscriber('Active Person', '+15555550103', 0, '1');

        $this->assertCount(1, $this->requests);
        $this->assertSame('Active Person', $this->requests[0]['name']);
    }
}
