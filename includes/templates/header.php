<?php $option = get_option('wpsms_settings'); ?>
<div class="wpsms-header-banner">

    <?php

    // Create tab url and active class for licenses tab
    $tab_url = add_query_arg(array(
        'settings-updated' => false,
        'tab'              => 'licenses',
        'page'             => 'wp-sms-settings'
    ));
    $active  = isset($_GET['tab']) && $_GET['tab'] == 'licenses' ? 'active' : '';

    ?>

    <div class="wpsms-header-items-flex">
        <a class="documentation" target="_blank" href="<?php echo WP_SMS_SITE; ?>/documentation/"><span class="icon"></span><?php _e('Documentation', 'wp-sms'); ?></a>
        <a class="gateway" target="_blank" href="<?php echo WP_SMS_SITE; ?>/gateways/add-new/"><span class="icon"></span><?php _e('Gateway', 'wp-sms'); ?></a>
        <a class="zapier" target="_blank" href="<?php echo WP_SMS_SITE; ?>/zapier-integration"><span class="icon"></span><?php _e('Zapier Integration', 'wp-sms'); ?></a>
        <a class="licenses<?php echo ' ' . $active; ?>" href="<?php echo esc_url($tab_url); ?>"><span class="icon"></span>Licenses</a>
    </div>

    <?php if (!is_plugin_active('wp-sms-pro/wp-sms-pro.php')) : ?>
        <div class="license-status license-status--free">
            <a href="<?php echo WP_SMS_SITE; ?>/buy" target="_blank"><span><?php _e('Get more features!', 'wp-sms'); ?></a></span>
        </div>
    <?php elseif (isset($option['license_wp-sms-pro_status']) and $option['license_wp-sms-pro_status']) : ?>
        <div class="license-status license-status--valid">
            <span><?php _e('Your license is activated', 'wp-sms'); ?></span>
        </div>
    <?php else : ?>
        <div class="license-status license-status--invalid">
            <span><?php _e('Invalid license!', 'wp-sms'); ?></span>
        </div>
    <?php endif; ?>
</div>