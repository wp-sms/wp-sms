<table class="form-table">
    <!-- Send Message To -->
    <tr valign="top">
        <th scope="row">
            <label for="wps-send-to"><?php _e('Send Notification to?', 'wp-sms'); ?></label>
        </th>
        <td>
            <select name="wps_send_to" id="wps-send-to" class="<?php echo $forceToSend ? "is-forced" : '';  ?>">
                <option value="0" <?php if (isset($_GET['post']) and !$forceToSend): echo 'selected'; endif; ?>><?php _e('Please select', 'wp-sms'); ?></option>
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
    </tr>
    <!-- Select Subscriber Group -->
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
    <!-- Enter receiver number -->
    <tr valign="top" id="wpsms-select-numbers">
        <th scope="row">
            <label for="wps-mobile-numbers"><?php _e('Number(s)', 'wp-sms'); ?>:</label>
        </th>
        <td>
            <input type="text" name="wps_mobile_numbers" id="wps-mobile-numbers" class="regular-text" value="<?php echo wp_sms_get_option('notif_publish_new_post_numbers') ?>"/>
        </td>
    </tr>
    <!-- Select specific role -->
    <tr valign="top" id="wpsms-select-users">
        <th scope="row">
            <label for="wpsms_roles"><?php _e('Specific Roles', 'wp-sms'); ?>:</label>
        </th>
        <td>
            <div class="wpsms-value wpsms-users wpsms-users-roles">
                <select id="wpsms_roles" name="wpsms_roles[]" multiple="multiple" class="js-wpsms-select2" data-placeholder="<?php _e('Please select the Role', 'wp-sms'); ?>">
                    <?php
                    foreach ($wpsms_list_of_role as $key_item => $val_item):
                        ?>
                        <!--echo Roles-->
                        <option value="<?php echo $key_item; ?>"
                            <?php
                            if ($val_item['count'] < 1) {
                                echo " disabled";
                            } else {
                                if (!empty($selected_roles) and in_array(strtolower($val_item['name']), $selected_roles)) {
                                    echo 'selected';
                                }
                            }
                            ?>
                        >
                            <?php _e($val_item['name'], 'wp-sms'); ?>
                            (<?php echo sprintf(__('<b>%s</b> Users have mobile number.', 'wp-sms'), $val_item['count']); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </td>
    </tr>
    <!--Message Body-->
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

<script type="text/javascript">
    const { subscribe } = wp.data;

    const initialPostStatus = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'status' );

    if ( 'publish' !== initialPostStatus ) {
        jQuery('#wps-send-to:not(.is-forced)').val(0);
        jQuery('#wps-send-to:not(.is-forced)').change();
        showHideFields();
        const unssubscribe = subscribe( () => {
            const currentPostStatus = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'status' );
            if ( 'publish' === currentPostStatus ) {
                jQuery('#wps-send-to').val(0);
                jQuery('#wps-send-to').change();
                showHideFields();
            }
        } );
    }

    function showHideFields() {
        const sendTo = jQuery('#wps-send-to').val()
        if (sendTo == 'subscriber') {
            jQuery('#wpsms-select-subscriber-group').show();
            jQuery('#wpsms-select-numbers').hide();
            jQuery('#wpsms-select-users').hide();
            jQuery('#wpsms-custom-text').show();
        } else if (sendTo == 'numbers') {
            jQuery('#wpsms-select-subscriber-group').hide();
            jQuery('#wpsms-select-numbers').show();
            jQuery('#wpsms-select-users').hide();
            jQuery('#wpsms-custom-text').show();
        } else if (sendTo == 'users') {
            jQuery('#wpsms-select-subscriber-group').hide();
            jQuery('#wpsms-select-numbers').hide();
            jQuery('#wpsms-select-users').show();
            jQuery('#wpsms-custom-text').show();
        } else {
            jQuery('#wpsms-select-subscriber-group').hide();
            jQuery('#wpsms-select-numbers').hide();
            jQuery('#wpsms-select-users').hide();
            jQuery('#wpsms-custom-text').hide();
        }
    }

    jQuery("#wps-send-to").on('change', function () {
        showHideFields();
    });
</script>