<?php

namespace WP_SMS\Settings;

/**
 * Centralized icon management for WP-SMS settings
 * All icons are Lucide React icon names
 */
class LucideIcons
{
    // Core Settings Icons
    public const string SETTINGS = 'Settings';
    public const string SETTINGS_2 = 'Settings2';
    public const string COG = 'Cog';
    public const string MESSAGE_SQUARE = 'MessageSquare';
    public const string SEND = 'Send';
    public const string ZAP = 'Zap';
    public const string STAR = 'Star';
    public const string BELL = 'Bell';
    public const string MAIL = 'Mail';
    public const string USERS = 'Users';
    public const string MOUSE_POINTER = 'MousePointer';
    public const string MESSAGE_CIRCLE = 'MessageCircle';
    public const string NEWSPAPER = 'Newspaper';
    
    // Integration Icons
    public const string SHIELD = 'Shield';
    public const string LOCK = 'Lock';
    public const string SHOPPING_CART = 'ShoppingCart';
    public const string USER_CHECK = 'UserCheck';
    public const string GRADUATION_CAP = 'GraduationCap';
    public const string CALENDAR = 'Calendar';
    public const string BAR_CHART_3 = 'BarChart3';
    
    // Utility Icons
    public const string HELP_CIRCLE = 'HelpCircle';
    public const string EXTERNAL_LINK = 'ExternalLink';
    public const string ALERT_TRIANGLE = 'AlertTriangle';
    public const string INFO = 'Info';
    public const string SPARKLES = 'Sparkles';
    public const string BEAKER = 'Beaker';
    public const string CROWN = 'Crown';
    public const string TEST_TUBE = 'TestTube';
    public const string CLOCK = 'Clock';
    public const string BADGE_CHECK = 'BadgeCheck';
    public const string FILE_TEXT = 'FileText';
    
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