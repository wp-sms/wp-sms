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
        $enriched = array_merge($this->getSystemVariables(), $payload);

        return $this->resolveValues($config, $enriched);
    }

    private function resolveValues(array $config, array $data): array
    {
        $resolved = [];

        foreach ($config as $key => $value) {
            if (is_string($value)) {
                $resolved[$key] = $this->templateEngine->render($value, $data);
            } elseif (is_array($value)) {
                $resolved[$key] = $this->resolveValues($value, $data);
            } else {
                $resolved[$key] = $value;
            }
        }

        return $resolved;
    }

    private function getSystemVariables(): array
    {
        return [
            'site' => [
                'name'  => get_bloginfo('name'),
                'url'   => get_site_url(),
                'email' => get_option('admin_email'),
            ],
            'now' => [
                'date'     => current_time('Y-m-d'),
                'time'     => current_time('H:i'),
                'datetime' => current_time('Y-m-d H:i:s'),
                'year'     => current_time('Y'),
            ],
        ];
    }
}
