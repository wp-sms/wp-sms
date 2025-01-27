<?php

/** @var WP_Statistics\Service\Admin\LicenseManagement\Plugin\PluginDecorator $addOn */
if (!defined('ABSPATH') || empty($addOn)) {
    exit;
}

?>
<div class="wpsms-addon__download__item <?php echo !$included ? 'wpsms-addon__download__item--disabled' : ''; ?>">
    <div class="wpsms-addon__download__item--info">
        <div class="wpsms-addon__download__item--info__img">
            <img src="<?php echo esc_url($addOn->getIcon()); ?>" alt="<?php echo esc_attr($addOn->getName()); ?>">
        </div>
        <div class="wpsms-addon__download__item--info__text">
            <div class="wpsms-addon__download__item--info__title">
                <?php echo esc_html($addOn->getName()); ?>
                <?php if (!empty($addOn->getProductUrl())) : ?>
                    <a target="_blank" href="<?php echo esc_html($addOn->getProductUrl()); ?>?utm_source=wp-sms&utm_medium=link&utm_campaign=dp" class="wpsms-postbox-addon__read-more" title="<?php esc_html_e('Learn More', 'wp-sms'); ?>">
                        <?php esc_html_e('Learn More', 'wp-sms'); ?>
                    </a>
                <?php endif; ?>

                <?php if ($included && $addOn->isUpdateAvailable()) : ?>
                    <span class="wpsms-postbox-addon__label wpsms-postbox-addon__label--updated"><?php esc_html_e('Update Available', 'wp-sms'); ?></span>
                <?php endif; ?>
            </div>
            <p class="wpsms-addon__download__item--info__desc">
                <?php echo wp_kses($addOn->getShortDescription(), 'data'); ?>
            </p>
        </div>
    </div>
    <div class="wpsms-addon__download__item--select">
        <?php if ($included && $addOn->isInstalled()) : ?>
            <span class="wpsms-postbox-addon__status wpsms-postbox-addon__status--installed "><?php esc_html_e('Already installed', 'wp-sms'); ?></span>
        <?php elseif (!$included) : ?>
            <span class="wpsms-postbox-addon__status wpsms-postbox-addon__status--primary "><?php esc_html_e('Not included', 'wp-sms'); ?></span>
        <?php endif; ?>

        <?php if ($included && (!$addOn->isInstalled() || $addOn->isUpdateAvailable())) : ?>
            <span> <input type="checkbox" class="js-wpsms-addon-check-box" name="addon-select" data-slug="<?php echo esc_attr($addOn->getSlug()); ?>"></span>
        <?php endif; ?>
    </div>
</div>