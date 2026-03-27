<?php

namespace WSms\Integration\Line\FlexTemplates;

defined('ABSPATH') || exit;

class FlexHelper
{
    /**
     * Build a footer box with a single CTA button.
     */
    public static function footer(string $url, string $label, string $color = '#1DB446'): array
    {
        return [
            'type' => 'box',
            'layout' => 'vertical',
            'spacing' => 'sm',
            'contents' => [
                [
                    'type' => 'button',
                    'style' => 'primary',
                    'color' => $color,
                    'action' => [
                        'type' => 'uri',
                        'label' => $label,
                        'uri' => $url,
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a hero image section for a bubble.
     */
    public static function heroImage(string $url, string $aspectRatio = '20:13'): array
    {
        return [
            'type'        => 'image',
            'url'         => $url,
            'size'        => 'full',
            'aspectRatio' => $aspectRatio,
            'aspectMode'  => 'cover',
        ];
    }

    /**
     * Build a baseline row with a label and value (used in info sections).
     */
    public static function baselineRow(string $label, string $value, string $valueColor = '#666666', bool $bold = false): array
    {
        $valueNode = [
            'type' => 'text',
            'text' => $value,
            'wrap' => true,
            'color' => $valueColor,
            'size' => 'sm',
            'flex' => 5,
        ];

        if ($bold) {
            $valueNode['weight'] = 'bold';
        }

        return [
            'type' => 'box',
            'layout' => 'baseline',
            'spacing' => 'sm',
            'contents' => [
                [
                    'type' => 'text',
                    'text' => $label,
                    'color' => '#aaaaaa',
                    'size' => 'sm',
                    'flex' => 2,
                ],
                $valueNode,
            ],
        ];
    }
}
