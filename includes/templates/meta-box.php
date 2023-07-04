<table class="form-table send-sms-post">

    <!-- Send Message and Receiver Row -->
    <tr valign="top">
        <!-- Send Message To -->
        <td colspan="2">
            <label for="wps-send-to"><?php _e('To', 'wp-sms'); ?></label>
            <select name="wps_send_to" id="wps-send-to">
                <option value="0" <?php if (isset($_GET['post']) and !$forceToSend) : echo 'selected';
                endif; ?>><?php _e('Please select', 'wp-sms'); ?></option>
                <option value="subscriber" <?php if (empty($_GET['post']) and $forceToSend) {
                    selected(wp_sms_get_option('notif_publish_new_post_receiver') == 'subscriber');
                } ?>><?php _e('Subscribers'); ?></option>
                <option value="numbers" <?php if (empty($_GET['post']) and $forceToSend) {
                    selected(wp_sms_get_option('notif_publish_new_post_receiver') == 'numbers');
                } ?>><?php _e('Number(s)'); ?></option>
                <option value="users" <?php if (empty($_GET['post']) and $forceToSend) {
                    selected(wp_sms_get_option('notif_publish_new_post_receiver') == 'users');
                } ?>><?php _e('WordPress Users'); ?></option>
            </select>
        </td>

        <!-- Select Subscriber Group -->
        <td colspan="2" id="wpsms-select-subscriber-group">
            <label for="wps-subscribe-group"><?php _e('Subscribe group', 'wp-sms'); ?></label>
            <select name="wps_subscribe_group" id="wps-subscribe-group">
                <option value="all"><?php echo sprintf(__('All (%s subscribers active)', 'wp-sms'), $username_active); ?></option>
                <?php foreach ($get_group_result as $items) : ?>
                    <option value="<?php echo esc_attr($items->ID); ?>" <?php selected($defaultGroup, $items->ID); ?>><?php echo esc_attr($items->name); ?></option>
                <?php endforeach; ?>
            </select>
        </td>

        <!-- Enter receiver number -->
        <td colspan="2" id="wpsms-select-numbers">
            <label for="wps-mobile-numbers"><?php _e('Number(s)', 'wp-sms'); ?></label>
            <input placeholder="<?php _e('Separate numbers with commas', 'wp-sms'); ?>" type="text" name="wps_mobile_numbers" id="wps-mobile-numbers" class="regular-text" value="<?php echo wp_sms_get_option('notif_publish_new_post_numbers') ?>"/>

        </td>


        <!-- Select specific role -->
        <td colspan="2" id="wpsms-select-users">
            <label for="wpsms_roles"><?php _e('Specific roles', 'wp-sms'); ?></label>
            <div class="wpsms-value wpsms-users wpsms-users-roles">
                <select id="wpsms_roles" name="wpsms_roles[]" multiple="multiple" class="js-wpsms-select2" data-placeholder="<?php _e('Please select the Role', 'wp-sms'); ?>">
                    <?php
                    foreach ($wpsms_list_of_role as $key_item => $val_item) :
                        ?>
                        <!--echo Roles-->
                        <option value="<?php echo $key_item; ?>" <?php
                        if ($val_item['count'] < 1) {
                            echo " disabled";
                        } else {
                            if (!empty($selected_roles) and in_array(strtolower($val_item['name']), $selected_roles)) {
                                echo 'selected';
                            }
                        }
                        ?>>
                            <?php _e($val_item['name'], 'wp-sms'); ?>
                            (<?php echo sprintf(__('<b>%s</b> Users have mobile number.', 'wp-sms'), $val_item['count']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </td>

    </tr>

    <!-- Message Body and Short Codes Row -->
    <tr>
        <!--Message Body-->
        <td colspan="4">
            <label for="wpsms-text-template"><?php _e('Message body', 'wp-sms'); ?></label>
            <textarea cols="60" rows="5" id="wpsms-text-template" name="wpsms_text_template"><?php echo wp_sms_get_option('notif_publish_new_post_template'); ?></textarea>
        </td>
        <td colspan="1" class="short-codes" id="wpsms-short-codes">
            <div class="sms-shortcode-label"><?php _e('Short codes', 'wp-sms'); ?></div>
            <p class="description data">
                <?php
                echo \WP_SMS\Notification\NotificationFactory::getPost()->printVariables(); ?>
            </p>
        </td>
    </tr>

</table>