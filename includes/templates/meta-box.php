<script type="text/javascript">
    function showHideFields() {
        const sendTo = jQuery('#wps-send-to').val()
        if (sendTo == 'subscriber') {
            jQuery('#wpsms-select-numbers').hide();
            jQuery('#wpsms-select-subscriber-group').show();
            jQuery('#wpsms-custom-text').show();
        } else if (sendTo == 'numbers') {
            jQuery('#wpsms-select-subscriber-group').hide();
            jQuery('#wpsms-select-numbers').show();
            jQuery('#wpsms-custom-text').show();
        } else {
            jQuery('#wpsms-select-subscriber-group').hide();
            jQuery('#wpsms-select-numbers').hide();
            jQuery('#wpsms-custom-text').hide();
        }
    }

    jQuery(document).ready(function () {
        showHideFields();

        jQuery("#wps-send-to").change(function () {
            showHideFields();
        });
    })
</script>

<table class="form-table">
    <tr valign="top">
        <th scope="row">
            <label for="wps-send-to"><?php _e('Send Notification to?', 'wp-sms'); ?></label>
        </th>
        <td>
            <select name="wps_send_to" id="wps-send-to">
                <option value="0" <?php if (!$forceToSend): echo 'selected'; endif; ?>><?php _e('Please select', 'wp-sms'); ?></option>
                <option value="subscriber" <?php if ($forceToSend) { selected(wp_sms_get_option('notif_publish_new_post_receiver') == 'subscriber'); } ?>><?php _e('Subscribers'); ?></option>
                <option value="numbers" <?php if ($forceToSend) { selected(wp_sms_get_option('notif_publish_new_post_receiver') == 'numbers'); } ?>><?php _e('Number(s)'); ?></option>
            </select>
        </td>
    </tr>
    <tr valign="top" id="wpsms-select-subscriber-group">
        <th scope="row">
            <label for="wps-subscribe-group"><?php _e('Subscribe group', 'wp-sms'); ?>:</label>
        </th>
        <td>
            <select name="wps_subscribe_group" id="wps-subscribe-group">
                <option value="all"><?php echo sprintf(__('All (%s subscribers active)', 'wp-sms'), $username_active); ?></option>
                <?php foreach ($get_group_result as $items): ?>
                    <option value="<?php echo esc_attr($items->ID); ?>" <?php selected($defaultGroup, $items->ID); ?>><?php echo esc_attr($items->name); ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr valign="top" id="wpsms-select-numbers">
        <th scope="row">
            <label for="wps-mobile-numbers"><?php _e('Number(s)', 'wp-sms'); ?>:</label>
        </th>
        <td>
            <input type="text" name="wps_mobile_numbers" id="wps-mobile-numbers" class="regular-text" value="<?php echo wp_sms_get_option('notif_publish_new_post_numbers') ?>"/>
        </td>
    </tr>
    <tr valign="top" id="wpsms-custom-text">
        <th scope="row">
            <label for="wpsms-text-template"><?php _e('Message body', 'wp-sms'); ?>:</label>
        </th>
        <td>
            <textarea cols="80" rows="5" id="wpsms-text-template" name="wpsms_text_template"><?php echo wp_sms_get_option('notif_publish_new_post_template'); ?></textarea>
            <p class="description data">
                <?php _e('Input data:', 'wp-sms'); ?>
                <br/><?php _e('Post title', 'wp-sms'); ?>: <code>%post_title%</code>
                <br/><?php _e('Post content', 'wp-sms'); ?>: <code>%post_content%</code>
                <br/><?php _e('Post url', 'wp-sms'); ?>: <code>%post_url%</code>
                <br/><?php _e('Post date', 'wp-sms'); ?>: <code>%post_date%</code>
                <br/><?php _e('Post thumbnail URL', 'wp-sms'); ?>: <code>%post_thumbnail%</code>
            </p>
        </td>
    </tr>
</table>