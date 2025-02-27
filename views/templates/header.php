<?php

use WP_SMS\Utils\AdminHelper;
use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHelper;

$isPremium = LicenseHelper::isPremiumLicenseAvailable() ? true : false;
?>

<div class="wpsms-adminHeader <?php echo $isPremium ? 'wpsms-adminHeader__premium' : '' ?>">
    <div class="wpsms-adminHeader__logo--container">
        <?php if ($isPremium): ?>
            <img width="134" height="22" class="wpsms-adminHeader__logo wpsms-adminHeader__logo--premium" src="<?php echo esc_url(apply_filters('wp_sms_header_url', WP_SMS_URL . 'assets/images/wp-sms-premium.svg')); ?>"/>
        <?php else: ?>
            <img width="134" height="22" class="wpsms-adminHeader__logo" src="<?php echo esc_url(apply_filters('wp_sms_header_url', WP_SMS_URL . 'assets/images/white-header-logo.svg')); ?>"/>

        <?php endif; ?>
    </div>
    <div class="wpsms-adminHeader__side">
        <?php if (apply_filters('wp_sms_enable_upgrade_to_bundle', true)) : ?>
            <?php if (!$isPremium && !LicenseHelper::isValidLicenseAvailable()) : ?>
                <a href="<?php echo esc_url(WP_SMS_SITE . '/pricing?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>" target="_blank" class="wpsms-license-status wpsms-license-status--free">
                    <?php esc_html_e('Upgrade To Premium', 'wp-sms'); ?>
                </a>
            <?php else : ?>
                <a href="<?php echo esc_url(WP_SMS_SITE . '/pricing?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>" class="wpsms-license-status wpsms-license-status--valid">
                    <span><?php esc_html_e(sprintf('License: %s/%s', count(PluginHelper::getLicensedPlugins()), count(PluginHelper::$plugins)), 'wp-sms') ?></span> <span><?php esc_html_e('Upgrade', 'wp-sms'); ?></span>
                </a>
            <?php endif; ?>
        <?php endif; ?>

        <a href="<?php echo esc_url(admin_url('admin.php?page=wps_optimization_page')); ?>" title="<?php esc_html_e('Optimization', 'wp-sms'); ?>" class="optimization <?php if (isset($_GET['page']) && $_GET['page'] === 'wps_optimization_page') {
            echo 'active';
        } ?>"></a>
        <a href="<?php echo esc_url(admin_url('admin.php?page=wps_settings_page')); ?>" title="<?php esc_html_e('Settings', 'wp-sms'); ?>" class="settings <?php if (isset($_GET['page']) && $_GET['page'] === 'wps_settings_page') {
            echo 'active';
        } ?>"></a>
        <?php if (apply_filters('wp_sms_enable_help_icon', true)) { ?>
            <a href="<?php echo esc_url(WP_SMS_SITE . '/support?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>" target="_blank" title="<?php esc_html_e('Help Center', 'wp-sms'); ?>" class="support"></a>
        <?php } ?>
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
                if (!$isPremium && apply_filters('wp_sms_enable_header_addons_menu', true)) {
                    echo AdminHelper::getTemplate('layout/partials/menu-link', ['slug' => 'wps_plugins_page', 'link_text' => __('Add-Ons', 'wp-sms'), 'icon_class' => 'addons', 'badge_count' => null], true);
                }
                if ($isPremium) {
                    echo AdminHelper::getTemplate('layout/partials/menu-link', ['slug' => 'wps_pages_page', 'link_text' => __('Top Pages', 'wp-sms'), 'icon_class' => 'top-pages', 'badge_count' => null], true);
                    echo AdminHelper::getTemplate('layout/partials/menu-link', ['slug' => 'wps_content-analytics_page', 'link_text' => __('Content Analytics', 'wp-sms'), 'icon_class' => 'content-analytics', 'badge_count' => null], true);
                    echo AdminHelper::getTemplate('layout/partials/menu-link', ['slug' => 'wps_author-analytics_page', 'link_text' => __('Author Analytics', 'wp-sms'), 'icon_class' => 'author-analytics', 'badge_count' => null], true);
                }
                echo AdminHelper::getTemplate('layout/partials/menu-link', ['slug' => 'wps_settings_page', 'link_text' => __('Settings', 'wp-sms'), 'icon_class' => 'settings', 'badge_count' => null], true);
                echo AdminHelper::getTemplate('layout/partials/menu-link', ['slug' => 'wps_optimization_page', 'link_text' => __('Optimization', 'wp-sms'), 'icon_class' => 'optimization', 'badge_count' => null], true);
                ?>
                <?php if (apply_filters('wp_sms_enable_help_icon', true)) { ?>
                    <div>
                        <a href="<?php echo esc_url(WP_SMS_SITE . '/support?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>" target="_blank" title="<?php esc_html_e('Help Center', 'wp-sms'); ?>" class="help">
                            <span class="icon"></span>
                            <?php esc_html_e('Help Center', 'wp-sms'); ?>
                        </a>
                    </div>
                <?php } ?>

                <?php if (apply_filters('wp_sms_enable_upgrade_to_bundle', true)) : ?>
                    <div class="wpsms-bundle">
                        <?php if (!$isPremium && !LicenseHelper::isValidLicenseAvailable()) : ?>
                            <a href="<?php echo esc_url(WP_SMS_SITE . '/pricing?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>" target="_blank" class="wpsms-license-status wpsms-license-status--free">
                                <?php esc_html_e('Upgrade To Premium', 'wp-sms'); ?>
                            </a>
                        <?php else : ?>
                            <a href="<?php echo esc_url(WP_SMS_SITE . '/pricing?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>" class="wpsms-license-status wpsms-license-status--valid">
                                <span><?php esc_html_e(sprintf('License: %s/%s', count(PluginHelper::getLicensedPlugins()), count(PluginHelper::$plugins)), 'wp-sms'); ?></span> <span><?php esc_html_e('Upgrade', 'wp-sms'); ?></span>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>