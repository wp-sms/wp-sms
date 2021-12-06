<script type="text/javascript">
    jQuery(document).ready(function () {
        jQuery(".wpsms-value").hide();
        jQuery(".wpsms-group").show();

        jQuery("select#select_sender").change(function () {
            var get_method = "";
            jQuery("select#select_sender option:selected").each(
                function () {
                    get_method += jQuery(this).attr('id');
                }
            );
            if (get_method == 'wp_subscribe_username') {
                jQuery(".wpsms-value").hide();
                jQuery(".wpsms-group").fadeIn();
            } else if (get_method == 'wp_users') {
                jQuery(".wpsms-value").hide();
                jQuery(".wpsms-users").fadeIn();
            } else if (get_method == 'wc_users') {
                jQuery(".wpsms-value").hide();
                jQuery(".wpsms-wc-users").fadeIn();
            } else if (get_method == 'bp_users') {
                jQuery(".wpsms-value").hide();
                jQuery(".wpsms-bp-users").fadeIn();
            } else if (get_method == 'wp_tellephone') {
                jQuery(".wpsms-value").hide();
                jQuery(".wpsms-numbers").fadeIn();
                jQuery("#wp_get_number").focus();
            } else if (get_method == 'wp_role') {
                jQuery(".wpsms-value").hide();
                jQuery(".wprole-group").fadeIn();
            }
        });

        jQuery("#wp_get_message").counter({
            count: 'up',
            goal: 'sky',
            msg: '<?php _e('characters', 'wp-sms'); ?>'
        });
        <?php if(\WP_SMS\Version::pro_is_active()) :?>
        jQuery("#datepicker").flatpickr({
            enableTime: true,
            dateFormat: "Y-m-d H:i:00",
            time_24hr: true,
            minuteIncrement: "10",
            minDate: "<?= current_datetime()->format("Y-m-d H:i:00"); ?>",
            disableMobile: true,
            defaultDate: "<?= current_datetime()->format("Y-m-d H:i:00"); ?>"
        });

        jQuery("#schedule_status").change(function () {
            if (jQuery(this).is(":checked")) {
                jQuery('#schedule_date').show();
            } else {
                jQuery('#schedule_date').hide();
            }
        });
        <?php endif; ?>
    });
