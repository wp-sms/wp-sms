<?php
namespace WP_SMS;
?>
<div class="wrap wpsms-wrap">
    <?php echo Helper::loadTemplate('header.php'); ?>
    <div class="wpsms-wrap__main">
        <h2><?php _e('SMS Notification', 'wp-sms'); ?></h2>

        <form id="outbox-filter" method="get">
            <?php $_request_page = sanitize_text_field($_REQUEST['page']) ?>
            <input type="hidden" name="page" value="<?php echo esc_attr($_request_page); ?>"/>
            <?php $list_table->display(); ?>
        </form>
    </div>
</div>

