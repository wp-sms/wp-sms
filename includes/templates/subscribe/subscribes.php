<div class="wrap">
    <h2><?php _e( 'Subscribers', 'wp-sms' ); ?></h2>

    <div class="wpsms-button-group">
        <a href="admin.php?page=wp-sms-subscribers&action=add" class="button"><span
                    class="dashicons dashicons-admin-users"></span> <?php _e( 'Add Subscribe', 'wp-sms' ); ?></a>
        <a href="admin.php?page=wp-sms-subscribers-group" class="button"><span
                    class="dashicons dashicons-category"></span> <?php _e( 'Manage Group', 'wp-sms' ); ?></a>
        <a href="admin.php?page=wp-sms-subscribers&action=import" class="button"><span
                    class="dashicons dashicons-undo"></span> <?php _e( 'Import', 'wp-sms' ); ?></a>
        <a href="admin.php?page=wp-sms-subscribers&action=export" class="button"><span
                    class="dashicons dashicons-redo"></span> <?php _e( 'Export', 'wp-sms' ); ?></a>
    </div>

    <form id="subscribers-filter" method="get">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
		<?php $list_table->search_box( __( 'Search', 'wp-sms' ), 'search_id' ); ?>
		<?php $list_table->display(); ?>
    </form>
</div>