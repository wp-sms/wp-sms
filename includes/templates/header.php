<?php $option = get_option('wpsms_settings'); ?>
<div class="wpsms-header-banner">
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
    $wp_sms_addons = array(
        'wp-sms-pro/wp-sms-pro.php'                         => 'license_wp-sms-pro_status',
        'wp-sms-two-way/wp-sms-two-way.php'                 => 'license_wp-sms-two-way_status',
        'wp-sms-woocommerce-pro/wp-sms-woocommerce-pro.php' => 'license_wp-sms-woocommerce-pro_status'
    );

    $addons = array();
    foreach ($wp_sms_addons as $name => $option_key) {
        if (is_plugin_active($name)) {
            if (isset($option[$option_key]) && $option[$option_key]) {
                $addons[$option_key] = true;
            } else {
                $addons[$option_key] = false;
            }
        }
    }
    ?>

    <!-- Header Items -->
    <div class="wpsms-header-items-flex">
        <a class="documentation" target="_blank" href="<?php echo WP_SMS_SITE; ?>/documentation/"><span class="icon"></span><?php _e('Documentation', 'wp-sms'); ?></a>
        <a class="gateway" target="_blank" href="<?php echo WP_SMS_SITE; ?>/gateways/add-new/"><span class="icon"></span><?php _e('Gateway', 'wp-sms'); ?></a>
        <a class="zapier" target="_blank" href="<?php echo WP_SMS_SITE; ?>/zapier-integration"><span class="icon"></span><?php _e('Zapier Integration', 'wp-sms'); ?></a>
        <a class="licenses<?php echo ' ' . $active; ?>" href="<?php echo esc_url($tab_url); ?>"><span class="icon"></span><?php _e('Manage Licenses', 'wp-sms'); ?></a>
    </div>

    <!-- Activated Licenses Status -->
    <?php if (!is_plugin_active('wp-sms-pro/wp-sms-pro.php')) : ?>
        <div class="license-status license-status--free">
            <a href="<?php echo WP_SMS_SITE; ?>/buy" target="_blank"><span><?php _e('Get more features!', 'wp-sms'); ?></a></span>
        </div>
    <?php elseif (count($addons) > 0) : ?>
        <div class="license-status license-status--valid">
            <span><?php echo sprintf(__('%s/%s Active License!', 'wp-sms'), count(array_filter($addons)), count($addons)); ?></span>
        </div>
    <?php endif; ?>

</div>