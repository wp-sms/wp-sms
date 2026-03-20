<?php

namespace WSms\Messaging\Template\Contracts;

use WSms\Messaging\Template\ValueObjects\ChannelContent;
use WSms\Messaging\Template\ValueObjects\VariableDefinition;

defined('ABSPATH') || exit;

interface TemplateDefinitionInterface
{
    public function getId(): string;

    public function getLabel(): string;

    public function getDescription(): string;

    /**
     * @return array<string, VariableDefinition>
     */
    public function getVariables(): array;

    /**
     * @return array<string>
     */
    public function getSupportedChannels(): array;

    /**
     * @return array<string, ChannelContent>
     */
    public function getDefaults(): array;
}
