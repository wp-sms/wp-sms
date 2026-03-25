<?php

namespace WSms\Messaging\Template;

use WSms\Messaging\Contracts\TemplateEngineInterface;

defined('ABSPATH') || exit;

class MustacheEngine implements TemplateEngineInterface
{
    public function render(string $template, array $data): string
    {
        return preg_replace_callback('/\{\{(.+?)\}\}/', function ($match) use ($data) {
            $key = trim($match[1]);
            return $this->resolve($key, $data) ?? $match[0];
        }, $template);
    }

    private function resolve(string $key, array $data): ?string
    {
        $parts = explode('.', $key);
        $value = $data;

        foreach ($parts as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }

        return is_scalar($value) ? (string) $value : (wp_json_encode($value) ?: null);
    }
}
