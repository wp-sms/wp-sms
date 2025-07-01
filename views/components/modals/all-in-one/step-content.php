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

<div class="wp-sms-modal__aio-step js-wp-sms-aioModalStep wp-sms-modal__aio-step--<?php echo esc_attr($step_name) ?>">
    <div class="js-wp-sms-aio-steps__head">
        <div class="js-wp-sms-aio-step__title">
            <?php echo esc_html($step_title); ?>
        </div>
        <span class="wp-sms-modal__aio-step__desc">
            <?php echo $description; ?>
        </span>
    </div>

    <?php if ($step_name !== 'first-step') : ?>
        <img class="wp-sms-aio-step__image v-image-lazy" width="509" height="291" data-src="<?php echo WP_SMS_URL . 'assets/images/premium-modal/' . esc_attr($step_name) . '.png'; ?>" alt="<?php echo esc_attr($step_name); ?>">

        <?php if ($hasLicense && !$isInstalled) : ?>
            <div class="wp-sms-aio-step__notice">
                <div>
                    <?php printf(
                        __('Your license includes the %s, but it’s not installed yet. Go to the Add-Ons page to install and %s it, so you can start using all its features.', 'wp-sms'),
                        '<b>' . esc_attr($addon_name) . '</b>',
                        '<b>' . __('activate', 'wp-sms') . '</b>'
                    ); ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if (!$hasLicense && $isActive) : ?>
            <div class="wp-sms-aio-step__notice wp-sms-aio-step__notice--warning">
                <div>
                    <?php printf(
                        __('This add-on does %s, which means it cannot receive updates, including important security updates. For uninterrupted access to updates and to keep your site secure, we strongly recommend activating a license. Activate your license %s.', 'wp-sms'),
                        '<b>' . __('not have an active license', 'wp-sms') . '</b>',
                        '<a href="' . esc_url(admin_url('admin.php?page=wp-sms-add-ons')) . '">' . __('here', 'wp-sms') . '</a>'
                    ); ?>
                </div>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <img class="wp-sms-aio-step__image v-image-lazy" width="509" height="291" data-src="<?php echo WP_SMS_URL . 'assets/images/premium-modal/first-step.png'; ?>">
    <?php endif; ?>
</div>