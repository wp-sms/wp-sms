<script type="text/javascript">
    jQuery(document).ready(function () {
        if (jQuery('#wps-send-subscribe').val() == 'yes') {
            jQuery('#wpsms-select-subscriber-group').show();
            jQuery('#wpsms-custom-text').show();
        }

        jQuery("#wps-send-subscribe").change(function () {
            if (this.value == 'yes') {
                jQuery('#wpsms-select-subscriber-group').show();
                jQuery('#wpsms-custom-text').show();
            } else {
                jQuery('#wpsms-select-subscriber-group').hide();
                jQuery('#wpsms-custom-text').hide();
            }

        });
    })
</script>

<table class="form-table">
    <tr valign="top">
        <th scope="row">
            <label for="wps-send-subscribe"><?php _e('Send this post to subscribers?', 'wp-sms'); ?>:</label>
        </th>
        <td>
            <select name="wps_send_subscribe" id="wps-send-subscribe">
                <option value="0" selected><?php _e('Please select', 'wp-sms'); ?></option>
                <option value="yes"><?php _e('Yes'); ?></option>
                <option value="no"><?php _e('No'); ?></option>
            </select>
        </td>
    </tr>
    <tr valign="top" id="wpsms-select-subscriber-group">
        <th scope="row">
            <label for="wps-subscribe-group"><?php _e('Select the group', 'wp-sms'); ?>:</label>
        </th>
        <td>
            <select name="wps_subscribe_group" id="wps-subscribe-group">
                <option value="all"><?php echo sprintf(__('All (%s subscribers active)', 'wp-sms'), $username_active); ?></option>
                <?php foreach ($get_group_result as $items): ?>
                    <option value="<?php echo $items->ID; ?>"><?php echo $items->name; ?></option><?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr valign="top" id="wpsms-custom-text">
        <th scope="row">
            <label for="wpsms-text-template"><?php _e('Text template', 'wp-sms'); ?>:</label>
        </th>
        <td>
            <textarea cols="80" rows="5" id="wpsms-text-template" name="wpsms_text_template"><?php
                echo wp_sms_get_option('notif_publish_new_post_template'); ?></textarea>
            <p class="description data">
                <?php _e('Input data:', 'wp-sms'); ?>
                <br/><?php _e('Post title', 'wp-sms'); ?>: <code>%post_title%</code>
                <br/><?php _e('Post content', 'wp-sms'); ?>: <code>%post_content%</code>
                <br/><?php _e('Post url', 'wp-sms'); ?>: <code>%post_url%</code>
                <br/><?php _e('Post date', 'wp-sms'); ?>: <code>%post_date%</code>
            </p>
        </td>
    </tr>
</table>