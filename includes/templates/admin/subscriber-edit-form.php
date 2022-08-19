<form action="" method="post">
    <?php if (isset($subscriber_id)) : ?>
        <input type="hidden" name="ID" value="<?php echo $subscriber_id; ?>"/>
    <?php endif; ?>

    <table>
        <tr>
            <td style="padding-top: 10px;">
                <label for="wp_subscribe_name" class="wp_sms_subscribers_label"><?php _e('Name', 'wp-sms'); ?></label>
                <input type="text" id="wp_subscribe_name" name="wp_subscribe_name" value="<?php echo isset($subscriber->name) ? $subscriber->name : ''; ?>" class="wp_sms_subscribers_input_text"/>
            </td>
        </tr>
        <tr>
            <td style="padding-top: 10px;">
                <label for="wp_subscribe_mobile" class="wp_sms_subscribers_label"><?php _e('Mobile', 'wp-sms'); ?></label>
                <?php wp_sms_render_mobile_field(array('name' => 'wp_subscribe_mobile', 'class' => array('wp_sms_subscribers_input_text'), 'value' => isset($subscriber->mobile) ? $subscriber->mobile : '')); ?>
            </td>
        </tr>
        <?php if ($groups) : ?>
            <tr>
                <td style="padding-top: 10px;">
                    <label for="wpsms_group_name" class="wp_sms_subscribers_label"><?php _e('Group', 'wp-sms'); ?></label>
                    <select name="wpsms_group_name" id="wpsms_group_name" class="wp_sms_subscribers_input_text code">
                        <?php foreach ($groups as $items) : ?>
                            <option value="<?php echo esc_attr($items->ID); ?>" <?php if (isset($subscriber)): echo selected($subscriber->group_ID, $items->ID); endif; ?>><?php echo esc_attr($items->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        <?php else : ?>
            <tr>
                <td style="padding-top: 10px;">
                    <label for="wpsms_group_name" class="wp_sms_subscribers_label"><?php _e('Group', 'wp-sms'); ?></label>
                    <?php echo sprintf(__('There is no group! <a href="%s"> Add</a> ', 'wp-sms'), 'admin.php?page=wp-sms-subscribers-group'); ?>
                </td>
            </tr>
        <?php endif; ?>

        <tr>
            <td>
                <label for="wpsms_subscribe_status" class="wp_sms_subscribers_label"><?php _e('Status', 'wp-sms'); ?></label>
                <select name="wpsms_subscribe_status" id="wpsms_subscribe_status" class="wp_sms_subscribers_input_text code">';
                    <?php if (isset($subscriber)) : ?>
                        <option value="1" <?php selected($subscriber->status); ?>><?php _e('Active', 'wp-sms'); ?></option>
                        <option value="0" <?php selected($subscriber->status, false); ?>><?php _e('Deactivate', 'wp-sms'); ?></option>
                    <?php else : ?>
                        <option value="1" selected="selected"><?php _e('Active', 'wp-sms'); ?></option>
                        <option value="0"><?php _e('Deactivate', 'wp-sms'); ?></option>
                    <?php endif; ?>
                </select>
            </td>
        </tr>

        <tr>
            <td colspan="2" style="padding-top: 20px;">
                <?php if (isset($subscriber_id)) : ?>
                    <input type="submit" class="button-primary" name="wp_update_subscribe" value="<?php _e('Update', 'wp-sms'); ?>"/>
                <?php else : ?>
                    <input type="submit" class="button-primary" name="wp_add_subscribe" value="<?php _e('Add', 'wp-sms'); ?>"/>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</form>