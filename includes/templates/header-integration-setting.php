<?php $option = get_option('wpsms_settings'); ?>
<div class="wpsms-header-banner" style="<?php echo isset($full_width_banner) && $full_width_banner ? 'margin-left: -20px; width: auto; max-width: none;' : ''; ?>">
    <div class="wpsms-header-logo"></div>

    <?php
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

    <!-- Header Items -->
    <div class="wpsms-header-items-flex">
        <a class="send-sms" href="<?php echo esc_url(WP_SMS_ADMIN_URL . 'admin.php?page=wp-sms'); ?>"><span class="icon"></span><?php esc_html_e('Send SMS', 'wp-sms'); ?></a>
        <a class="inbox" href="<?php echo esc_url(WP_SMS_ADMIN_URL . 'admin.php?page=wp-sms-inbox'); ?>"><span class="icon"></span><?php esc_html_e('Inbox', 'wp-sms'); ?> <span class="badge">2</span></a>
        <a class="outbox" href="<?php echo esc_url(WP_SMS_ADMIN_URL . 'admin.php?page=wp-sms-outbox'); ?>"><span class="icon"></span><?php esc_html_e('Outbox', 'wp-sms'); ?></a>
        <a class="integrations active" href="<?php echo esc_url(WP_SMS_ADMIN_URL . 'admin.php?page=wp-sms-integrations'); ?>"><span class="icon"></span><?php esc_html_e('Integrations', 'wp-sms'); ?></a>
    </div>

    <div class="wpsms-header-items-side">
        <?php if (count($addons) == 0) : ?>
            <div class="license-status license-status--free">
                <a class="upgrade" href="<?php echo esc_url(WP_SMS_SITE . '/buy?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>" target="_blank"><span><?php esc_html_e('UPGRADE TO PRO', 'wp-sms'); ?></span></a>
            </div>
        <?php else : ?>
            <div class="license-status license-status--valid">
                <span>
                    <?php
                    // translators: %1$s: Active licenses, %2$s: Total licenses
                    echo sprintf(esc_html__('License: %1$s/%2$s', 'wp-sms'), count(array_filter($addons)), count($addons));
                    ?>
                    <a class="upgrade" target="_blank" href="<?php echo esc_url(WP_SMS_SITE . '/buy?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>"><?php esc_html_e('UPGRADE', 'wp-sms'); ?></a>
                </span>
            </div>
        <?php endif; ?>
        <a href="<?php echo esc_url(WP_SMS_ADMIN_URL . 'admin.php?page=wp-sms-settings'); ?>" title="<?php esc_html_e('setting', 'wp-sms'); ?>" class="setting <?php if (isset($_GET['page']) && $_GET['page'] === 'wp-sms-settings') {
            echo 'active';
        } ?>"></a>
        <a href="<?php echo esc_url(WP_SMS_SITE . '/support?utm_source=wp-sms&utm_medium=link&utm_campaign=header-support'); ?>" target="_blank" title="<?php esc_html_e('support', 'wp-sms'); ?>" class="support"></a>

    </div>

</div>