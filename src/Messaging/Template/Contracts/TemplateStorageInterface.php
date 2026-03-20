<?php

namespace WSms\Messaging\Template\Contracts;

use WSms\Messaging\Template\ValueObjects\ChannelContent;

defined('ABSPATH') || exit;

interface TemplateStorageInterface
{
    public function getOverride(string $templateId, string $channel): ?ChannelContent;

    public function saveOverride(string $templateId, string $channel, ?ChannelContent $content): void;

    /**
     * @return array<string, array<string, ChannelContent>>
     */
    public function getAllOverrides(): array;
}
