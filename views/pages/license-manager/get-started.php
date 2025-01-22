<?php

use WP_SMS\Admin\LicenseManagement\Plugin\PluginDecorator;
use WP_SMS\Components\View;
use WP_SMS\Utils\MenuUtil;
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
                <a class="wpsms-addon__download_active-all js-addon_active-all <?php echo empty($data['display_activate_all']) ? 'wpsms-hide' : ''; ?>"><?php esc_html_e('Activate All', 'wp-statistics'); ?></a>
            </div>
            <div class="wpsms-addon__download__items wpsms-addon__download__items--get-started">
                <?php
                if (!empty($data['licensed_addons'])) {
                    $selectedAddOns = !empty($data['selected_addons']) ? $data['selected_addons'] : [];

                    /** @var PluginDecorator $addOn */
                    foreach ($data['licensed_addons'] as $addOn) {
                        View::load('components/addon-active-card', [
                            'addOn'          => $addOn,
                            'selectedAddOns' => $selectedAddOns,
                        ]);
                    }
                }
                ?>
            </div>
        </div>
        <div class="wpsms-review_premium">
            <div>
                <div class="wpsms-review_premium__content">
                    <h4><?php esc_html_e('Love WP Statistics Premium? Let Us Know!', 'wp-statistics'); ?></h4>
                    <p><?php esc_html_e('Thanks for choosing WP Statistics Premium! If you’re enjoying the new features, please leave us a 5-star review. Your feedback helps us improve!', 'wp-statistics'); ?></p>
                    <p><?php esc_html_e('Thanks for being part of our community!', 'wp-statistics'); ?></p>
                </div>
                <div class="wpsms-review_premium__actions">
                    <a href="https://wordpress.org/support/plugin/wp-statistics/reviews/?filter=5#new-post" target="_blank" class="wpsms-review_premium__actions__review-btn"><?php esc_html_e('Write a Review', 'wp-statistics'); ?></a>
                    <a href="<?php echo esc_url(MenuUtil::getAdminUrl('overview')); ?>" class="wpsms-review_premium__actions__overview-btn"><?php esc_html_e('No, Take me to the Overview', 'wp-statistics'); ?></a>
                </div>
            </div>
        </div>
    </div>
</div>