</script>
<div class="wrap wpsms-wrap">
    <?php require_once WP_SMS_DIR . 'includes/templates/header.php'; ?>
    <div class="wpsms-wrap__main">
        <h2><?php _e('Send SMS', 'wp-sms'); ?></h2>
        <div class="postbox-container wpsms-sendsms__container" style="padding-top: 20px;">
            <div class="meta-box-sortables">
                <div class="postbox">
                    <h2 class="hndle" style="cursor: default;padding: 25px 18px 12px 20px; font-size: 20px;">
                        <span><?php _e('Send SMS form', 'wp-sms'); ?></span>
                    </h2>
                    <div class="inside">
                        <form method="post" action="">
                            <?php wp_nonce_field('update-options'); ?>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row">
                                        <label for="wp_get_sender"><?php _e('From', 'wp-sms'); ?>:</label>
                                    </th>
                                    <td>
                                        <input type="text" name="wp_get_sender" id="wp_get_sender" value="<?php echo $this->sms->from; ?>" maxlength="18"/>
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">
                                        <label for="select_sender"><?php _e('To', 'wp-sms'); ?>:</label>
                                    </th>
                                    <td>
                                        <select name="wp_send_to" id="select_sender">
                                            <option value="wp_subscribe_username" id="wp_subscribe_username"><?php _e('Subscribers', 'wp-sms'); ?></option>
                                            <option value="wp_users" id="wp_users"><?php _e('WordPress\'s Users', 'wp-sms'); ?></option>
                                            <option value="wc_users" id="wc_users"<?php disabled(!$proIsActive); ?>>
                                                <?php _e('WooCommerce\'s Customers', 'wp-sms'); ?>
                                                <?php if (!$proIsActive) : ?>
                                                    <span>(<?php _e('Requires the Pro Pack', 'wp-sms'); ?>)</span>
                                                <?php endif; ?>
                                            </option>
                                            <option value="bp_users" id="bp_users"<?php disabled(!$proIsActive); ?>>
                                                <?php _e('BuddyPress\'s Users', 'wp-sms'); ?>
                                                <?php if (!$proIsActive) : ?>
                                                    <span>(<?php _e('Requires the Pro Pack', 'wp-sms'); ?>)</span>
                                                <?php endif; ?>
                                            </option>
                                            <option value="wp_role" id="wp_role"><?php _e('Role', 'wp-sms'); ?></option>
                                            <option value="wp_tellephone" id="wp_tellephone"><?php _e('Number(s)', 'wp-sms'); ?></option>
                                        </select>

                                        <select name="wpsms_group_role" class="wpsms-value wprole-group">
                                            <?php
                                            foreach ($wpsms_list_of_role as $key_item => $val_item):
                                                ?>
                                                <option value="<?php echo $key_item; ?>"<?php if ($val_item['count'] < 1) {
                                                    echo " disabled";
                                                } ?>><?php _e($val_item['name'], 'wp-sms'); ?>
                                                    (<?php echo sprintf(__('<b>%s</b> Users have mobile number.', 'wp-sms'), $val_item['count']); ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>

                                        <?php if (count($get_group_result)) : ?>
                                            <div class="wpsms-value wpsms-group">
                                                <select name="wpsms_groups[]" multiple="true" class="js-wpsms-select2">
                                                    <?php foreach ($get_group_result as $items): ?>
                                                        <option value="<?php echo $items->ID; ?>"><?php echo sprintf(__('Group %s', 'wp-sms'), $items->name); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                        <?php else: ?>
                                            <span class="wpsms-value wpsms-group" style="display: none;">
                                                <span>
                                                    <?php
                                                    global $wpdb;
                                                    $username_active = $wpdb->query("SELECT * FROM {$wpdb->prefix}sms_subscribes WHERE status = '1'");
                                                    echo sprintf(__('<b>%s</b> Subscribers.', 'wp-sms'), $username_active);
                                                    ?>
                                                </span>
                                            </span>
                                        <?php endif; ?>

                                        <span class="wpsms-value wpsms-users" style="display: none;">
                                            <span><?php echo sprintf(__('<b>%s</b> Users have the mobile number.', 'wp-sms'), count($get_users_mobile)); ?></span>
                                        </span>

                                        <span class="wpsms-value wpsms-wc-users" style="display: none;">
                                            <span><?php echo sprintf(__('<b>%s</b> Customers have the mobile number.', 'wp-sms'), count($woocommerceCustomers)); ?></span>
                                        </span>

                                        <span class="wpsms-value wpsms-bp-users" style="display: none;">
                                            <span><?php echo sprintf(__('<b>%s</b> Users have the mobile number in their profile.', 'wp-sms'), count($buddyPressMobileNumbers)); ?></span>
                                        </span>

                                        <span class="wpsms-value wpsms-numbers">
                                            <div class="clearfix"></div>
                                            <textarea cols="80" rows="5" style="direction:ltr;margin-top: 10px;" id="wp_get_number" name="wp_get_number"></textarea>
                                            <div class="clearfix"></div>
                                            <div style="font-size: 14px"><?php _e('Separate the numbers with comma (,) or enter in each lines.', 'wp-sms'); ?></div>
                                            <?php if ($this->sms->validateNumber) : ?>
                                                <div style="margin-top: 10px"><?php echo sprintf(__('Gateway description: <code>%s</code>', 'wp-sms'), $this->sms->validateNumber); ?></div>
                                            <?php endif; ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php if (!$this->sms->bulk_send) : ?>
                                    <tr>
                                        <td></td>
                                        <td><?php _e('This gateway doesn\'t support the bulk SMS and will use the first number while sending a group of numbers.', 'wp-sms'); ?></td>
                                    </tr>
                                <?php endif; ?>
                                <tr valign="top">
                                    <th scope="row">
                                        <label for="wp_get_message"><?php _e('Message', 'wp-sms'); ?>:</label>
                                    </th>
                                    <td>
                                        <textarea dir="auto" cols="80" rows="5" name="wp_get_message" id="wp_get_message"></textarea><br/>
                                        <p class="number">
                                            <?php echo __('Your account credit', 'wp-sms') . ': ' . $credit; ?>
                                        </p>
                                    </td>
                                </tr>

                                <tr>
                                    <th><?php _e('Choice MMS media', 'wp-sms'); ?></th>
                                    <td>
                                        <?php if ($this->sms->supportMedia) : ?>
                                            <div><a href="#" class="wpsms-upload-button button">Upload image</a></div>
                                            <div style="margin-top: 11px;"><a href="#" class="wpsms-remove-button button" style="display:none">Remove image</a></div><input type="hidden" class="wpsms-mms-image" name="wpsms_mms_image[]" value=""/>
                                        <?php else: ?>
                                            <p><?php echo sprintf(__('This gateway doesn\'t support the MMS, <a href="%s" target="_blank">click here</a> to see which gateways support.', 'wp-sms'), WP_SMS_SITE . '/gateways'); ?></p>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                                <?php if ($proIsActive): ?>
                                    <tr id="schedule" valign="top">
                                        <th scope="row">
                                            <label for="datepicker"><?php _e('Scheduled message?', 'wp-sms'); ?></label>
                                        </th>
                                        <td>
                                            <input type="checkbox" id="schedule_status" name="schedule_status"/>
                                        </td>
                                    </tr>
                                    <tr id="schedule_date" valign="top" style="display: none;">
                                        <th scope="row">
                                            <label for="datepicker"><?php _e('Set date', 'wp-sms'); ?>:</label>
                                        </th>
                                        <td>
                                            <input type="text" id="datepicker" readonly="readonly" name="wpsms_scheduled"/>
                                            <p><?php echo __("Site's time zone", 'wp-sms') . ': ' . wp_timezone_string(); ?></p>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <tr id="schedule" valign="top">
                                        <th scope="row">
                                            <label for="datepicker"><?php _e('Scheduled message?', 'wp-sms'); ?></label>
                                        </th>
                                        <td style="padding-top: 10px;">
                                            <input type="checkbox" id="schedule_status" name="schedule_status" disabled="disabled"/>
                                            <p class="wpsms-error-notice" style="padding: 4px 4px;"><?php _e('Requires the Pro Pack', 'wp-sms'); ?></p>
                                        </td>
                                    </tr>
                                <?php endif; ?>

                                <?php if ($this->sms->flash == "enable") { ?>
                                    <tr>
                                        <td><?php _e('Send as a Flash', 'wp-sms'); ?>:</td>
                                        <td>
                                            <input type="radio" id="flash_yes" name="wp_flash" value="true"/>
                                            <label for="flash_yes"><?php _e('Yes', 'wp-sms'); ?></label>
                                            <input type="radio" id="flash_no" name="wp_flash" value="false" checked="checked"/>
                                            <label for="flash_no"><?php _e('No', 'wp-sms'); ?></label> <br/>
                                            <p class="description"><?php _e('A message that appears on the recipient\'s mobile screen directly. The recipient does not need to go to the mobile phone inbox to read the message, nor is the message allocated to the SMS inbox.', 'wp-sms'); ?></p>
                                        </td>
                                    </tr>
                                <?php } ?>

                                <tr>
                                    <td>
                                        <p class="submit" style="padding: 0;">
                                            <input type="submit" class="button-primary" name="SendSMS" value="<?php _e('Send SMS', 'wp-sms'); ?>"/>
                                        </p>
                                    </td>
                                </tr>
                            </table>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
