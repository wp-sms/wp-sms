<?php

namespace WSms\Messaging\Template\Renderer;

defined('ABSPATH') || exit;

trait PlaintextNormalization
{
    protected function toPlaintext(string $html): string
    {
        $text = strip_tags($html);
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
