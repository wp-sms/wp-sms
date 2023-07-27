<div class="notice notice-<?php echo esc_attr($type); ?> wpsms-admin-notice">
    <p><?php echo wp_kses_post($message); ?></p>
    <?php if ($dismiss) : ?>
        <a href='<?php echo esc_url($link); ?>' class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></a>
    <?php endif; ?>
</div>