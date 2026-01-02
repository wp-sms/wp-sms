<?php

if (!defined('ABSPATH')) exit;

use Veronalabs\LicenseClient\LicenseHub;

// Get license status values using static calls
$isAuthenticated = LicenseHub::isAuthenticated();
$isPremium = LicenseHub::isPremium();
$hasValidLicense = LicenseHub::hasValidLicense();
$licensedCount = count(LicenseHub::getLocalLicenses('valid'));
$totalPlugins = LicenseHub::getTotalProducts();
$pricingUrl = LicenseHub::getPricingUrl() ?: WP_SMS_SITE . '/pricing?utm_source=wp-sms&utm_medium=link&utm_campaign=header';
?>
<div class="wrap wpsms-wrap<?php echo isset($class) ? ' ' . esc_attr($class) : ''; ?>">

<div class="wpsms-adminHeader<?php echo $isPremium ? ' wpsms-adminHeader__aio' : ''; ?>">
    <div class="wpsms-adminHeader__logo--container">
        <?php if ($isPremium) : ?>
            <img width="134" height="22" class="wpsms-adminHeader__logo wpsms-adminHeader__logo--aio" src="<?php echo esc_url(WP_SMS_URL . 'assets/images/wp-sms-premium.svg'); ?>"/>
        <?php else : ?>
            <img width="134" height="22" class="wpsms-adminHeader__logo" src="<?php echo esc_url(WP_SMS_URL . 'assets/images/white-header-logo.svg'); ?>"/>
        <?php endif; ?>
    </div>

    <div class="wpsms-adminHeader__side">
        <?php if (!$isAuthenticated) : ?>
            <!-- Guest: Show Login -->
            <a href="<?php echo esc_url(LicenseHub::getLoginUrl()); ?>" class="wpsms-license-status wpsms-license-status--login">
                <?php esc_html_e('Login', 'wp-sms'); ?>
            </a>
        <?php else : ?>
            <!-- Authenticated: Show License Status + Upgrade + Logout -->
            <?php if ($hasValidLicense) : ?>
                <a href="<?php echo esc_url($pricingUrl); ?>" target="_blank" class="wpsms-license-status wpsms-license-status--valid">
                    <span><?php printf(esc_html__('License: %1$s/%2$s', 'wp-sms'), $licensedCount, $totalPlugins); ?></span>
                    <?php if ($licensedCount < $totalPlugins) : ?>
                        <span><?php esc_html_e('Upgrade', 'wp-sms'); ?></span>
                    <?php endif; ?>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url($pricingUrl); ?>" target="_blank" class="wpsms-license-status wpsms-license-status--free">
                    <?php esc_html_e('Upgrade To Premium', 'wp-sms'); ?>
                </a>
            <?php endif; ?>
            <a href="<?php echo esc_url(LicenseHub::getLogoutUrl()); ?>" class="wpsms-logout" title="<?php esc_attr_e('Logout', 'wp-sms'); ?>">
                <span class="dashicons dashicons-exit"></span>
            </a>
        <?php endif; ?>

        <a href="<?php echo esc_url(admin_url('admin.php?page=wps_optimization_page')); ?>" title="<?php esc_html_e('Optimization', 'wp-sms'); ?>" class="optimization<?php echo (isset($_GET['page']) && $_GET['page'] === 'wps_optimization_page') ? ' active' : ''; ?>"></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wps_settings_page')); ?>" title="<?php esc_html_e('Settings', 'wp-sms'); ?>" class="settings<?php echo (isset($_GET['page']) && $_GET['page'] === 'wps_settings_page') ? ' active' : ''; ?>"></a>
        <a href="<?php echo esc_url(WP_SMS_SITE . '/support?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>" target="_blank" title="<?php esc_html_e('Help Center', 'wp-sms'); ?>" class="support"></a>

        <div class="wpsms-adminHeader__mobileMenu">
            <input type="checkbox" id="wpsms-menu-toggle" class="hamburger-menu">
            <label for="wpsms-menu-toggle" class="hamburger-menu-container">
                <div class="hamburger-menu-bar">
                    <div class="menu-bar"></div>
                    <div class="menu-bar"></div>
                    <div class="menu-bar"></div>
                </div>
                <span><?php esc_html_e('Menu', 'wp-sms'); ?></span>
            </label>
            <div class="wpsms-mobileMenuContent">
                <?php
                if (!$isPremium) {
                    echo \WP_SMS\Utils\AdminHelper::getTemplate('layout/partials/menu-link', ['slug' => 'wps_plugins_page', 'link_text' => __('Add-Ons', 'wp-sms'), 'icon_class' => 'addons', 'badge_count' => null], true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                }
                echo \WP_SMS\Utils\AdminHelper::getTemplate('layout/partials/menu-link', ['slug' => 'wps_settings_page', 'link_text' => __('Settings', 'wp-sms'), 'icon_class' => 'settings', 'badge_count' => null], true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                echo \WP_SMS\Utils\AdminHelper::getTemplate('layout/partials/menu-link', ['slug' => 'wps_optimization_page', 'link_text' => __('Optimization', 'wp-sms'), 'icon_class' => 'optimization', 'badge_count' => null], true); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
                ?>
                <div>
                    <a href="<?php echo esc_url(WP_SMS_SITE . '/support?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>" target="_blank" title="<?php esc_html_e('Help Center', 'wp-sms'); ?>" class="help">
                        <span class="icon"></span>
                        <?php esc_html_e('Help Center', 'wp-sms'); ?>
                    </a>
                </div>

                <div class="wpsms-bundle">
                    <?php if (!$isAuthenticated) : ?>
                        <a href="<?php echo esc_url(LicenseHub::getLoginUrl()); ?>" class="wpsms-license-status wpsms-license-status--login">
                            <?php esc_html_e('Login', 'wp-sms'); ?>
                        </a>
                    <?php else : ?>
                        <?php if ($hasValidLicense) : ?>
                            <a href="<?php echo esc_url($pricingUrl); ?>" target="_blank" class="wpsms-license-status wpsms-license-status--valid">
                                <span><?php printf(esc_html__('License: %1$s/%2$s', 'wp-sms'), $licensedCount, $totalPlugins); ?></span>
                                <?php if ($licensedCount < $totalPlugins) : ?>
                                    <span><?php esc_html_e('Upgrade', 'wp-sms'); ?></span>
                                <?php endif; ?>
                            </a>
                        <?php else : ?>
                            <a href="<?php echo esc_url($pricingUrl); ?>" target="_blank" class="wpsms-license-status wpsms-license-status--free">
                                <?php esc_html_e('Upgrade To Premium', 'wp-sms'); ?>
                            </a>
                        <?php endif; ?>
                        <a href="<?php echo esc_url(LicenseHub::getLogoutUrl()); ?>" class="wpsms-logout">
                            <?php esc_html_e('Logout', 'wp-sms'); ?>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
