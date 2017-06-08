<div class="wrap">
    <h2><?php _e( 'Add Group', 'wp-sms' ); ?></h2>
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