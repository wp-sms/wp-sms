<div id="forminator-wpsms" class="contact-form-editor-wpsms">
    <div class="head-with-back">
        <h3><?php _e('SMS Recipient Form '. $form->name .' (' . $form->id . ')' , 'wp-sms'); ?> </h3>
        <a title="Back" href="<?= esc_url( remove_query_arg( 'form' ) ); ?>"><span class="dashicons dashicons-undo"></span> </a>
    </div>
    
    <form  method="post">
        <input name="submit_action" type="hidden" value="forminator_form_sms_data">
        <?php wp_nonce_field() ?>
        <fieldset>
            <legend><?php _e("After submitting the form you can send an SMS message to numbers or subscribers' group", 'wp-sms'); ?><br></legend>
            <table class="form-table">
                <tbody>
                <tr>
                    <th scope="row">
                        <label for="forminator-sms-recipient"><?php _e('Recipients', 'wp-sms'); ?>:</label>
                    </th>
                    <td>
                        <select name="forminator-sms[recipient]" id="forminator-sms-recipient">
                            <option value="number" <?php if (isset($sms_data['forminator-sms']['recipient']) && $sms_data['forminator-sms']['recipient'] == 'number'): echo 'selected'; endif; ?> > <?php _e('Number', 'wp-sms'); ?></option>
                            <option value="subscriber" <?php if (isset($sms_data['forminator-sms']['recipient']) && $sms_data['forminator-sms']['recipient'] == 'subscriber'): echo 'selected'; endif; ?> > <?php _e('Subscriber Group', 'wp-sms'); ?></option>
                        </select>
                    </td>
                </tr>
                <tr id="wp-sms-recipient-numbers" class="<?= $sms_data['forminator-sms']['recipient'] == 'subscriber' ? 'display-none' : '' ?>">
                    <th scope="row"><label for="forminator-sms-recipient-number"><?php _e('Numbers', 'wp-sms'); ?>:</label></th>
                    <td>
                        <input type="text" value="<?php echo $sms_data['forminator-sms']['phone'] ?? ''; ?>" size="70" class="large-text code" name="forminator-sms[phone]" id="forminator-sms-recipient-number">
                        <p class="description"><?php _e('<b>Note:</b> When sending multiple numbers, please separate them with a comma. for example: 10000000001, 10000000002.', 'wp-sms'); ?></p>
                    </td>
                </tr>

                <tr id="wp-sms-recipient-groups" class="<?= $sms_data['forminator-sms']['recipient'] == 'number' ? 'display-none' : '' ?>">
                    <th scope="row"><label for="forminator-sms-recipient-subscriber"><?php _e('Subscriber Group', 'wp-sms'); ?>:</label></th>
                    <td>
                        <div class="wpsms-value wpsms-group">
                            <select name="forminator-sms[groups][]" multiple="multiple" class="js-wpsms-select2" data-placeholder="<?php _e('Please select the Group', 'wp-sms'); ?>">
                                <?php
                                if (isset($get_group_result)):
                                    foreach ($get_group_result as $items): ?>
                                        <option value="<?php echo $items->ID; ?>" <?php if (isset($sms_data['forminator-sms']['groups']) && in_array($items->ID, $sms_data['forminator-sms']['groups'])): echo 'selected'; endif; ?>>
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
                    <th scope="row"><label for="forminator-sms-message"><?php _e('Message body', 'wp-sms'); ?>:</label></th>
                    <td>
                        <textarea class="large-text" rows="4" cols="100" name="forminator-sms[message]" id="forminator-sms-message"><?php echo $sms_data['forminator-sms']['message'] ?? ''; ?></textarea>
                        <p class="description"><?php _e('<b>Note:</b> Use %% Instead of [], for example: <code>%your-mobile%</code>', 'wp-sms'); ?><br>
                        </p>
                    </td>
                </tr>
            </table>

            <h3><?php _e('Send to form', 'wp-sms'); ?></h3>
            <legend><?php _e('After submitting the form, you have the option to send an SMS message to the specified field:', 'wp-sms'); ?><br></legend>
            <table class="form-table">
                <tr>
                    <th scope="row"><label for="forminator-sms-sender-form"><?php _e('Send to field', 'wp-sms'); ?>:</label>
                    </th>
                    <td>
                        <input type="text" value="<?php echo $sms_data['forminator-sms-from']['phone'] ?? ''; ?>" size="70" class="large-text code" name="forminator-sms-from[phone]" id="forminator-sms-sender-form">
                        <p class="description"><?php _e('<b>Note:</b> Use %% Instead of [], for example: <code>%your-mobile%</code>', 'wp-sms'); ?></p>
                    </td>
                </tr>

                <tr>
                    <th scope="row"><label for="forminator-sms-message-form"><?php _e('Message body', 'wp-sms'); ?>:</label>
                    </th>
                    <td>
                        <textarea class="large-text" rows="4" cols="100" name="forminator-sms-from[message]" id="forminator-sms-message-form"><?php echo $sms_data['forminator-sms-from']['message'] ?? ''; ?></textarea>
                        <p class="description"><?php _e('<b>Note:</b> Use %% Instead of [], for example: <code>%your-mobile%</code>', 'wp-sms'); ?><br>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="forminator-sms-message-form"></label>
                    </th>
                    <td>
                        <button type="submit" class="button button-primary">submit</button>
                    </td>
                </tr>
                </tbody>
            </table>
        </fieldset>
    </form>
</div>

<style>
.display-none{
    display: none;
}
.head-with-back
{
    display: flex;
    flex-direction: row;
    align-items: center;
    justify-content: space-between;
}
</style>

<script> 
        jQuery('#forminator-sms-recipient').on('change', function(e){
            var number = document.getElementById('wp-sms-recipient-numbers');   
            var subscriber = document.getElementById('wp-sms-recipient-groups');   
            if(this.value == 'subscriber') {
                number.style.display = "none";
                subscriber.style.display = "table-row";
            }
            if(this.value == 'number') {
                number.style.display = "table-row";
                subscriber.style.display = "none";
            }
            
        })
</script>