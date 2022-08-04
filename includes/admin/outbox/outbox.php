<?php
namespace WP_SMS;
?>

<div class="wrap wpsms-wrap">
    <?php require_once WP_SMS_DIR . 'includes/templates/header.php'; ?>
    <div class="wpsms-wrap__main">
        <h2><?php _e('Outbox SMS', 'wp-sms'); ?></h2>

        <?php
        echo Helper::loadTemplate('admin/quick-reply.php', array('reload' => 'true'));
        ?>

        <form id="outbox-filter" method="get">
            <?php $_request_page = sanitize_text_field($_REQUEST['page']) ?>
            <input type="hidden" name="page" value="<?php echo esc_attr($_request_page); ?>"/>
            <?php $list_table->search_box(__('Search', 'wp-sms'), 'search_id'); ?>
            <?php $list_table->display(); ?>
        </form>
    </div>
</div>
