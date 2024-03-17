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
        <a class="zapier" target="_blank" href="<?php echo esc_url(WP_SMS_SITE . '/zapier-integration'); ?>"><span class="icon"></span><?php esc_html_e('Zapier Integration', 'wp-sms'); ?></a>
    </div>

    <!-- Activated Licenses Status -->
    <?php if (count($addons) == 0) : ?>
        <div class="license-status license-status--free">
            <a href="<?php echo esc_url(WP_SMS_SITE . '/buy'); ?>" target="_blank"><span><?php esc_html_e('Unlock More Features!', 'wp-sms'); ?></a></span>
        </div>
    <?php else : ?>
        <div class="license-status license-status--valid">
            <span>
                <?php 
                    // translators: %1$s: Active licenses, %2$s: Total licenses
                    echo sprintf(esc_html__('License Status: %1$s of %2$s Activated.', 'wp-sms'), count(array_filter($addons)), count($addons)); 
                ?>
            </span>
        </div>
    <?php endif; ?>

</div>