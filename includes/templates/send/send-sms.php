<script type="text/javascript">
    jQuery(document).ready(function () {
        jQuery(".wpsms-value").hide();
        jQuery(".wpsms-group").show();

        jQuery("select#select_sender").change(function () {
            var get_method = "";
            jQuery("select#select_sender option:selected").each(
                function () {
                    get_method += jQuery(this).attr('id');
                }
            );
            if (get_method == 'wp_subscribe_username') {
                jQuery(".wpsms-value").hide();
                jQuery(".wpsms-group").fadeIn();
            } else if (get_method == 'wp_users') {
                jQuery(".wpsms-value").hide();
                jQuery(".wpsms-users").fadeIn();
            } else if (get_method == 'wp_tellephone') {
                jQuery(".wpsms-value").hide();
                jQuery(".wpsms-numbers").fadeIn();
                jQuery("#wp_get_number").focus();
            } else if (get_method == 'wp_role') {
                jQuery(".wpsms-value").hide();
                jQuery(".wprole-group").fadeIn();
            }
        });

        jQuery("#wp_get_message").counter({
            count: 'up',
            goal: 'sky',
            msg: '<?php _e('characters', 'wp-sms'); ?>'
        })
    });
</script>

<div class="wrap">
    <h2><?php _e( 'Send SMS', 'wp-sms' ); ?></h2>
    <form method="post" action="">
        <?php wp_nonce_field( 'update-options' ); ?>
        <table class="form-table">
            <tr>
                <td><?php _e( 'Send from', 'wp-sms' ); ?>:</td>
                <td>
                    <input type="text" name="wp_get_sender" id="wp_get_sender" value="<?php echo $this->sms->from; ?>" maxlength="11"/>
                </td>
            </tr>
            <tr>
                <td><?php _e( 'Send to', 'wp-sms' ); ?>:</td>
                <td>
                    <select name="wp_send_to" id="select_sender">
                        <option value="wp_subscribe_username"
                                id="wp_subscribe_username"><?php _e( 'Subscribe users', 'wp-sms' ); ?></option>
                        <option value="wp_users" id="wp_users"><?php _e( 'Wordpress Users', 'wp-sms' ); ?></option>
                        <option value="wp_role" id="wp_role"<?php if(empty($wpsms_option['add_mobile_field']) or $wpsms_option['add_mobile_field'] !=1) { echo 'disabled title="'.__( 'To enable this item, you should enable the Mobile number field in the Settings > Features', 'wp-sms' ).'"';   } ?>><?php _e( 'Role', 'wp-sms' ); ?></option>
                        <option value="wp_tellephone" id="wp_tellephone"><?php _e( 'Number(s)', 'wp-sms' ); ?></option>
                    </select>

                    <?php if(!empty($wpsms_option['add_mobile_field']) or $wpsms_option['add_mobile_field'] ==1) { ?>
                    <select name="wpsms_group_role" class="wpsms-value wprole-group">
                        <?php
                        foreach ( $wpsms_list_of_role as $key_item => $val_item ):
                        ?>
                            <option value="<?php echo $key_item; ?>"<?php if($val_item['count'] <1) { echo " disabled"; } ?>><?php _e( $val_item['name'] , 'wp-sms' ); ?> (<?php echo sprintf( __( '<b>%s</b> Users have mobile number.', 'wp-sms' ), $val_item['count'] ); ?>)</option>
                        <?php endforeach; ?>
                    </select>
                    <?php } ?>

                    <select name="wpsms_group_name" class="wpsms-value wpsms-group">
                        <option value="all">
							<?php
							global $wpdb, $table_prefix;
							$username_active = $wpdb->query( "SELECT * FROM {$table_prefix}sms_subscribes WHERE status = '1'" );
							echo sprintf( __( 'All (%s subscribers active)', 'wp-sms' ), $username_active );
							?>
                        </option>
						<?php foreach ( $get_group_result as $items ): ?>
                            <option value="<?php echo $items->ID; ?>"><?php echo $items->name; ?></option>
						<?php endforeach; ?>
                    </select>

                    <span class="wpsms-value wpsms-users">
						<span><?php echo sprintf( __( '<b>%s</b> Users have mobile number.', 'wp-sms' ), count( $get_users_mobile ) ); ?></span>
					</span>

                    <span class="wpsms-value wpsms-numbers">
                        <span style="font-size: 10px"><?php echo sprintf( __( 'For example: <code>%s</code>', 'wp-sms' ), $this->sms->validateNumber ); ?></span>
                        <div class="clearfix"></div>
                        <textarea cols="80" rows="2" style="direction:ltr;" id="wp_get_number" name="wp_get_number"></textarea>
                    </span>
                </td>
            </tr>

			<?php if ( ! $this->sms->bulk_send ) : ?>
                <tr>
                    <td></td>
                    <td><?php _e( 'This gateway does not support sending bulk message and used first number to sending sms.', 'wp-sms' ); ?></td>
                </tr>
			<?php endif; ?>

            <tr>
                <td><?php _e( 'Message', 'wp-sms' ); ?>:</td>
                <td>
                    <textarea dir="auto" cols="80" rows="5" name="wp_get_message" id="wp_get_message"></textarea><br/>
                    <p class="number">
						<?php echo __( 'Your account credit', 'wp-sms' ) . ': ' . WP_SMS_Gateway::credit(); ?>
                    </p>
                </td>
            </tr>
			<?php if ( $this->sms->flash == "enable" ) { ?>
                <tr>
                    <td><?php _e( 'Send a Flash', 'wp-sms' ); ?>:</td>
                    <td>
                        <input type="radio" id="flash_yes" name="wp_flash" value="true"/>
                        <label for="flash_yes"><?php _e( 'Yes', 'wp-sms' ); ?></label>
                        <input type="radio" id="flash_no" name="wp_flash" value="false" checked="checked"/>
                        <label for="flash_no"><?php _e( 'No', 'wp-sms' ); ?></label>
                        <br/>
                        <p class="description"><?php _e( 'Flash is possible to send messages without being asked, opens', 'wp-sms' ); ?></p>
                    </td>
                </tr>
			<?php } ?>
            <tr>
                <td>
                    <p class="submit">
                        <input type="submit" class="button-primary" name="SendSMS"
                               value="<?php _e( 'Send SMS', 'wp-sms' ); ?>"/>
                    </p>
                </td>
            </tr>
        </table>
    </form>
</div>