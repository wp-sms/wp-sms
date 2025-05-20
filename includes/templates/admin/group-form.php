<form action="" method="post">
    <table>
        <tr>
            <td>
                <label for="wp_group_name" class="wp_sms_subscribers_label"><?php esc_html_e('Name', 'wp-sms'); ?><span class="wp-sms-required">*</span></label>
                <input type="text" id="wp_group_name" name="wp_group_name" placeholder="<?php esc_html_e('Name', 'wp-sms'); ?>" value="<?php echo isset($group_name) ? esc_attr($group_name) : ''; ?>" class="wp_sms_subscribers_input_text" required/>
                <?php if (isset($group_id)) : ?>
                    <input type="hidden" id="wp_group_name" name="group_id" placeholder="<?php esc_html_e('ID', 'wp-sms'); ?>" value="<?php echo esc_attr($group_id); ?>" class="wp_sms_subscribers_input_text"/>
                <?php endif; ?>
            </td>
        </tr>
        <tr>
            <td>
                <?php wp_nonce_field('wp_sms_group_action'); ?>
                <?php if (isset($group_id)) : ?>
                    <input type="submit" class="button-primary" name="wp_update_group" value="<?php esc_html_e('Update', 'wp-sms'); ?>"/>
                <?php else : ?>
                    <input type="submit" class="button-primary" name="wp_add_group" value="<?php esc_html_e('Add', 'wp-sms'); ?>"/>
                <?php endif; ?>
            </td>
        </tr>
    </table>
</form>