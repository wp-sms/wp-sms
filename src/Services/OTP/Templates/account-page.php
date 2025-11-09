<?php
/**
 * Account Page Template
 * 
 * Full account management with Profile and Security/MFA tabs
 * 
 * @var array $atts Shortcode attributes
 */

if (!defined('ABSPATH')) {
    exit;
}

$defaultTab = isset($atts['default_tab']) ? $atts['default_tab'] : 'profile';
$showTabs = isset($atts['show_tabs']) && $atts['show_tabs'] === 'true';
?>

<div class="wpsms-account-container" data-default-tab="<?php echo esc_attr($defaultTab); ?>">
    
    <?php if ($showTabs): ?>
    <!-- Tab Navigation -->
    <div class="wpsms-account-tabs" role="tablist">
        <button 
            class="wpsms-tab-button" 
            data-tab="profile" 
            role="tab" 
            aria-selected="true"
            aria-controls="wpsms-tab-profile">
            <span class="wpsms-tab-icon">👤</span>
            <span class="wpsms-tab-label"><?php _e('Profile', 'wp-sms'); ?></span>
        </button>
        <button 
            class="wpsms-tab-button" 
            data-tab="security" 
            role="tab" 
            aria-selected="false"
            aria-controls="wpsms-tab-security">
            <span class="wpsms-tab-icon">🔒</span>
            <span class="wpsms-tab-label"><?php _e('Security', 'wp-sms'); ?></span>
        </button>
    </div>
    <?php endif; ?>

    <!-- Tab Content -->
    <div class="wpsms-account-content">
        
        <!-- Profile Tab -->
        <div 
            id="wpsms-tab-profile" 
            class="wpsms-tab-panel" 
            data-tab="profile"
            role="tabpanel"
            aria-labelledby="tab-profile">
            <?php include __DIR__ . '/account-profile.php'; ?>
        </div>

        <!-- Security/MFA Tab -->
        <div 
            id="wpsms-tab-security" 
            class="wpsms-tab-panel" 
            data-tab="security"
            role="tabpanel"
            aria-labelledby="tab-security">
            <?php include __DIR__ . '/account-mfa.php'; ?>
        </div>

    </div>

    <!-- Toast Notifications -->
    <div class="wpsms-toast-container" aria-live="polite" aria-atomic="true"></div>

</div>

