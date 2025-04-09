<?php

use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;

$pluginHandler = new PluginHandler();

$hasLicense  = false;
$isActive    = false;
$isInstalled = false;
$isPremium   = isset($isPremium) ? $isPremium : false;

if ($step_name !== 'first-step') {
    $isActive    = $pluginHandler->isPluginActive($step_name);
    $isInstalled = $pluginHandler->isPluginInstalled($step_name);
    $hasLicense  = LicenseHelper::isPluginLicenseValid($step_name);
}
?>

<div class="wp-sms-modal__premium-step js-wp-sms-premiumModalStep wp-sms-modal__premium-step--<?php echo esc_attr($step_name) ?>">
    <div class="js-wp-sms-premium-steps__head">
        <div class="js-wp-sms-premium-step__title">
            <?php if ($step_name === 'first-step') : ?>
                <?php if ($isPremium) : ?>
                    <p><?php esc_html_e('You\'re All Set with WP SMS All-in-One', 'wp-sms'); ?></p>
                <?php elseif ($hasLicense && !$isPremium) : ?>
                    <p><?php esc_html_e('You\'re Already Enjoying Add-Ons!', 'wp-sms'); ?></p>
                <?php else : ?>
                    <p><?php esc_html_e('Try the upgrade. See more. Do more.', 'wp-sms'); ?></p>
                <?php endif; ?>
            <?php else: ?>
                <?php echo esc_html($step_title); ?>
            <?php endif; ?>
        </div>
        <?php echo $description; ?>
    </div>

    <?php if ($step_name !== 'first-step') : ?>
        <img class="wp-sms-premium-step__image v-image-lazy" width="509" height="291" data-src="<?php echo WP_SMS_URL . 'assets/images/premium-modal/' . esc_attr($step_name) . '.png'; ?>" alt="<?php echo esc_attr($step_name); ?>">

        <?php if ($hasLicense && !$isActive) : ?>
            <div class="wp-sms-premium-step__notice">
                <div>
                    <?php echo sprintf(__('Your license includes the %s, but it’s not installed yet. Go to the Add-Ons page to install and activate it, so you can start using all its features.', 'wp-sms'),
                        esc_attr($step_title)) ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!$hasLicense && $isInstalled) : ?>
            <div class="wp-sms-premium-step__notice wp-sms-premium-step__notice--warning">
                <div>
                    <?php echo sprintf(__('This add-on does <b>not have an active license</b>, which means it cannot receive updates, including important security updates. For uninterrupted access to updates and to keep your site secure, we strongly recommend activating a license. Activate your license <a href="%s">here</a>.', 'wp-sms'),
                        esc_url(admin_url('admin.php?page=wp-sms-add-ons'))) ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <img class="wp-sms-premium-step__image v-image-lazy" width="509" height="291" data-src="<?php echo WP_SMS_URL . 'assets/images/premium-modal/first-step.png'; ?>">
    <?php endif; ?>
</div>