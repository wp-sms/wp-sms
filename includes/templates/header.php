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
        <a class="documentation" target="_blank" href="<?php echo WP_SMS_SITE; ?>/documentation/"><span class="icon"></span><?php _e('Documentation', 'wp-sms'); ?></a>
        <a class="gateway" target="_blank" href="<?php echo WP_SMS_SITE; ?>/gateways/"><span class="icon"></span><?php _e('Gateways Directory', 'wp-sms'); ?></a>
        <a class="zapier" target="_blank" href="<?php echo WP_SMS_SITE; ?>/zapier-integration"><span class="icon"></span><?php _e('Zapier Integration', 'wp-sms'); ?></a>
        <a class="licenses<?php echo ' ' . esc_attr($active); ?>" href="<?php echo esc_url($tab_url); ?>"><span class="icon"></span><?php _e('Manage Licenses', 'wp-sms'); ?></a>
    </div>

    <!-- Activated Licenses Status -->
    <?php if (count($addons) == 0) : ?>
        <div class="license-status license-status--free">
            <a href="<?php echo esc_url(WP_SMS_SITE); ?>/buy" target="_blank"><span><?php _e('Unlock More Features!', 'wp-sms'); ?></a></span>
        </div>
    <?php else : ?>
        <div class="license-status license-status--valid">
            <span><?php echo sprintf(__('License Status: %s of %s Activated.', 'wp-sms'), count(array_filter($addons)), count($addons)); ?></span>
        </div>
    <?php endif; ?>

</div>