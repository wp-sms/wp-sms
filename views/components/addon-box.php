<?php

/** @var PluginDecorator $addOn */

use WP_SMS\Admin\LicenseManagement\Plugin\PluginDecorator;

if (!defined('ABSPATH') || empty($addOn)) {
    exit;
}

?>
<div class="wpsms-postbox-addon__item">
    <div>
        <div class="wpsms-postbox-addon__item--info">
            <div class="wpsms-postbox-addon__item--info__img">
                <a href="<?php echo esc_url($addOn->getProductUrl()); ?>?utm_source=wp-sms&utm_medium=link&utm_campaign=dp" target="_blank">
                    <img src="<?php echo esc_url($addOn->getIcon()); ?>" alt="<?php echo esc_html($addOn->getName()); ?>" />
                </a>
            </div>
            <div class="wpsms-postbox-addon__item--info__text">
                <div class="wpsms-postbox-addon__item--info__title">
                    <a href="<?php echo esc_url($addOn->getProductUrl()); ?>?utm_source=wp-sms&utm_medium=link&utm_campaign=dp" target="_blank">
                        <?php echo esc_html($addOn->getName())?><span class="wpsms-postbox-addon__version">v<?php echo esc_html($addOn->getVersion())?></span>
                    </a>
                    <?php if (!empty($addOn->getLabel())) : ?>
                        <span class="wpsms-postbox-addon__label wpsms-postbox-addon__label--<?php echo esc_attr($addOn->getLabelClass()); ?>"><?php echo esc_html($addOn->getLabel()); ?></span>
                    <?php endif; ?>

                    <?php if ($addOn->isLicenseValid() && $addOn->isUpdateAvailable()) : ?>
                        <span class="wpsms-postbox-addon__label wpsms-postbox-addon__label--updated"><?php esc_html_e('Update Available', 'wp-sms'); ?></span>
                    <?php endif; ?>
                </div>
                <p class="wpsms-postbox-addon__item--info__desc">
                    <?php echo wp_kses($addOn->getShortDescription(), 'data'); ?>
                </p>
            </div>
        </div>
        <div class="wpsms-postbox-addon__item--actions" data-addon-slug="<?php echo $addOn->getSlug(); ?>">
            <div class="wpsms-postbox-addon__item__statuses js-addon-statuses-wrapper">
                <span class="wpsms-postbox-addon__status wpsms-postbox-addon__status--<?php echo esc_attr($addOn->getStatusClass()); ?> js-wpsms-addon-status-<?php echo esc_attr($addOn->getStatusClass()); ?>"><?php echo esc_html($addOn->getStatusLabel()); ?></span>
            </div>
            <div class="wpsms-postbox-addon__buttons">
                <?php if ($addOn->isInstalled() && !$addOn->isActivated()) : ?>
                    <a class="wpsms-postbox-addon__button js-addon-active-plugin-btn" data-slug="<?php echo esc_attr($addOn->getSlug()); ?>" title="<?php esc_html_e('Active', 'wp-sms'); ?>"><?php esc_html_e('Active', 'wp-sms'); ?></a>
                <?php endif; ?>
                <?php if ($addOn->isInstalled()) : ?>
                    <a class="wpsms-postbox-addon__button js-wpsms-addon-license-button"><?php esc_html_e('License', 'wp-sms'); ?></a>
                <?php endif; ?>
            </div>
            <div class="wpsms-addon--actions">
                <span class="wpsms-addon--actions--show-more js-addon-show-more"></span>
                <ul class="wpsms-addon--submenus">
                    <?php if ($addOn->isActivated()) : ?>
                        <li><a href="<?php echo esc_url($addOn->getSettingsUrl()); ?>" class="wpsms-addon--submenu wpsms-addon--submenu__settings" target="_blank"><span><?php esc_html_e('Settings', 'wp-sms'); ?></span></a></li>
                        <li><span class="wpsms-separator"></span></li>
                    <?php endif; ?>
                    <li><a href="<?php echo esc_url($addOn->getProductUrl()); ?>?utm_source=wp-sms&utm_medium=link&utm_campaign=dp" class="wpsms-addon--submenu" target="_blank"><?php esc_html_e('Add-On Details', 'wp-sms'); ?></a></li>
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
    <div class="wpsms-addon__item__license js-wpsms-addon-license">
        <div class="wpsms-addon__item__update_license">
            <input data-addon-slug="<?php echo esc_attr($addOn->getSlug()) ?>" type="text" placeholder="xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" value="<?php echo esc_attr($addOn->getLicenseKey()) ?>">
            <button><?php esc_html_e('Update License', 'wp-sms'); ?></button>
        </div>
        <?php if (isset($alert_text)) : ?>
            <div class="wpsms-alert wpsms-alert--<?php echo esc_attr($alert_class); ?>">
                <span class="icon"></span>
                <div>
                    <p><?php echo esc_html($alert_text); ?></p>
                    <?php if (isset($alert_link_text)) : ?>
                        <div>
                            <a href="<?php echo esc_url($alert_link); ?>" class="js-wpsms-addon-check-box" title="<?php echo esc_attr($alert_link_text); ?>"><?php echo esc_html($alert_link_text); ?></a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
     </div>
    <div class="wpsms-addon__download__item__info__alerts js-wpsms-addon-alert-wrapper"></div>
</div>