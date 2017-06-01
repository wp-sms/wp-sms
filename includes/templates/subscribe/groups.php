<div class="wrap">
    <h2><?php _e( 'Groups', 'wp-sms' ); ?></h2>

    <div class="wpsms-button-group">
        <a href="admin.php?page=wp-sms-subscribers-group&action=add" class="button"><span
                    class="dashicons dashicons-groups"></span> <?php _e( 'Add Group', 'wp-sms' ); ?></a>
    </div>

    <form id="subscribers-filter" method="get">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
		<?php $list_table->search_box( __( 'Search', 'wp-sms' ), 'search_id' ); ?>
		<?php $list_table->display(); ?>
    </form>
</div>