<?php

use WP_SMS\Components\View;
use WP_SMS\Utils\MenuUtil;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginDecorator;

if (!empty($data['licensed_addons'])) {
    $total_installed_addons = 0;
    $total_licensed_addons  = count($data['licensed_addons']);
    foreach ($data['licensed_addons'] as $addOn) {
        $total_installed_addons = $addOn->isInstalled() ? $total_installed_addons + 1 : -1;
    }
}
?>
<div class="wpsms-wrap__main">
    <div class="wp-header-end"></div>

    <div class="wpsms-postbox-addon__step">
        <div class="wpsms-addon__step__info">
            <span class="wpsms-addon__step__image wpsms-addon__step__image--checked"></span>
            <h2 class="wpsms-addon__step__title"><?php esc_html_e('You\'re All Set! Your License is Successfully Activated!', 'wp-sms'); ?></h2>
            <p class="wpsms-addon__step__desc"><?php esc_html_e('Choose the add-ons you want to install. You can modify your selection later.', 'wp-sms'); ?></p>
        </div>
        <div class="wpsms-addon__step__download">
            <div class="wpsms-addon__download__title">
                <h3>
                    <?php esc_html_e('Select Your Add-Ons', 'wp-sms'); ?>
                </h3>
                <a class="wpsms-addon__download_select-all js-wpsms-addon-select-all <?php echo empty($data['display_select_all']) ? 'wpsms-hide' : ''; ?>"><?php esc_html_e('Select All', 'wp-sms'); ?></a>
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
            <a href="<?php echo esc_url(MenuUtil::getAdminUrl('wp-sms-add-ons-1', ['tab' => 'add-license'])); ?>" class="wpsms-addon__step__back"><?php esc_html_e('Back', 'wp-sms'); ?></a>
            <?php if ($total_installed_addons > 0 && $total_installed_addons == $total_licensed_addons) { ?>
                <a href="<?php echo esc_url(MenuUtil::getAdminUrl('wp-sms-add-ons-1', ['tab' => 'get-started', 'license_key' => \WP_SMS\Utils\Request::get('license_key')])); ?>" class="wpsms-postbox-addon-button js-addon-download-button">
                    <?php esc_html_e('Activate Add-Ons', 'wp-sms'); ?>
                </a>
            <?php } else { ?>
                <a class="wpsms-postbox-addon-button js-addon-download-button disabled">
                    <?php esc_html_e('Download & Install Selected Add-Ons', 'wp-sms'); ?>
                </a>
            <?php } ?>
        </div>
    </div>
</div>