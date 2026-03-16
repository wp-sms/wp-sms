<?php

namespace WSms\Messaging\Contracts;

defined('ABSPATH') || exit;

interface TemplateEngineInterface
{
    public function render(string $template, array $data): string;
}
