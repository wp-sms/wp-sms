<table class="form-table">
    <tr>
        <th><label for="wpsms-mobile"><?php _e('Mobile', 'wp-sms'); ?></label></th>
        <td>
            <?php wp_sms_render_mobile_field(['class' => ['regular-text'], 'name' => 'mobile', 'value' => isset($_POST['mobile']) ? sanitize_text_field($_POST['mobile']) : '']); ?>
            <span class="description"><?php _e('User mobile number.', 'wp-sms'); ?></span>
        </td>
    </tr>
</table>