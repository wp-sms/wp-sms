<?php $option = get_option('wpsms_settings');
// Create tab url and active class for licenses tab
$tab_url = add_query_arg(array(
    'settings-updated' => false,
    'tab'              => 'licenses',
    'page'             => 'wp-sms-settings'
));
$active  = isset($_GET['tab']) && $_GET['tab'] == 'licenses' ? 'active' : '';

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
?>
<div class="wpsms-header-banner" style="<?php echo isset($full_width_banner) && $full_width_banner ? 'margin-left: -20px; width: auto; max-width: none;' : ''; ?>">
    <div class="wpsms-header-logo"></div>
    <!-- Header Items -->
    <div class="wpsms-header-items-flex">
        <?php
        $unreadMessagesCount = method_exists(\WPSmsTwoWay\Models\IncomingMessage::class, 'countOfUnreadMessages') ? \WPSmsTwoWay\Models\IncomingMessage::countOfUnreadMessages() : null;
        echo \WP_SMS\Helper::loadTemplate('admin/partials/menu-link.php', ['slug' => 'wp-sms', 'link_text' => __('Send SMS', 'wp-sms'), 'icon_class' => 'send-sms', 'badge_count' => null]);
        echo \WP_SMS\Helper::loadTemplate('admin/partials/menu-link.php', ['slug' => 'wp-sms-inbox', 'link_text' => __('Inbox', 'wp-sms'), 'icon_class' => 'inbox', 'badge_count' => $unreadMessagesCount]);
        echo \WP_SMS\Helper::loadTemplate('admin/partials/menu-link.php', ['slug' => 'wp-sms-outbox', 'link_text' => __('Outbox', 'wp-sms'), 'icon_class' => 'outbox', 'badge_count' => null]);
        echo \WP_SMS\Helper::loadTemplate('admin/partials/menu-link.php', ['slug' => 'wp-sms-integrations', 'link_text' => __('Integrations', 'wp-sms'), 'icon_class' => 'integrations', 'badge_count' => null]);
        ?>
    </div>
    <div class="wpsms-header-items-side">
        <?php echo \WP_SMS\Helper::loadTemplate('admin/partials/license-status.php', ['addons' => $addons,'tab_url'=>$tab_url]); ?>
        <a href="<?php echo esc_url(WP_SMS_ADMIN_URL . 'admin.php?page=wp-sms-settings'); ?>" title="<?php esc_html_e('Settings', 'wp-sms'); ?>" class="setting <?php if (isset($_GET['page']) && $_GET['page'] === 'wp-sms-settings') {
            echo 'active';
        } ?>"></a>
        <a href="<?php echo esc_url(WP_SMS_SITE . '/support?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>" target="_blank" title="<?php esc_html_e('Help Center', 'wp-sms'); ?>" class="support"></a>
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
                <a href="<?php echo esc_url(WP_SMS_SITE . '/support?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>" target="_blank" title="<?php esc_html_e('Help Center', 'wp-sms'); ?>" class="help">
                    <span class="icon"></span>
                    <?php esc_html_e('Help Center', 'wp-sms'); ?>
                </a>
                <div class="wpsms-license">
                    <?php echo \WP_SMS\Helper::loadTemplate('admin/partials/license-status.php', ['addons' => $addons,'tab_url'=>$tab_url]); ?>
                </div>
            </div>
        </div>
    </div>
</div>