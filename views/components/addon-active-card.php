<?php

/** @var PluginDecorator $addOn */

use WP_SMS\Admin\LicenseManagement\Plugin\PluginDecorator;

if (!defined('ABSPATH') || empty($addOn)) {
    exit;
}

?>
<div class="wpsms-addon__download__item">
    <div class="wpsms-addon__download__item--info">
        <div class="wpsms-addon__download__item--info__img">
            <img src="<?php echo esc_url($addOn->getIcon()); ?>" alt="<?php echo esc_attr($addOn->getName()); ?>">
        </div>
        <div class="wpsms-addon__download__item--info__text">
            <div class="wpsms-addon__download__item--info__title">
                <?php echo esc_html($addOn->getName()); ?>
            </div>
            <p class="wpsms-addon__download__item--info__desc">
                <?php echo wp_kses($addOn->getShortDescription(), 'data'); ?>
            </p>
        </div>
    </div>
    <div class="wpsms-addon__download__item--actions">
        <?php if (in_array($addOn->getSlug(), $selectedAddOns) && (!$addOn->isInstalled() || $addOn->isUpdateAvailable())) : ?>
            <span class="wpsms-postbox-addon__status wpsms-postbox-addon__status--danger "><?php esc_html_e('Failed', 'wp-sms'); ?></span>
        <?php elseif ($addOn->isActivated()) : ?>
            <span class="wpsms-postbox-addon__status wpsms-postbox-addon__status--success "><?php esc_html_e('Activated', 'wp-sms'); ?></span>
        <?php endif; ?>

        <div class="wpsms-postbox-addon__buttons">
            <?php if (in_array($addOn->getSlug(), $selectedAddOns) && (!$addOn->isInstalled() || $addOn->isUpdateAvailable())) : ?>
                <a class="wpsms-postbox-addon__button button-retry-addon-download js-addon-retry-btn" data-slug="<?php echo esc_attr($addOn->getSlug()); ?>" title="<?php esc_html_e('Retry', 'wp-sms'); ?>"><?php esc_html_e('Retry', 'wp-sms'); ?></a>
            <?php elseif ($addOn->isInstalled() && !$addOn->isActivated() ) : ?>
                <a class="wpsms-postbox-addon__button button-activate-addon js-addon-active-plugin-btn" data-slug="<?php echo esc_attr($addOn->getSlug()); ?>" title="<?php esc_html_e('Active', 'wp-sms'); ?>"><?php esc_html_e('Active', 'wp-sms'); ?></a>
            <?php endif; ?>
        </div>


        <div class="wpsms-addon--actions <?php echo !$addOn->isInstalled() ? 'wpsms-hide' : ''; ?>">
            <span class="wpsms-addon--actions--show-more js-addon-show-more"></span>
            <ul class="wpsms-addon--submenus">
                <?php if ($addOn->isActivated()) : ?>
                    <li><a target="_blank" href="<?php echo esc_url($addOn->getSettingsUrl()); ?>" class="wpsms-addon--submenu wpsms-addon--submenu__settings"><?php esc_html_e('Settings', 'wp-sms'); ?></a></li>
                <?php endif; ?>
                <?php if (!empty($addOn->getProductUrl())) : ?>
                    <li><a href="<?php echo esc_url($addOn->getProductUrl()); ?>?utm_source=wp-sms&utm_medium=link&utm_campaign=dp" class="wpsms-addon--submenu" target="_blank"><?php esc_html_e('Add-On Details', 'wp-sms'); ?></a></li>
                <?php endif; ?>
                <?php if (!empty($addOn->getChangelogUrl())) : ?>
                    <li><a href="<?php echo esc_url($addOn->getChangelogUrl()); ?>&utm_source=wp-sms&utm_medium=link&utm_campaign=dp" class="wpsms-addon--submenu" target="_blank"><?php esc_html_e('Changelog', 'wp-sms'); ?></a></li>
                <?php endif; ?>
                <?php if (!empty($addOn->getDocumentationUrl())) : ?>
                    <li><a href="<?php echo esc_url($addOn->getDocumentationUrl()); ?>?utm_source=wp-sms&utm_medium=link&utm_campaign=dp" class="wpsms-addon--submenu" target="_blank"><?php esc_html_e('Documentation', 'wp-sms'); ?></a></li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</div>