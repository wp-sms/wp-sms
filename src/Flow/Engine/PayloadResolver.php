<?php

namespace WSms\Flow\Engine;

use WSms\Messaging\Contracts\TemplateEngineInterface;

defined('ABSPATH') || exit;

class PayloadResolver
{
    public function __construct(
        private readonly TemplateEngineInterface $templateEngine,
    ) {
    }

    public function resolveConfig(array $config, array $payload): array
    {
        $resolved = [];

        foreach ($config as $key => $value) {
            if (is_string($value)) {
                $resolved[$key] = $this->templateEngine->render($value, $payload);
            } elseif (is_array($value)) {
                $resolved[$key] = $this->resolveConfig($value, $payload);
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }
}
