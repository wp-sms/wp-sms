<div class="notice notice-<?php echo $type; ?> wpsms-admin-notice">
    <?php echo $message; ?>
    <?php if ($dismiss) : ?>
        <a href='<?php echo esc_url($link); ?>' class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></a>
    <?php endif; ?>
</div>