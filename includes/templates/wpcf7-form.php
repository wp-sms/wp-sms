<div id="wpcf7-wpsms" class="contact-form-editor-wpsms">
    <h3><?php _e('SMS Recipient', 'wp-sms'); ?></h3>
    <fieldset>
        <legend><?php _e("After submitting the form you can send an SMS message to numbers or subscribers' group", 'wp-sms'); ?><br></legend>
        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row"><label for="wpcf7-sms-recipient"><?php _e('Recipients', 'wp-sms'); ?>:</label></th>
                <td>
                    <select name="wpcf7-sms[recipient]" id="wpcf7-sms-recipient">
                        <option value="number" <?php if (isset($cf7_options['recipient']) && $cf7_options['recipient'] == 'number'): echo 'selected'; endif; ?> > <?php _e('Number', 'wp-sms'); ?></option>
                        <option value="subscriber" <?php if (isset($cf7_options['recipient']) && $cf7_options['recipient'] == 'subscriber'): echo 'selected'; endif; ?> > <?php _e('Subscriber Group', 'wp-sms'); ?></option>
                    </select>
                </td>
            </tr>

            <tr id="wp-sms-recipient-numbers">
                <th scope="row"><label for="wpcf7-sms-recipient-number"><?php _e('Numbers', 'wp-sms'); ?>:</label></th>
                <td>
                    <input type="text" value="<?php echo $cf7_options['phone'] ?? ''; ?>" size="70" class="large-text code" name="wpcf7-sms[phone]" id="wpcf7-sms-recipient-number">
                    <p class="description"><?php _e('<b>Note:</b> When sending multiple numbers, please separate them with a comma. for example: 10000000001, 10000000002.', 'wp-sms'); ?></p>
                </td>
            </tr>

            <tr id="wp-sms-recipient-groups">
                <th scope="row"><label for="wpcf7-sms-recipient-subscriber"><?php _e('Subscriber Group', 'wp-sms'); ?>:</label></th>
                <td>
                    <div class="wpsms-value wpsms-group">
                        <select name="wpcf7-sms[groups][]" multiple="multiple" class="js-wpsms-select2" data-placeholder="<?php _e('Please select the Group', 'wp-sms'); ?>">
                            <?php
                            if (isset($get_group_result)):
                                foreach ($get_group_result as $items): ?>
                                    <option value="<?php echo $items->ID; ?>" <?php if (isset($cf7_options['groups']) && in_array($items->ID, $cf7_options['groups'])): echo 'selected'; endif; ?>>
                                        <?php echo sprintf(__('Group %s', 'wp-sms'), $items->name); ?>
                                    </option>
                                <?php
                                endforeach;
                            endif; ?>
                        </select>
                    </div>
                    <p class="description"><?php _e('<b>Note:</b> Multiple groups can be chosen.', 'wp-sms'); ?></p>
                </td>
            </tr>

            <tr id="wp-sms-cf7-message-body">
                <th scope="row"><label for="wpcf7-sms-message"><?php _e('Message body', 'wp-sms'); ?>:</label></th>
                <td>
                    <textarea class="large-text" rows="4" cols="100" name="wpcf7-sms[message]" id="wpcf7-sms-message"><?php echo $cf7_options['message'] ?? ''; ?></textarea>
                    <p class="description"><?php _e('<b>Note:</b> Use %% Instead of [], for example: %your-name%', 'wp-sms'); ?></p>
                </td>
            </tr>
        </table>

        <h3><?php _e('Send to form', 'wp-sms'); ?></h3>
        <legend><?php _e('After submitting the form, you have the option to send an SMS message to the specified field:', 'wp-sms'); ?><br></legend>
        <table class="form-table">
            <tr>
                <th scope="row"><label for="wpcf7-sms-sender-form"><?php _e('Send to field', 'wp-sms'); ?>:</label>
                </th>
                <td>
                    <input type="text" value="<?php echo $cf7_options_field['phone'] ?? ''; ?>" size="70" class="large-text code" name="wpcf7-sms-form[phone]" id="wpcf7-sms-sender-form">
                    <p class="description"><?php _e('<b>Note:</b> Use %% Instead of [], for example: %your-mobile%', 'wp-sms'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="wpcf7-sms-message-form"><?php _e('Message body', 'wp-sms'); ?>:</label>
                </th>
                <td>
                    <textarea class="large-text" rows="4" cols="100" name="wpcf7-sms-form[message]" id="wpcf7-sms-message-form"><?php echo $cf7_options_field['message'] ?? ''; ?></textarea>
                    <p class="description"><?php _e('<b>Note:</b> Use %% Instead of [], for example: %your-name%', 'wp-sms'); ?></p>
                </td>
            </tr>
            </tbody>
        </table>
    </fieldset>
</div>