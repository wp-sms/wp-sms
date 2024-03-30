<?php if (isset($addons)): ?>
    <?php if (count($addons) == 0): ?>
        <div class="license-status license-status--free">
            <a class="upgrade" href="<?php echo esc_url(WP_SMS_SITE . '/buy'); ?>" target="_blank"><span><?php echo esc_html__('UPGRADE TO PRO', 'wp-sms'); ?></span></a>
        </div>
    <?php else: ?>
        <div class="license-status license-status--valid">
            <span>
                <?php echo sprintf(esc_html__('License: %1$s/%2$s', 'wp-sms'), count(array_filter($addons)), count($addons)); ?>
                <a class="upgrade" href="<?php echo esc_url($tab_url); ?>"><?php echo esc_html__('MANAGE LICENSE', 'wp-sms'); ?></a>
            </span>
        </div>
    <?php endif; ?>
<?php endif; ?>
