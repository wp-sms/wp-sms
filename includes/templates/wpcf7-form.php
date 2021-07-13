<div id="wpcf7-wpsms" class="contact-form-editor-wpsms">
    <h3><?php _e('Send to number', 'wp-sms'); ?></h3>
    <fieldset>
        <legend><?php _e('After submit form you can send a sms message to number', 'wp-sms'); ?><br></legend>
        <table class="form-table">
            <tbody>
            <tr>
                <th scope="row"><label for="wpcf7-sms-sender"><?php _e('Send to', 'wp-sms'); ?>:</label></th>
                <td>
                    <input type="text" value="<?php echo $cf7_options['phone'] ?? ''; ?>" size="70" class="large-text code" name="wpcf7-sms[phone]" id="wpcf7-sms-sender">
                    <p class="description"><?php _e('<b>Note:</b> To send more than one number, separate the numbers with a comma. (e.g. 10000000001,10000000002)', 'wp-sms'); ?></p>
                </td>
            </tr>

            <tr>
                <th scope="row"><label for="wpcf7-sms-message"><?php _e('Message body', 'wp-sms'); ?>:</label></th>
                <td>
                    <textarea class="large-text" rows="4" cols="100" name="wpcf7-sms[message]" id="wpcf7-sms-message"><?php echo $cf7_options['message'] ?? ''; ?></textarea>
                    <p class="description"><?php _e('<b>Note:</b> Use %% Instead of [], for example: %your-name%', 'wp-sms'); ?></p>
                </td>
            </tr>
        </table>

        <h3><?php _e('Send to form', 'wp-sms'); ?></h3>
        <legend><?php _e('After submit form you can send a sms message to field', 'wp-sms'); ?><br></legend>
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