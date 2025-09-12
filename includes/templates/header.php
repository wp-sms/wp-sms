<?php

use WP_SMS\Admin\LicenseManagement\ApiCommunicator;
use WP_SMS\Admin\LicenseManagement\LicenseHelper;
use WP_SMS\Components\View;
use WP_SMS\Option as Option;
use WP_SMS\Services\Notification\NotificationFactory;
use WP_SMS\Admin\LicenseManagement\LicenseMigration;
use WP_SMS\Utils\MenuUtil;
use WP_SMS\Version;
use WP_SMS\Admin\ModalHandler\Modal;

$option = get_option('wpsms_settings');
// Create tab url and active class for licenses tab
$tab_url   = add_query_arg(array(
    'settings-updated' => false,
    'tab'              => 'licenses',
    'page'             => 'wp-sms-settings'
));
$active    = isset($_GET['tab']) && $_GET['tab'] == 'licenses' ? 'active' : '';
$isPremium = LicenseHelper::isPremiumLicenseAvailable();

// Get information about active add-ons
$addons = is_plugin_active('wp-sms-pro/wp-sms-pro.php') ? array('license_wp-sms-pro_status' => false) : array();

$get_addons = wp_sms_get_addons();
foreach ($get_addons as $addOnKey => $addOnName) {
    $addons["license_{$addOnKey}_status"] = false;
}

foreach ($addons as $option_key => $status) {
    if (isset($option[$option_key]) && $option[$option_key]) {
        $addons[$option_key] = true;
    }
}

$hasUpdatedNotifications = NotificationFactory::hasUpdatedNotifications();
$displayNotifications    = (bool)Option::getOption('plugin_notifications');
$apiCommunicator  = new ApiCommunicator();
$licenseMigration = new LicenseMigration($apiCommunicator);
$licenseMigration->migrateOldLicenses();
?>
<div class="wpsms-header-banner <?php echo $isPremium ? 'wpsms-header-banner__aio' : '' ?>">
    <div class="wpsms-header-logo"></div>
    <!-- Header Items -->
    <div class="wpsms-header-items-flex">
        <?php
        $unreadMessagesCount = method_exists(\WPSmsTwoWay\Models\IncomingMessage::class, 'countOfUnreadMessages') ? \WPSmsTwoWay\Models\IncomingMessage::countOfUnreadMessages() : 0;
        echo \WP_SMS\Helper::loadTemplate('admin/partials/menu-link.php', ['slug' => 'wp-sms', 'link_text' => __('Send SMS', 'wp-sms'), 'icon_class' => 'send-sms', 'badge_count' => null]);
        echo \WP_SMS\Helper::loadTemplate('admin/partials/menu-link.php', ['slug' => 'wp-sms-inbox', 'link_text' => __('Inbox', 'wp-sms'), 'icon_class' => 'inbox', 'badge_count' => $unreadMessagesCount]);
        echo \WP_SMS\Helper::loadTemplate('admin/partials/menu-link.php', ['slug' => 'wp-sms-outbox', 'link_text' => __('Outbox', 'wp-sms'), 'icon_class' => 'outbox', 'badge_count' => null]);
        echo \WP_SMS\Helper::loadTemplate('admin/partials/menu-link.php', ['slug' => 'wp-sms-integrations', 'link_text' => __('Integrations', 'wp-sms'), 'icon_class' => 'integrations', 'badge_count' => null]);
        ?>
    </div>
    <div class="wpsms-header-items-side">
        <?php echo \WP_SMS\Helper::loadTemplate('admin/partials/license-status.php', ['addons' => $addons, 'tab_url' => $tab_url]); ?>
        <a href="<?php echo esc_url(WP_SMS_ADMIN_URL . 'admin.php?page=wp-sms-settings'); ?>" title="<?php esc_html_e('Settings', 'wp-sms'); ?>" class="setting <?php if (isset($_GET['page']) && $_GET['page'] === 'wp-sms-settings') {
            echo 'active';
        } ?>"></a>
        <a href="<?php echo esc_url(WP_SMS_SITE . '/support?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>" target="_blank" title="<?php esc_html_e('Help Center', 'wp-sms'); ?>" class="support"></a>
        <?php
        if ($displayNotifications): ?>
            <a title="<?php esc_html_e('Notifications', 'wp-sms'); ?>" class="wpsms-notifications js-wpsms-open-notification <?php echo $hasUpdatedNotifications ? esc_attr('wpsms-notifications--has-items') : ''; ?>"></a>
        <?php endif; ?>
        <div class="wpsms-mobile-menu">
            <input type="checkbox" id="wpsms-menu-toggle" class="hamburger-menu">
            <label for="wpsms-menu-toggle" class="hamburger-menu-container">
                <div class="hamburger-menu-bar">
                    <div class="menu-bar"></div>
                    <div class="menu-bar"></div>
                    <div class="menu-bar"></div>
                </div>
                <span><?php esc_html_e('Menu', 'wp-sms'); ?></span>
            </label>
            <div class="wpsms-menu-content">
                <?php
                echo \WP_SMS\Helper::loadTemplate('admin/partials/menu-link.php', ['slug' => 'wp-sms-outbox', 'link_text' => __('Outbox', 'wp-sms'), 'icon_class' => 'outbox', 'badge_count' => null]);
                echo \WP_SMS\Helper::loadTemplate('admin/partials/menu-link.php', ['slug' => 'wp-sms-integrations', 'link_text' => __('Integrations', 'wp-sms'), 'icon_class' => 'integrations', 'badge_count' => null]);
                echo \WP_SMS\Helper::loadTemplate('admin/partials/menu-link.php', ['slug' => 'wp-sms-settings', 'link_text' => __('Settings', 'wp-sms'), 'icon_class' => 'settings', 'badge_count' => null]);
                ?>
                <?php if ($displayNotifications): ?>
                    <a class="wpsms-notifications js-wpsms-open-notification <?php echo $hasUpdatedNotifications ? esc_attr('wpsms-notifications--has-items') : ''; ?>">
                        <span class="icon"></span><span><?php esc_html_e('Notifications', 'wp-sms'); ?></span>
                    </a>
                <?php endif; ?>
                <a href="<?php echo esc_url(WP_SMS_SITE . '/support?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>" target="_blank" title="<?php esc_html_e('Help Center', 'wp-sms'); ?>" class="help">
                    <span class="icon"></span>
                    <?php esc_html_e('Help Center', 'wp-sms'); ?>
                </a>
                <div class="wpsms-license">
                    <?php echo \WP_SMS\Helper::loadTemplate('admin/partials/license-status.php', ['addons' => $addons, 'tab_url' => $tab_url]); ?>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
if ($displayNotifications) {
    View::load("components/notification/side-bar", ['notifications' => NotificationFactory::getAllNotifications()]);
}
?>
<?php Modal::showOnce('welcome-premium'); ?>

<?php
add_action('admin_footer', function () {
    if (MenuUtil::isInPluginPage()) {
        Modal::showOnce('welcome-premium');
    }
}, 20);
?>
<?php Modal::render('all-in-one'); ?>
