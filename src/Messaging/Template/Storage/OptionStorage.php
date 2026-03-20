<?php

namespace WSms\Messaging\Template\Storage;

use WSms\Messaging\Template\Contracts\TemplateStorageInterface;
use WSms\Messaging\Template\ValueObjects\ChannelContent;

defined('ABSPATH') || exit;

class OptionStorage implements TemplateStorageInterface
{
    private const OPTION_KEY = 'wsms_message_templates';

    private ?array $cache = null;

    public function getOverride(string $templateId, string $channel): ?ChannelContent
    {
        $all = $this->load();

        if (!isset($all[$templateId][$channel])) {
            return null;
        }

        return ChannelContent::fromArray($all[$templateId][$channel]);
    }

    public function saveOverride(string $templateId, string $channel, ?ChannelContent $content): void
    {
        $all = $this->load();

        if ($content === null) {
            unset($all[$templateId][$channel]);

            if (empty($all[$templateId])) {
                unset($all[$templateId]);
            }
        } else {
            $all[$templateId][$channel] = $content->toArray();
        }

        update_option(self::OPTION_KEY, $all);
        $this->cache = $all;
    }

    public function getAllOverrides(): array
    {
        $all = $this->load();
        $result = [];

        foreach ($all as $templateId => $channels) {
            foreach ($channels as $channel => $data) {
                $result[$templateId][$channel] = ChannelContent::fromArray($data);
            }
        }

        return $result;
    }

    private function load(): array
    {
        if ($this->cache === null) {
            $this->cache = get_option(self::OPTION_KEY, []);

            if (!is_array($this->cache)) {
                $this->cache = [];
            }
        }

        return $this->cache;
    }
}
