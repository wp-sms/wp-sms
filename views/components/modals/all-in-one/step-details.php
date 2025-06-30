<?php

use WP_SMS\Components\View;
use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHandler;
use WP_SMS\Admin\LicenseManagement\Plugin\PluginHelper;

$pluginHandler    = new pluginHandler();
$installedPlugins = $pluginHandler->getInstalledPlugins();
$hasLicense       = LicenseHelper::isValidLicenseAvailable();
$isPremium        = LicenseHelper::isPremiumLicenseAvailable();

?>
<div class="wp-sms-aio-step">
    <div class="wp-sms-aio-step__header">
        <span class="wp-sms-aio-step__skip js-wp-sms-aioModalClose"></span>
        <span><?php esc_html_e('WP SMS All-in-One ', 'wp-sms'); ?></span>

        <div class="wp-sms-aio-step__title js-wp-sms-dynamic-title" id="dynamic-title"></div>
    </div>
    <div class="wp-sms-aio-step__body">
        <div class="wp-sms-aio-step__content">
            <?php

            $defaultDescription = __('<p>All-in-One includes Pro, WooCommerce Pro, Two-Way, and more. Send better SMS, handle two-way messaging, secure logins, and manage everything in one place.</p>', 'wp-sms');
            $premiumDescription = __('<p>You already have the complete bundle! Enjoy every premium feature and integration with no extra steps. Thanks for your support—have fun exploring everything!</p>', 'wp-sms');
            $licenseDescription = __('<p>Looks like you have a few premium features active. Upgrade to All‑in‑One to unlock every tool and integration. Get the most out of WP SMS and boost your site’s performance.</p>', 'wp-sms');

            $data = [
                'step_name'   => 'first-step',
                'description' => $defaultDescription,
                'step_href'   => esc_url(WP_SMS_SITE . '/pricing/?utm_source=wp-sms&utm_medium=link&utm_campaign=pop-up-aio'),
                'step_title'  => $isPremium ? esc_html__('You’re All Set with WP SMS All‑in‑One', 'wp-sms') : ($hasLicense && !$isPremium ? esc_html__('You\'re Already Enjoying Add-Ons!', 'wp-sms') : esc_html__('All Premium SMS Features in One Package', 'wp-sms')),
            ];

            if ($isPremium) {
                $data['description'] = $premiumDescription;
            } elseif ($hasLicense && !$isPremium) {
                $data['description'] = $licenseDescription;
            }

            View::load("components/modals/all-in-one/step-content", $data);


            $data = [
                'step_name'   => 'wp-sms-pro',
                'addon_name'  => esc_html__('WP SMS Pro', 'wp-sms'),
                'step_title'  => esc_html__('Key SMS Tools for Your Site', 'wp-sms'),
                'description' => esc_html__('WP SMS Pro offers phone logins, two-factor authentication, scheduled and repeating messages, shorter Bitly URLs, and a Gutenberg block. It also integrates with WooCommerce, BuddyPress, Quform, Gravity Forms, Easy Digital Downloads, WP Job Manager, and WP Awesome Support.', 'wp-sms'),
            ];
            View::load("components/modals/all-in-one/step-content", $data);

            $data = [
                'step_name'   => 'wp-sms-woocommerce-pro',
                'addon_name'  => esc_html__('WP SMS WooCommerce Pro', 'wp-sms'),
                'step_title'  => esc_html__('Advanced WooCommerce SMS Features', 'wp-sms'),
                'description' => esc_html__('WooCommerce Pro boosts sales and support with SMS campaigns, abandoned cart reminders, phone verification at checkout, SMS login and registration, and local shipping notifications.', 'wp-sms')
            ];
            View::load("components/modals/all-in-one/step-content", $data);

            $data = [
                'step_name'   => 'wp-sms-two-way',
                'addon_name'  => esc_html__('WP SMS Two-Way', 'wp-sms'),
                'step_title'  => esc_html__('Send and Receive Messages', 'wp-sms'),
                'description' => esc_html__('Two-Way lets you view incoming texts in your dashboard, set keywords to trigger replies, allow customers to update orders via SMS, and let subscribers join or leave newsletters by texting.', 'wp-sms'),
            ];
            View::load("components/modals/all-in-one/step-content", $data);

            $data = [
                'step_name'   => 'wp-sms-integration',
                'addon_name'  => esc_html__('WP SMS Integrations', 'wp-sms'),
                'step_title'  => esc_html__('Extra Ways to Connect', 'wp-sms'),
                'description' => esc_html__('All-in-One also includes membership updates, Elementor SMS tools, Fluent marketing automation, and booking reminders.', 'wp-sms'),
            ];
            View::load("components/modals/all-in-one/step-content", $data);
            ?>
        </div>
        <div class="wp-sms-aio-step__sidebar">
            <div>
                <p><?php esc_html_e('WP SMS All-in-One Includes', 'wp-sms'); ?>:</p>
                <ul class="wp-sms-aio-step__features-list">
                    <?php foreach (PluginHelper::$plugins as $slug => $title) :
                        $class = '';

                        $isActive    = $pluginHandler->isPluginActive($slug);
                        $isInstalled = $pluginHandler->isPluginInstalled($slug);
                        $hasLicense  = LicenseHelper::isPluginLicenseValid($slug);

                        if ($hasLicense && $isActive) {
                            $class = 'activated';
                        } elseif ($hasLicense && $isInstalled && !$isActive) {
                            $class = 'not-active';
                        } elseif (!$hasLicense && $isActive) {
                            $class = 'no-license';
                        }
                        ?>
                        <li class="<?php echo esc_attr($class); ?> wp-sms-aio-step__feature js-wp-sms-aioStepFeature" data-modal="<?php echo esc_attr($slug) ?>">
                            <?php echo esc_html($title); ?>
                            <?php if ($hasLicense && !$isInstalled) : ?>
                                <span class="wp-sms-aio-step__feature-badge"><?php esc_html_e('Not Installed', 'wp-sms'); ?></span>
                            <?php elseif ($hasLicense && !$isActive) : ?>
                                <span class="wp-sms-aio-step__feature-badge"><?php esc_html_e('Not activated', 'wp-sms'); ?></span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="wp-sms-aio-step__actions">
                <div class="wp-sms-aio-step__head js-wp-sms-aio-first-step__head">
                    <?php
                    if ($isPremium) : ?>
                        <a class="wp-sms-aio-step__action-btn wp-sms-aio-step__action-btn--upgrade activated js-wp-sms-aioModalUpgradeBtn"><?php esc_html_e('All-in-One Activated', 'wp-sms'); ?></a>
                    <?php elseif ($hasLicense && !$isPremium) : ?>
                        <a target="_blank" href="<?php echo esc_url(WP_SMS_SITE . '/pricing?utm_source=wp-sms&utm_medium=link&utm_campaign=pop-up-aio') ?>" class="wp-sms-aio-step__action-btn wp-sms-aio-step__action-btn--upgrade js-wp-sms-aioModalUpgradeBtn"><?php esc_html_e('Upgrade to All-in-One', 'wp-sms'); ?></a>
                    <?php else : ?>
                        <a target="_blank" href="<?php echo esc_url(WP_SMS_SITE . '/pricing?utm_source=wp-sms&utm_medium=link&utm_campaign=pop-up-aio') ?>" class="wp-sms-aio-step__action-btn wp-sms-aio-step__action-btn--upgrade js-wp-sms-aioModalUpgradeBtn"><?php esc_html_e('Upgrade Now', 'wp-sms'); ?></a>
                    <?php endif; ?>

                    <?php if (!$isPremium) : ?>
                        <a class="wp-sms-aio-step__action-btn wp-sms-aio-step__action-btn--later js-wp-sms-aioModalClose"><?php esc_html_e('Maybe Later', 'wp-sms'); ?></a>
                    <?php endif; ?>
                </div>
                <div class="js-wp-sms-aio-steps__head js-wp-sms-aio-steps__side-buttons">
                    <?php foreach (PluginHelper::$plugins as $slug => $title) :
                        $isActive = $pluginHandler->isPluginActive($slug);
                        $isInstalled = $pluginHandler->isPluginInstalled($slug);
                        $hasLicense = LicenseHelper::isPluginLicenseValid($slug);
                        ?>
                        <div class="wp-sms-aio-step__action-container">
                            <?php if ($slug != 'wp-sms-integration'): ?>
                                <?php if (!$hasLicense && !$isInstalled) : ?>
                                    <a href="<?php echo esc_url(WP_SMS_SITE . '/pricing?utm_source=wp-sms&utm_medium=link&utm_campaign=pop-up-aio') ?>" target="_blank" class="wp-sms-aio-step__action-btn wp-sms-aio-step__action-btn--upgrade js-wp-sms-aioModalUpgradeBtn"><?php esc_html_e('Upgrade to All-in-One', 'wp-sms'); ?></a>
                                    <a class="wp-sms-aio-step__action-btn wp-sms-aio-step__action-btn--later js-wp-sms-aioModalClose"><?php esc_html_e('Maybe Later', 'wp-sms'); ?></a>
                                <?php elseif (($hasLicense && !$isActive) || (!$hasLicense && $isInstalled)) : ?>
                                    <a href="<?php echo esc_url(admin_url('admin.php?page=wp-sms-add-ons')) ?>" class="wp-sms-aio-step__action-btn js-wp-sms-aioModalUpgradeBtn wp-sms-aio-step__action-btn--addons"><?php esc_html_e('Go to Add-Ons Page', 'wp-sms'); ?></a>
                                <?php elseif ($hasLicense && $isActive) : ?>
                                    <a class="wp-sms-aio-step__action-btn wp-sms-aio-step__action-btn--upgrade  activated js-wp-sms-aioModalUpgradeBtn"><?php esc_html_e('Add-on Activated', 'wp-sms'); ?></a>
                                <?php endif; ?>
                            <?php else: ?>
                                <?php if (!$isPremium): ?>
                                    <a href="<?php echo esc_url(WP_SMS_SITE . '/pricing?utm_source=wp-sms&utm_medium=link&utm_campaign=pop-up-aio') ?>" target="_blank" class="wp-sms-aio-step__action-btn wp-sms-aio-step__action-btn--upgrade js-wp-sms-aioModalUpgradeBtn"><?php esc_html_e('Upgrade to All-in-One', 'wp-sms'); ?></a>
                                    <a class="wp-sms-aio-step__action-btn wp-sms-aio-step__action-btn--later js-wp-sms-aioModalClose"><?php esc_html_e('Maybe Later', 'wp-sms'); ?></a>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>