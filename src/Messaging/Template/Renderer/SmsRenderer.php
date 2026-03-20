<?php

namespace WSms\Messaging\Template\Renderer;

use WSms\Messaging\Template\Contracts\ChannelRendererInterface;
use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\RenderedMessage;

defined('ABSPATH') || exit;

class SmsRenderer implements ChannelRendererInterface
{
    use PlaintextNormalization;

    public function getChannel(): string
    {
        return 'sms';
    }

    public function render(ChannelContent $content, array $context): RenderedMessage
    {
        return new RenderedMessage(body: $this->toPlaintext($content->body));
    }
}
