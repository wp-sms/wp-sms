<?php if (isset($addons)): ?>
    <?php if (count($addons) == 0): ?>
        <div class="license-status license-status--free">
            <a class="upgrade" href="<?php echo esc_url(WP_SMS_SITE . '/buy?utm_source=wp-sms&utm_medium=link&utm_campaign=header'); ?>" target="_blank"><span><?php echo esc_html__('UPGRADE TO PRO', 'wp-sms'); ?></span></a>
        </div>
    <?php else: ?>
        <div class="license-status license-status--valid">
            <a class="upgrade" href="<?php echo esc_url($tab_url); ?>">
                <span>
                <?php echo sprintf(esc_html__('License: %1$s/%2$s', 'wp-sms'), count(array_filter($addons)), count($addons)); ?>
                    <span><?php echo esc_html__('MANAGE', 'wp-sms'); ?></span>
                </span>
            </a>
        </div>
    <?php endif; ?>
<?php endif; ?>
