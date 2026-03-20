<?php

namespace WSms\Messaging\Template\Contracts;

use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\RenderedMessage;

defined('ABSPATH') || exit;

interface ChannelRendererInterface
{
    public function getChannel(): string;

    public function render(ChannelContent $content, array $context): RenderedMessage;
}
