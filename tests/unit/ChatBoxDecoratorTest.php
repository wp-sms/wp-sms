<?php

namespace unit;

use WP_SMS\Option;
use WP_SMS\Services\MessageButton\ChatBoxDecorator;
use WP_UnitTestCase;

class ChatBoxDecoratorTest extends WP_UnitTestCase
{
    public function tearDown(): void
    {
        Option::deleteOption('chatbox_footer_text');

        parent::tearDown();
    }

    public function testFooterTextIsEmptyWhenSettingIsEmpty()
    {
        Option::updateOption('chatbox_footer_text', '');

        $chatbox = new ChatBoxDecorator();

        $this->assertFalse($chatbox->getFooterText());
    }

    public function testFooterTextReturnsConfiguredValue()
    {
        Option::updateOption('chatbox_footer_text', 'We typically reply within minutes');

        $chatbox = new ChatBoxDecorator();

        $this->assertSame('We typically reply within minutes', $chatbox->getFooterText());
    }

    public function testFooterTextPreservesConfiguredZeroValue()
    {
        Option::updateOption('chatbox_footer_text', '0');

        $chatbox = new ChatBoxDecorator();

        $this->assertSame('0', $chatbox->getFooterText());
    }
}