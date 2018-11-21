<div class="wrap">
    <h2><?php _e( 'Groups', 'wp-sms' ); ?></h2>

    <div class="wpsms-button-group">
	    <?php add_thickbox(); ?>
        <a href="admin.php?page=wp-sms-subscribers-group#TB_inline?&width=600&height=550&inlineId=add-group" class="thickbox button"><span
                    class="dashicons dashicons-groups"></span> <?php _e( 'Add Group', 'wp-sms' ); ?></a>
            <div id="add-group" style="display:none;">
            <form action="" method="post">
                <table>
                    <tr>
                        <td colspan="2"><h3><?php _e( 'Group Info:', 'wp-sms' ); ?></h3></td>
                    </tr>
                    <tr>
                        <td><span class="label_td" for="wp_group_name"><?php _e( 'Name', 'wp-sms' ); ?>:</span></td>
                        <td><input type="text" id="wp_group_name" name="wp_group_name"/></td>
                    </tr>

                    <tr>
                        <td colspan="2">
                            <a href="admin.php?page=wp-sms-subscribers-group" class="button"><?php _e( 'Back', 'wp-sms' ); ?></a>
                            <input type="submit" class="button-primary" name="wp_add_group"
                                   value="<?php _e( 'Add', 'wp-sms' ); ?>"/>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
        <div id="edit-group" style="display:none;">
            <form action="" method="post">
                <table>
                    <tr>
                        <td colspan="2"><h3><?php _e( 'Group Info:', 'wp-sms' ); ?></h3></td>
                    </tr>
                    <tr>
                        <td><span class="label_td" for="wp_group_name"><?php _e( 'Name', 'wp-sms' ); ?>:</span></td>
                        <td><input type="text" id="wp_group_name" name="wp_group_name" value="<?php echo $get_group->name; ?>"/>
                        </td>
                    </tr>

                    <tr>
                        <td colspan="2">
                            <a href="admin.php?page=wp-sms-subscribers-group" class="button"><?php _e( 'Back', 'wp-sms' ); ?></a>
                            <input type="submit" class="button-primary" name="wp_update_group"
                                   value="<?php _e( 'Add', 'wp-sms' ); ?>"/>
                        </td>
                    </tr>
                </table>
            </form>
        </div>
    </div>

    <form id="subscribers-filter" method="get">
        <input type="hidden" name="page" value="<?php echo $_REQUEST['page'] ?>"/>
		<?php $list_table->search_box( __( 'Search', 'wp-sms' ), 'search_id' ); ?>
		<?php $list_table->display(); ?>
    </form>
</div>