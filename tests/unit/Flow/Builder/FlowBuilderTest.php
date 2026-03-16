<?php

namespace WSms\Tests\Unit\Flow\Builder;

use PHPUnit\Framework\TestCase;
use WSms\Flow\Builder\FlowBuilder;

class FlowBuilderTest extends TestCase
{
    public function testBuildSimpleFlow(): void
    {
        $flow = FlowBuilder::create('test-id')
            ->name('Test Flow')
            ->trigger('wordpress.user_register')
            ->action('send_message', ['channel' => 'sms', 'to' => '{{user.phone}}', 'body' => 'Welcome!'])
            ->build();

        $this->assertSame('test-id', $flow->getId());
        $this->assertSame('Test Flow', $flow->getName());
        $this->assertSame('wordpress.user_register', $flow->getTriggerType());
        $this->assertCount(1, $flow->getSteps());
        $this->assertSame('action', $flow->getSteps()[0]['type']);
        $this->assertSame('send_message', $flow->getSteps()[0]['action']);
    }

    public function testBuildWithCondition(): void
    {
        $flow = FlowBuilder::create('cond-test')
            ->name('Condition Flow')
            ->trigger('woocommerce.order_completed')
            ->condition('order.total > 100')
                ->then()
                    ->sendSms('twilio', '{{customer.phone}}', 'VIP!')
                ->otherwise()
                    ->sendEmail('wp_mail', '{{customer.email}}', 'Thanks', 'Regular order')
            ->endCondition()
            ->build();

        $steps = $flow->getSteps();
        $this->assertCount(1, $steps);
        $this->assertSame('condition', $steps[0]['type']);
        $this->assertSame('order.total > 100', $steps[0]['expression']);
        $this->assertCount(1, $steps[0]['then']);
        $this->assertCount(1, $steps[0]['else']);
    }

    public function testBuildWithParallel(): void
    {
        $flow = FlowBuilder::create('par-test')
            ->name('Parallel Flow')
            ->trigger('wordpress.post_published')
            ->parallel()
                ->sendSms('wp_sms', '{{admin.phone}}', 'New post!')
                ->branch()
                ->sendEmail('wp_mail', '{{admin.email}}', 'Post', 'New post published')
            ->endParallel()
            ->build();

        $steps = $flow->getSteps();
        $this->assertCount(1, $steps);
        $this->assertSame('parallel', $steps[0]['type']);
        $this->assertCount(2, $steps[0]['branches']);
    }

    public function testBuildWithDelay(): void
    {
        $flow = FlowBuilder::create('delay-test')
            ->name('Delay Flow')
            ->trigger('wordpress.user_register')
            ->delay(300)
            ->action('send_message', ['channel' => 'email'])
            ->build();

        $steps = $flow->getSteps();
        $this->assertCount(2, $steps);
        $this->assertSame('delay', $steps[0]['type']);
        $this->assertSame(300, $steps[0]['duration']);
    }

    public function testSendSmsHelper(): void
    {
        $flow = FlowBuilder::create('sms-test')
            ->name('SMS Flow')
            ->trigger('test')
            ->sendSms('twilio', '+1234', 'Hello')
            ->build();

        $config = $flow->getSteps()[0]['config'];
        $this->assertSame('twilio', $config['gateway']);
        $this->assertSame('sms', $config['channel']);
        $this->assertSame('+1234', $config['to']);
        $this->assertSame('Hello', $config['body']);
    }

    public function testSendEmailHelper(): void
    {
        $flow = FlowBuilder::create('email-test')
            ->name('Email Flow')
            ->trigger('test')
            ->sendEmail('wp_mail', 'a@b.com', 'Subject', 'Body')
            ->build();

        $config = $flow->getSteps()[0]['config'];
        $this->assertSame('wp_mail', $config['gateway']);
        $this->assertSame('email', $config['channel']);
        $this->assertSame('Subject', $config['subject']);
    }

    public function testDefaultStatus(): void
    {
        $flow = FlowBuilder::create()->name('Test')->trigger('test')->build();
        $this->assertSame('draft', $flow->getStatus());
    }

    public function testAutoGeneratesId(): void
    {
        $flow = FlowBuilder::create()->name('Test')->trigger('test')->build();
        $this->assertNotEmpty($flow->getId());
        $this->assertSame(26, strlen($flow->getId())); // ULID length
    }
}
