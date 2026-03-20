<?php

namespace WSms\Messaging\Template\Contracts;

defined('ABSPATH') || exit;

interface ToggleableTemplateInterface extends TemplateDefinitionInterface
{
    public function isEnabledByDefault(): bool;
}
