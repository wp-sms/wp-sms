<div class="wrap">
    <h2><?php _e('Outbox SMS', 'wp-sms'); ?></h2>

    <form id="outbox-filter" method="get">
        <?php $_request_page = sanitize_text_field($_REQUEST['page']) ?>
        <input type="hidden" name="page" value="<?php echo esc_attr($_request_page); ?>"/>
        <?php $list_table->search_box(__('Search', 'wp-sms'), 'search_id'); ?>
        <?php $list_table->display(); ?>
    </form>
</div>