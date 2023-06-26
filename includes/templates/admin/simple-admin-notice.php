<div class="notice notice-warning wpsms-simple_admin_notice">
    <?php echo $message; ?>
    <?php if ($dismiss) : ?>
        <a href='<?php echo admin_url("admin.php?page=wp-sms-settings&action=wpsms-hide-notice&name=$name&security=$nonce"); ?>' class="notice-dismiss"><span class="screen-reader-text">Dismiss this notice.</span></a>
    <?php endif; ?>
</div>