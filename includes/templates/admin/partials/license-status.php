<?php if (isset($addons)) {
    if (count($addons) == 0) {
        echo '<div class="license-status license-status--free">';
        echo '<a class="upgrade" href="' . esc_url(WP_SMS_SITE . '/buy') . '" target="_blank"><span>' . esc_html__('UPGRADE TO PRO', 'wp-sms') . '</span></a>';
    } else {
        echo '<div class="license-status license-status--valid">';
        echo '<span>';
        echo sprintf(esc_html__('License: %1$s/%2$s', 'wp-sms'), count(array_filter($addons)), count($addons));
        echo '<a class="upgrade" target="_blank" href="' . esc_url(WP_SMS_SITE . '/buy') . '">' . esc_html__('UPGRADE', 'wp-sms') . '</a>';
        echo '</span>';
    }
    echo '</div>';
}
