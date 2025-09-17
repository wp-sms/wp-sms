<?php

namespace WP_SMS\Settings;

/**
 * Tag management for WP-SMS settings sections and fields
 */
class Tags
{
    public const NEW = 'new';
    public const DEPRECATED = 'deprecated';
    public const BETA = 'beta';
    public const PRO = 'pro';
    public const EXPERIMENTAL = 'experimental';
    public const COMING_SOON = 'coming-soon';

    /**
     * Get predefined tags with their styles
     *
     * @return array
     */
    public static function getPredefined(): array
    {
        return [
            self::NEW => [
                'label' => __('New', 'wp-sms'),
                'color' => 'green',
                'icon' => 'Sparkles'
            ],
            self::DEPRECATED => [
                'label' => __('Deprecated', 'wp-sms'),
                'color' => 'red',
                'icon' => 'AlertTriangle'
            ],
            self::BETA => [
                'label' => __('Beta', 'wp-sms'),
                'color' => 'yellow',
                'icon' => 'Beaker'
            ],
            self::PRO => [
                'label' => __('Pro', 'wp-sms'),
                'color' => 'purple',
                'icon' => 'Crown'
            ],
            self::EXPERIMENTAL => [
                'label' => __('Experimental', 'wp-sms'),
                'color' => 'orange',
                'icon' => 'TestTube'
            ],
            self::COMING_SOON => [
                'label' => __('Coming Soon', 'wp-sms'),
                'color' => 'blue',
                'icon' => 'Clock'
            ]
        ];
    }

    /**
     * Check if a tag is predefined
     *
     * @param string $tag
     * @return bool
     */
    public static function isValid(string $tag): bool
    {
        return array_key_exists($tag, self::getPredefined());
    }
    
    /**
     * Get tag configuration
     *
     * @param string $tag
     * @return array|null
     */
    public static function getConfig(string $tag): ?array
    {
        return self::getPredefined()[$tag] ?? null;
    }
}