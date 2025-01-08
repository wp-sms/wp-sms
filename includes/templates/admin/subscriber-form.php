<form action="" method="post">
    <?php if (isset($subscriber_id)) : ?>
        <input type="hidden" name="ID" value="<?php echo esc_attr($subscriber_id); ?>"/>
    <?php endif; ?>

    <table>
        <tr>
            <td style="padding-top: 10px;">
                <label for="wp_subscribe_name" class="wp_sms_subscribers_label"><?php esc_html_e('Name', 'wp-sms'); ?></label>
                <input type="text" id="wp_subscribe_name" name="wp_subscribe_name" value="<?php echo isset($subscriber->name) ? esc_attr($subscriber->name) : ''; ?>" class="wp_sms_subscribers_input_text"/>
            </td>
        </tr>
        <tr>
            <td style="padding-top: 10px;">
                <label for="wp_subscribe_mobile" class="wp_sms_subscribers_label"><?php esc_html_e('Mobile', 'wp-sms'); ?></label>
                <?php wp_sms_render_mobile_field(array('name' => 'wp_subscribe_mobile', 'class' => array('wp_sms_subscribers_input_text'), 'value' => isset($subscriber->mobile) ? esc_attr($subscriber->mobile) : '')); ?>
            </td>
        </tr>
        <?php
        // groups field does not need to be multiple in edit form.
        if ($groups) : ?>
            <tr>
                <td style="padding-top: 10px;">
                    <label for="wpsms_group_name" class="wp_sms_subscribers_label"><?php esc_html_e('Group', 'wp-sms'); ?></label>
                    <select
                        name="<?php echo isset($subscriber_id) ? 'wpsms_group_name' : 'wpsms_group_name[]'; ?>"
                        id="wpsms_group_name"
                        class="wp_sms_subscribers_input_text code"
                        <?php echo isset($subscriber_id) ? '' : 'multiple="multiple"'; ?>
                        style="<?php echo isset($subscriber_id) ? '' : 'height: 100px;'; ?>">
                        <?php if (isset($subscriber_id)) : ?>
                            <option value="" selected><?php esc_html_e('Select group', 'wp-sms'); ?></option>
                        <?php endif; ?>
                        <?php foreach ($groups as $items) : ?>
                            <option value="<?php echo esc_attr($items->ID); ?>" <?php if (isset($subscriber)): echo selected($subscriber->group_ID, $items->ID); endif; ?>>
                                <?php echo esc_attr($items->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        <?php else : ?>
            <tr>
                <td style="padding-top: 10px;">
                    <label for="wpsms_group_name" class="wp_sms_subscribers_label"><?php esc_html_e('Group', 'wp-sms'); ?></label>
                    <?php esc_html_e('There is no group!', 'wp-sms'); ?>
                    <a href="admin.php?page=wp-sms-subscribers-group"><?php esc_html_e('Add', 'wp-sms') ?></a>
                </td>
            </tr>
        <?php endif; ?>

        <tr>
            <td>
                <label for="wpsms_subscribe_status" class="wp_sms_subscribers_label"><?php esc_html_e('Status', 'wp-sms'); ?></label>
                <select name="wpsms_subscribe_status" id="wpsms_subscribe_status" class="wp_sms_subscribers_input_text code">';
                    <?php if (isset($subscriber)) : ?>
                        <option value="1" <?php selected($subscriber->status, 1); ?>><?php esc_html_e('Active', 'wp-sms'); ?></option>
                        <option value="0" <?php selected($subscriber->status, 0); ?>><?php esc_html_e('Deactivate', 'wp-sms'); ?></option>
                    <?php else : ?>
                        <option value="1" selected="selected"><?php esc_html_e('Active', 'wp-sms'); ?></option>
                        <option value="0"><?php esc_html_e('Deactivate', 'wp-sms'); ?></option>
                    <?php endif; ?>
                </select>
            </td>
        </tr>

        <tr>
            <td colspan="2" style="padding-top: 20px;">
                <?php wp_nonce_field('wp_sms_subscriber_action'); ?>
                <?php if (isset($subscriber_id)) : ?>
                    <input type="submit" class="button-primary" name="wp_update_subscribe" value="<?php esc_html_e('Update', 'wp-sms'); ?>"/>
                <?php else : ?>
                    <input type="submit" class="button-primary" name="wp_add_subscribe" value="<?php esc_html_e('Add', 'wp-sms'); ?>"/>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</form>
