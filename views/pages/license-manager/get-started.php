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
            <h2 class="wpsms-addon__step__title"><?php esc_html_e('Your Selected Add‑ons Are Installed!', 'wp-sms'); ?></h2>
            <p class="wpsms-addon__step__desc"><?php esc_html_e('They’re ready to go. Enjoy using WP SMS!', 'wp-sms'); ?></p>
        </div>
        <div class="wpsms-addon__step__download">
            <div class="wpsms-addon__download__title">
                <h3>
                    <?php esc_html_e('Activate Your Add-Ons', 'wp-sms'); ?>
                </h3>
                <a class="wpsms-addon__download_active-all js-addon_active-all <?php echo empty($data['display_activate_all']) ? 'wpsms-hide' : ''; ?>"><?php esc_html_e('Activate All', 'wp-sms'); ?></a>
            </div>
            <div class="wpsms-addon__download__items wpsms-addon__download__items--get-started">
                <?php
                if (!empty($data['licensed_addons'])) {
                    $selectedAddOns = !empty($data['selected_addons']) ? $data['selected_addons'] : [];

                    /** @var PluginDecorator $addOn */
                    foreach ($data['licensed_addons'] as $addOn) {
                        if ($addOn->isInstalled()) {
                            View::load('components/addon-active-card', [
                                'addOn'          => $addOn,
                                'selectedAddOns' => $selectedAddOns,
                            ]);
                        }
                    }
                }
                ?>
            </div>
        </div>
        <div class="wpsms-review_aio">
            <div>
                <div class="wpsms-review_aio__content">
                    <h4><?php esc_html_e('Love WP SMS All-In-One? Let Us Know!', 'wp-sms'); ?></h4>
                    <p><?php esc_html_e('Thanks for choosing WP SMS All-In-One! If you’re enjoying the new features, please leave us a 5-star review. Your feedback helps us improve!', 'wp-sms'); ?></p>
                    <p><?php esc_html_e('Thanks for being part of our community!', 'wp-sms'); ?></p>
                </div>
                <div class="wpsms-review_aio__actions">
                    <a href="https://wordpress.org/support/plugin/wp-sms/reviews/?filter=5#new-post" target="_blank" class="wpsms-review_aio__actions__review-btn"><?php esc_html_e('Write a Review', 'wp-sms'); ?></a>
                    <a href="<?php echo esc_url(MenuUtil::getAdminUrl('wp-sms')); ?>" class="wpsms-review_aio__actions__overview-btn"><?php esc_html_e('No, take me to the Send SMS page', 'wp-sms'); ?></a>
                </div>
            </div>
        </div>
    </div>
</div>