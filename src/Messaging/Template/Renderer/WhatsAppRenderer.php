<?php

namespace WSms\Messaging\Template\Renderer;

use WSms\Messaging\Template\Contracts\ChannelRendererInterface;
use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\RenderedMessage;

defined('ABSPATH') || exit;

class WhatsAppRenderer implements ChannelRendererInterface
{
    use PlaintextNormalization;

    public function getChannel(): string
    {
        return 'whatsapp';
    }

    public function render(ChannelContent $content, array $context): RenderedMessage
    {
        $meta = [];
        if (!empty($context['otp_code'])) {
            $meta['otp_code'] = $context['otp_code'];
        }

        return new RenderedMessage(body: $this->toPlaintext($content->body), meta: $meta);
    }
}
