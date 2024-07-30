<div id="wpcf7-wpsms" class="contact-form-editor-wpsms">
    <h3><?php esc_html_e('SMS Recipient', 'wp-sms'); ?></h3>
    <fieldset>
        <legend><?php esc_html_e("After submitting the form you can send an SMS message to numbers or subscribers' group", 'wp-sms'); ?><br></legend>
        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row"><label for="wpcf7-sms-recipient"><?php esc_html_e('Recipients', 'wp-sms'); ?>:</label></th>
                <td>
                    <select name="wpcf7-sms[recipient]" id="wpcf7-sms-recipient">
                        <option value="number" <?php if (isset($cf7_options['recipient']) && $cf7_options['recipient'] == 'number') echo 'selected'; ?> > <?php esc_html_e('Number', 'wp-sms'); ?></option>
                        <option value="subscriber" <?php if (isset($cf7_options['recipient']) && $cf7_options['recipient'] == 'subscriber') echo 'selected'; ?> > <?php esc_html_e('Subscriber Group', 'wp-sms'); ?></option>
                    </select>
                </td>
            </tr>

            <tr id="wp-sms-recipient-numbers" class="js-wpsms-show_if_wpcf7-sms-recipient_equal_number">
                <th scope="row"><label for="wpcf7-sms-recipient-number"><?php esc_html_e('Numbers', 'wp-sms'); ?>:</label></th>
                <td>
                    <input type="text" value="<?php echo isset($cf7_options['phone']) ? esc_attr($cf7_options['phone']) : ''; ?>" size="70" class="large-text code" name="wpcf7-sms[phone]" id="wpcf7-sms-recipient-number">
                    <p class="description"><b><?php esc_html_e('Note: ', 'wp-sms') ?></b><?php esc_html_e('When sending multiple numbers, please separate them with a comma. for example: 10000000001, 10000000002.', 'wp-sms'); ?></p>
                </td>
            </tr>

            <tr id="wp-sms-recipient-groups" class="js-wpsms-show_if_wpcf7-sms-recipient_equal_subscriber">
                <th scope="row"><label for="wpcf7-sms-recipient-subscriber"><?php esc_html_e('Subscriber Group', 'wp-sms'); ?>:</label></th>
                <td>
                    <div class="wpsms-value wpsms-group">
                        <select name="wpcf7-sms[groups][]" multiple="multiple" class="js-wpsms-select2" data-placeholder="<?php esc_html_e('Please select the Group', 'wp-sms'); ?>">
                            <?php
                            if (isset($get_group_result)):
                                foreach ($get_group_result as $items): ?>
                                    <option value="<?php echo esc_attr($items->ID); ?>" <?php if (isset($cf7_options['groups']) && in_array($items->ID, $cf7_options['groups'])) echo 'selected'; ?>>
                                        <?php
                                            // translators: %s: Group name
                                            echo sprintf(esc_html__('Group %s', 'wp-sms'), esc_attr($items->name));
                                        ?>
                                    </option>
                                <?php
                                endforeach;
                            endif; ?>
                        </select>
                    </div>
                    <p class="description"><b><?php esc_html_e('Note: ', 'wp-sms') ?></b><?php esc_html_e('Multiple groups can be chosen.', 'wp-sms'); ?></p>
                </td>
            </tr>

            <tr id="wp-sms-cf7-message-body" class="js-wpsms-show_if_wpcf7-sms-recipient_equal_number js-wpsms-show_if_wpcf7-sms-recipient_equal_subscriber">
                <th scope="row"><label for="wpcf7-sms-message"><?php esc_html_e('Message body', 'wp-sms'); ?>:</label></th>
                <td>
                    <textarea class="large-text" rows="4" cols="100" name="wpcf7-sms[message]" id="wpcf7-sms-message"><?php echo isset($cf7_options['message']) ? esc_html($cf7_options['message']) : ''; ?></textarea>
                    <p class="description"><b><?php esc_html_e('Note: ', 'wp-sms') ?></b><?php esc_html_e('Use %% Instead of [], for example: ', 'wp-sms'); ?> <code>%your-mobile%</code><br>
                        <?php esc_html_e('You can also use the following contact form 7 tags in the message body:', 'wp-sms'); ?>
                        <code>%_site_title%</code> <code>%_site_url%</code> <code>%_post_name%</code> <code>%_post_url%</code> <code>%_post_title%</code> <code>%_post_id%</code>
                    </p>
                </td>
            </tr>
        </table>

        <h3><?php esc_html_e('Send to form', 'wp-sms'); ?></h3>
        <legend><?php esc_html_e('After submitting the form, you have the option to send an SMS message to the specified field:', 'wp-sms'); ?><br></legend>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="wpcf7-sms-sender-form"><?php esc_html_e('Send to field', 'wp-sms'); ?>:</label>
                </th>
                <td>
                    <input type="text" value="<?php echo isset($cf7_options_field['phone']) ? esc_attr($cf7_options_field['phone']) : ''; ?>" size="70" class="large-text code" name="wpcf7-sms-form[phone]" id="wpcf7-sms-sender-form">
                    <p class="description"><b><?php esc_html_e('Note: ', 'wp-sms') ?></b><?php esc_html_e('Use %% Instead of [], for example: ', 'wp-sms'); ?><code>%your-mobile%</code></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="wpcf7-sms-message-form"><?php esc_html_e('Message body', 'wp-sms'); ?>:</label>
                </th>
                <td>
                    <textarea class="large-text" rows="4" cols="100" name="wpcf7-sms-form[message]" id="wpcf7-sms-message-form"><?php echo isset($cf7_options_field['message']) ? esc_html($cf7_options_field['message']) : ''; ?></textarea>
                    <p class="description"><b><?php esc_html_e('Note: ', 'wp-sms') ?></b> <?php esc_html_e('Use %% Instead of [], for example: ', 'wp-sms'); ?><code>%your-mobile%</code><br>
                        <?php esc_html_e('You can also use the following contact form 7 tags in the message body:', 'wp-sms'); ?>
                        <code>%_site_title%</code> <code>%_site_url%</code> <code>%_post_name%</code> <code>%_post_url%</code> <code>%_post_title%</code> <code>%_post_id%</code>
                    </p>
                </td>
            </tr>
            </tbody>
        </table>
    </fieldset>
</div>
