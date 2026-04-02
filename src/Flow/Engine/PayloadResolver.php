<?php

namespace WSms\Flow\Engine;

use WSms\Auth\SettingsRepository;
use WSms\Messaging\Contracts\TemplateEngineInterface;
use WSms\Support\DateFormatter;

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
                'name'      => get_bloginfo('name'),
                'url'       => get_site_url(),
                'email'     => get_option('admin_email'),
                'phone'     => (get_option(SettingsRepository::OPTION_KEY, []))['site_phone'] ?? '',
                'login_url' => wp_login_url(),
                'admin_url' => admin_url(),
                'tagline'   => get_bloginfo('description'),
            ],
            'now' => [
                'date'       => wp_date(get_option('date_format')),
                'time'       => wp_date(get_option('time_format')),
                'datetime'   => wp_date(DateFormatter::siteFormat()),
                'year'       => wp_date('Y'),
                'day_name'   => wp_date('l'),
                'month_name' => wp_date('F'),
                'day'        => wp_date('d'),
                'month'      => wp_date('m'),
                'hour'       => wp_date('H'),
            ],
        ];
    }
}
