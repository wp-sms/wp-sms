<?php
use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHelper;

if (apply_filters('wp_sms_enable_upgrade_to_bundle', true)) :
    $isPremium = (bool) LicenseHelper::isPremiumLicenseAvailable();
    ?>
    <?php if (!$isPremium && !LicenseHelper::isValidLicenseAvailable()) : ?>
    <div class="license-status license-status--free">
        <a class="upgrade" href="<?php echo esc_url(WP_SMS_SITE . '/pricing?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>" target="_blank">
            <span><?php esc_html_e('UPGRADE TO ALL-IN-ONE', 'wp-sms'); ?></span>
        </a>
    </div>
<?php else : ?>
    <div class="license-status license-status--valid">
        <a class="upgrade" href="<?php echo esc_url(WP_SMS_SITE . '/product-category/add-ons?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>">
                <span>
                    <?php echo sprintf(esc_html__('License: %1$s/%2$s', 'wp-sms'), count(PluginHelper::getLicensedPlugins()), count(PluginHelper::$plugins)); ?>
                    <span><?php esc_html_e('Upgrade', 'wp-sms'); ?></span>
                </span>
        </a>
    </div>
<?php endif; ?>
<?php endif; ?>
