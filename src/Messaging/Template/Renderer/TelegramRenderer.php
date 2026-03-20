<?php

namespace WSms\Messaging\Template\Renderer;

use WSms\Messaging\Template\Contracts\ChannelRendererInterface;
use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\RenderedMessage;

defined('ABSPATH') || exit;

class TelegramRenderer implements ChannelRendererInterface
{
    private const ALLOWED_TAGS = '<b><i><a><code><pre>';

    public function getChannel(): string
    {
        return 'telegram';
    }

    public function render(ChannelContent $content, array $context): RenderedMessage
    {
        $body = strip_tags($content->body, self::ALLOWED_TAGS);
        $body = trim($body);

        return new RenderedMessage(
            body: $body,
            meta: ['parse_mode' => 'HTML'],
        );
    }
}
