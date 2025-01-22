<?php

use WP_SMS\Components\View;
use WP_SMS\Utils\MenuUtil;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginDecorator;

?>
<div class="wpsms-wrap__main">
    <div class="wp-header-end"></div>

    <div class="wpsms-postbox-addon__step">
        <div class="wpsms-addon__step__info">
            <span class="wpsms-addon__step__image wpsms-addon__step__image--checked"></span>
            <h2 class="wpsms-addon__step__title"><?php esc_html_e('You\'re All Set! Your License is Successfully Activated!', 'wp-statistics'); ?></h2>
            <p class="wpsms-addon__step__desc"><?php esc_html_e('Choose the add-ons you want to install. You can modify your selection later.', 'wp-statistics'); ?></p>
        </div>
        <div class="wpsms-addon__step__download">
            <div class="wpsms-addon__download__title">
                <h3>
                    <?php esc_html_e('Select Your Add-Ons', 'wp-statistics'); ?>
                </h3>
                <a class="wpsms-addon__download_select-all js-wpsms-addon-select-all <?php echo empty($data['display_select_all']) ? 'wpsms-hide' : ''; ?>"><?php esc_html_e('Select All', 'wp-statistics'); ?></a>
            </div>
            <div class="wpsms-addon__download__items">
                <?php
                if (!empty($data['licensed_addons'])) {
                    /** @var PluginDecorator $addOn */
                    foreach ($data['licensed_addons'] as $addOn) {
                        View::load('components/addon-download-card', ['addOn' => $addOn, 'included' => true]);
                    }
                }

                if (!empty($data['not_included_addons'])) {
                    /** @var PluginDecorator $addOn */
                    foreach ($data['not_included_addons'] as $addOn) {
                        View::load('components/addon-download-card', ['addOn' => $addOn, 'included' => false]);
                    }
                }
                ?>
            </div>
        </div>
        <div class="wpsms-addon__step__action">
            <a href="<?php echo esc_url(MenuUtil::getAdminUrl('plugins', ['tab' => 'add-license'])); ?>" class="wpsms-addon__step__back"><?php esc_html_e('Back', 'wp-statistics'); ?></a>
            <a class="wpsms-postbox-addon-button js-addon-download-button disabled">
                <?php esc_html_e('Download & Install Selected Add-Ons', 'wp-statistics'); ?>
            </a>
        </div>
    </div>
</div>