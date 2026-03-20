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
        if ($channel === '_enabled') {
            return null;
        }

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
                if ($channel === '_enabled') {
                    continue;
                }
                $result[$templateId][$channel] = ChannelContent::fromArray($data);
            }
        }

        return $result;
    }

    public function isEnabled(string $templateId): ?bool
    {
        $all = $this->load();

        if (!isset($all[$templateId]['_enabled'])) {
            return null;
        }

        return (bool) $all[$templateId]['_enabled'];
    }

    public function setEnabled(string $templateId, bool $enabled): void
    {
        $all = $this->load();
        $all[$templateId]['_enabled'] = $enabled;

        update_option(self::OPTION_KEY, $all);
        $this->cache = $all;
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
