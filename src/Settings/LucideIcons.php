<?php

namespace WP_SMS\Settings;

/**
 * Centralized icon management for WP-SMS settings
 * All icons are Lucide React icon names
 */
class LucideIcons
{
    // Core Settings Icons
    public const SETTINGS = 'Settings';
    public const SETTINGS_2 = 'Settings2';
    public const COG = 'Cog';
    public const MESSAGE_SQUARE = 'MessageSquare';
    public const SEND = 'Send';
    public const ZAP = 'Zap';
    public const STAR = 'Star';
    public const BELL = 'Bell';
    public const MAIL = 'Mail';
    public const USERS = 'Users';
    public const MOUSE_POINTER = 'MousePointer';
    public const MESSAGE_CIRCLE = 'MessageCircle';
    public const NEWSPAPER = 'Newspaper';
    
    // Integration Icons
    public const SHIELD = 'Shield';
    public const LOCK = 'Lock';
    public const SHOPPING_CART = 'ShoppingCart';
    public const USER_CHECK = 'UserCheck';
    public const GRADUATION_CAP = 'GraduationCap';
    public const CALENDAR = 'Calendar';
    public const BAR_CHART_3 = 'BarChart3';
    
    // Utility Icons
    public const HELP_CIRCLE = 'HelpCircle';
    public const EXTERNAL_LINK = 'ExternalLink';
    public const ALERT_TRIANGLE = 'AlertTriangle';
    public const INFO = 'Info';
    public const SPARKLES = 'Sparkles';
    public const BEAKER = 'Beaker';
    public const CROWN = 'Crown';
    public const TEST_TUBE = 'TestTube';
    public const CLOCK = 'Clock';
    public const BADGE_CHECK = 'BadgeCheck';
    
    /**
     * Get all available icons
     *
     * @return array
     */
    public static function getAll(): array
    {
        return (new \ReflectionClass(self::class))->getConstants();
    }
    
    /**
     * Validate if icon exists
     *
     * @param string $icon
     * @return bool
     */
    public static function isValid(string $icon): bool
    {
        return in_array($icon, self::getAll());
    }
    
    /**
     * Get default icon for settings groups
     *
     * @return string
     */
    public static function getDefault(): string
    {
        return self::SETTINGS;
    }
} 