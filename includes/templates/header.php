<?php $option = get_option('wpsms_settings'); ?>
<div class="wpsms-header-banner">
    <?php if (!is_plugin_active('wp-sms-pro/wp-sms-pro.php')) : ?>
        <div class="license-status license-status--free">
            <h3><a href="<?php echo WP_SMS_SITE; ?>/buy" target="_blank"><?php _e('Get Pro Pack!', 'wp-sms'); ?></a></h3>
            <span><?php _e('You are using the free version, to enable the premium features, get the pro pack version.', 'wp-sms'); ?></span>
        </div>
    <?php elseif (isset($option['license_wp-sms-pro_status']) and $option['license_wp-sms-pro_status']) : ?>
        <div class="license-status license-status--valid">
            <h3><?php _e('Pro License', 'wp-sms'); ?></h3>
            <span><?php _e('Your license is enabled', 'wp-sms'); ?></span>
        </div>
    <?php else : ?>
        <div class="license-status license-status--invalid">
            <h3><?php _e('Pro License', 'wp-sms'); ?></h3>
            <span><?php _e('Your license is not enabled', 'wp-sms'); ?></span>
        </div>
    <?php endif; ?>
</div>
