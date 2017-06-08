<div class="wrap">
    <h2><?php _e( 'Edit Subscriber', 'wp-sms' ); ?></h2>
    <form action="" method="post">
        <table>
            <tr>
                <td colspan="2"><h3><?php _e( 'Subscriber Info:', 'wp-sms' ); ?></h3></td>
            </tr>
            <tr>
                <td><span class="label_td" for="wp_subscribe_name"><?php _e( 'Name', 'wp-sms' ); ?>:</span></td>
                <td><input type="text" id="wp_subscribe_name" name="wp_subscribe_name"
                           value="<?php echo $get_subscribe->name; ?>"/></td>
            </tr>

            <tr>
                <td><span class="label_td" for="wp_subscribe_mobile"><?php _e( 'Mobile', 'wp-sms' ); ?>:</span></td>
                <td><input type="text" name="wp_subscribe_mobile" id="wp_subscribe_mobile"
                           value="<?php echo $get_subscribe->mobile; ?>" class="code"/></td>
            </tr>

			<?php if ( $this->subscribe->get_groups() ): ?>
                <tr>
                    <td><span class="label_td" for="wpsms_group_name"><?php _e( 'Group', 'wp-sms' ); ?>:</span></td>
                    <td>
                        <select name="wpsms_group_name" id="wpsms_group_name">
							<?php foreach ( $this->subscribe->get_groups() as $items ): ?>
                                <option value="<?php echo $items->ID; ?>" <?php selected( $get_subscribe->group_ID, $items->ID ); ?>><?php echo $items->name; ?></option>
							<?php endforeach; ?>
                        </select>
                    </td>
                </tr>
			<?php else: ?>
                <tr>
                    <td><span class="label_td" for="wpsms_group_name"><?php _e( 'Group', 'wp-sms' ); ?>:</span></td>
                    <td><?php echo sprintf( __( 'There is no group! <a href="%s">Add</a>', 'wp-sms' ), 'admin.php?page=wp-sms-subscribers-group' ); ?></td>
                </tr>
			<?php endif; ?>

            <tr>
                <td><span class="label_td" for="wpsms_subscribe_status"><?php _e( 'Status', 'wp-sms' ); ?>:</span></td>
                <td>
                    <select name="wpsms_subscribe_status" id="wpsms_subscribe_status">
                        <option value="0" <?php selected( $get_subscribe->status, '0' ); ?>><?php _e( 'Deactive', 'wp-sms' ); ?></option>
                        <option value="1" <?php selected( $get_subscribe->status, '1' ); ?>><?php _e( 'Active', 'wp-sms' ); ?></option>
                    </select>
                </td>
            </tr>

            <tr>
                <td colspan="2">
                    <a href="admin.php?page=wp-sms-subscribers" class="button"><?php _e( 'Back', 'wp-sms' ); ?></a>
                    <input type="submit" class="button-primary" name="wp_update_subscribe"
                           value="<?php _e( 'Update', 'wp-sms' ); ?>"/>
                </td>
            </tr>
        </table>
    </form>
</div>