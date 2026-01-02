<?php

if (!defined('ABSPATH')) exit;

if (empty($addOn) || !is_array($addOn)) {
    return;
}

$slug = $addOn['slug'] ?? '';
$name = $addOn['name'] ?? '';
$version = $addOn['version'] ?? '';
$description = $addOn['description'] ?? '';
$thumbnail = $addOn['thumbnail'] ?? '';
$label = $addOn['label'] ?? '';
$labelClass = $addOn['label_class'] ?? 'updated';
$productUrl = $addOn['product_url'] ?? '';
$documentationUrl = $addOn['documentation_url'] ?? '';
$statusLabel = $addOn['status_label'] ?? '';
$statusClass = $addOn['status_class'] ?? 'disable';
$isInstalled = $addOn['is_installed'] ?? false;
$isActivated = $addOn['is_activated'] ?? false;
$isLicensed = $addOn['is_licensed'] ?? false;
$updateAvailable = $addOn['update_available'] ?? false;
$licenseKey = $addOn['license_key'] ?? '';
$settingsUrl = $addOn['settings_url'] ?? '';
$icon = $addOn['icon'] ?? $thumbnail;

?>
<div class="wpsms-postbox-addon__item">
    <div>
        <div class="wpsms-postbox-addon__item--info">
            <div class="wpsms-postbox-addon__item--info__img">
                <img src="<?php echo esc_url($icon); ?>" alt="<?php echo esc_attr($name); ?>"/>
             </div>
            <div class="wpsms-postbox-addon__item--info__text">
                <div class="wpsms-postbox-addon__item--info__title">
                    <a href="<?php echo esc_url($productUrl); ?>?utm_source=wp-sms&utm_medium=link&utm_campaign=install-addon" target="_blank">
                        <?php echo esc_html($name) ?><span aria-hidden="true" class="wpsms-postbox-addon__version">v<?php echo esc_html($version) ?></span>
                    </a>
                    <?php if (!empty($label)) : ?>
                        <span class="wpsms-postbox-addon__label wpsms-postbox-addon__label--<?php echo esc_attr($labelClass); ?>"><?php echo esc_html($label); ?></span>
                    <?php endif; ?>

                    <?php if ($isLicensed && $updateAvailable) : ?>
                        <a href="<?php echo esc_url(admin_url('plugins.php')); ?>">
                            <span class="wpsms-postbox-addon__label wpsms-postbox-addon__label--updated"><?php esc_html_e('Update Available', 'wp-sms'); ?></span>
                        </a>
                    <?php endif; ?>
                </div>
                <p class="wpsms-postbox-addon__item--info__desc">
                    <?php echo wp_kses($description, 'data'); ?>
                </p>
            </div>
        </div>
        <div class="wpsms-postbox-addon__item--actions">
            <span class="wpsms-postbox-addon__status wpsms-postbox-addon__status--<?php echo esc_attr($statusClass); ?> "><?php echo esc_html($statusLabel); ?></span>
            <div class="wpsms-postbox-addon__buttons">
                <?php if ($isInstalled) : ?>
                    <button  class="wpsms-postbox-addon__button js-wpsms-addon-license-button"><?php esc_html_e('License', 'wp-sms'); ?></button >
                <?php endif; ?>
            </div>
            <div class="wpsms-addon--actions">
                <button tabindex="0"  class="wpsms-addon--actions--show-more js-addon-show-more"><span class="screen-reader-text"><?php echo esc_html__('Show more', 'wp-sms'); ?></span></button>
                <ul class="wpsms-addon--submenus">
                    <?php if ($isActivated && !empty($settingsUrl)) : ?>
                        <li><a href="<?php echo esc_url($settingsUrl); ?>" class="wpsms-addon--submenu wpsms-addon--submenu__settings" target="_blank"><span><?php esc_html_e('Settings', 'wp-sms'); ?></span></a></li>
                        <li><span class="wpsms-separator"></span></li>
                    <?php endif; ?>
                    <?php if (!empty($productUrl)) : ?>
                        <li><a href="<?php echo esc_url($productUrl); ?>?utm_source=wp-sms&utm_medium=link&utm_campaign=install-addon" class="wpsms-addon--submenu" target="_blank"><?php esc_html_e('Add-On Details', 'wp-sms'); ?></a></li>
                        <li><a href="<?php echo esc_url($productUrl); ?>?utm_source=wp-sms&utm_medium=link&utm_campaign=install-addon#changelog" class="wpsms-addon--submenu" target="_blank"><?php esc_html_e('Changelog', 'wp-sms'); ?></a></li>
                    <?php endif; ?>
                    <?php if (!empty($documentationUrl)) : ?>
                        <li><a href="<?php echo esc_url($documentationUrl); ?>?utm_source=wp-sms&utm_medium=link&utm_campaign=install-addon" class="wpsms-addon--submenu" target="_blank"><?php esc_html_e('Documentation', 'wp-sms'); ?></a></li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
    <div class="wpsms-addon__item__license js-wpsms-addon-license">
        <div class="wpsms-addon__item__update_license">
            <input aria-label="License Key" data-addon-slug="<?php echo esc_attr($slug) ?>" type="text" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" value="<?php echo esc_attr($licenseKey) ?>">
            <button><?php esc_html_e('Update License', 'wp-sms'); ?></button>
        </div>
    </div>
    <div class="wpsms-addon__download__item__info__alerts js-wpsms-addon-alert-wrapper"></div>
</div>
