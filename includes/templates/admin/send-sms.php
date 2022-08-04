<div class="wrap wpsms-wrap">
    <?php require_once WP_SMS_DIR . 'includes/templates/header.php'; ?>
    <div class="wpsms-wrap__main">
        <h1 class="wrap__title"><?php _e('Send SMS', 'wp-sms'); ?>
        </h1>
        <div class="wpsms-wrap__main__notice notice is-dismissible">
            <p class="wpsms-wrap__notice__text" style="padding: 10px 0"></p>
            <button type="button" onclick="closeNotice()" class="notice-dismiss">
                <span class="screen-reader-text">Dismiss this notice.</span>
            </button>
        </div>
        <div class="wpsms-sendsms" style="padding-top: 4px;">
            <div class="postbox-container wpsms-sendsms__container">
                <div class="meta-box-sortables">
                    <div class="postbox">
                        <div class="inside">
                            <div class="wpsms-sendsms__overlay">
                                <svg class="wpsms-sendsms__overlay__spinner" xmlns="http://www.w3.org/2000/svg" style="margin:auto;background:0 0" width="200" height="200" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid" display="block">
                                    <circle cx="50" cy="50" fill="none" stroke="#c6c6c6" stroke-width="10" r="35" stroke-dasharray="164.93361431346415 56.97787143782138">
                                        <animateTransform attributeName="transform" type="rotate" repeatCount="indefinite" dur="1s" values="0 50 50;360 50 50" keyTimes="0;1"/>
                                    </circle>
                                </svg>
                            </div>
                            <form method="post" action="">
                                <?php wp_nonce_field('update-options'); ?>
                                <table class="form-table">
                                    <tr valign="top">
                                        <th scope="row">
                                            <label for="wp_get_sender"><?php _e('From', 'wp-sms'); ?>:</label>
                                        </th>
                                        <td>
                                            <input type="text" name="wp_get_sender" id="wp_get_sender" value="<?php echo $smsObject->from; ?>" maxlength="18"/>
                                        </td>
                                    </tr>
                                    <tr valign="top">
                                        <th scope="row">
                                            <label for="select_sender"><?php _e('To', 'wp-sms'); ?>:</label>
                                        </th>
                                        <td>
                                            <select name="wp_send_to" id="select_sender">
                                                <option value="subscribers" id="wp_subscribe_username"><?php _e('Subscribers', 'wp-sms'); ?>
                                                </option>
                                                <option value="users" id="wp_users"><?php _e('WordPress\'s Users', 'wp-sms'); ?>
                                                </option>
                                                <option value="wc-customers" id="wc_users" <?php disabled(!$proIsActive); ?>>
                                                    <?php _e('WooCommerce\'s Customers', 'wp-sms'); ?>
                                                    <?php if (!$proIsActive) : ?>
                                                        <span>(<?php _e('Requires Pro Pack!', 'wp-sms'); ?>)</span>
                                                    <?php endif; ?>
                                                </option>
                                                <option value="bp-users" id="bp_users" <?php disabled(!$proIsActive); ?>>
                                                    <?php _e('BuddyPress\'s Users', 'wp-sms'); ?>
                                                    <?php if (!$proIsActive) : ?>
                                                        <span>(<?php _e('Requires Pro Pack!', 'wp-sms'); ?>)</span>
                                                    <?php endif; ?>
                                                </option>
                                                <option value="numbers" id="wp_tellephone"><?php _e('Number(s)', 'wp-sms'); ?>
                                                </option>
                                            </select>

                                            <?php if (count($get_group_result)) : ?>
                                                <div class="wpsms-value wpsms-group">
                                                    <select name="wpsms_groups[]" multiple="true" class="js-wpsms-select2" data-placeholder="<?php _e('Please select the Group', 'wp-sms'); ?>">
                                                        <?php foreach ($get_group_result as $items): ?>
                                                            <option value="<?php echo $items->ID; ?>">
                                                                <?php echo sprintf(__('Group %s', 'wp-sms'), $items->name); ?>
                                                            </option>
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

                                            <div class="wpsms-value wpsms-users wpsms-users-roles">
                                                <select id="wpsms_roles" name="wpsms_roles[]" multiple="true" class="js-wpsms-select2" data-placeholder="<?php _e('Please select the Role', 'wp-sms'); ?>">
                                                    <?php
                                                    foreach ($wpsms_list_of_role as $key_item => $val_item):
                                                        ?>
                                                        <option value="<?php echo $key_item; ?>"
                                                            <?php if ($val_item['count'] < 1) {
                                                                echo " disabled";
                                                            } ?>><?php _e($val_item['name'], 'wp-sms'); ?>
                                                            (<?php echo sprintf(__('<b>%s</b> Users have mobile number.', 'wp-sms'), $val_item['count']); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>

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
                                                <div style="font-size: 14px"><?php _e('Separate the numbers with comma (,) or enter in each lines.', 'wp-sms'); ?>
                                                </div>
                                                <?php if ($smsObject->validateNumber) : ?>
                                                    <div style="margin-top: 10px"><?php echo sprintf(__('Gateway description: <code>%s</code>', 'wp-sms'), $smsObject->validateNumber); ?>
                                                </div>
                                                <?php endif; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php if (!$smsObject->bulk_send) : ?>
                                        <tr>
                                            <td></td>
                                            <td><?php _e('This gateway doesn\'t support the bulk SMS and will use the first number while sending a group of numbers.', 'wp-sms'); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                    <tr valign="top">
                                        <th scope="row">
                                            <label for="wp_get_message"><?php _e('Message', 'wp-sms'); ?>:</label>
                                        </th>
                                        <td>
                                            <textarea dir="auto" cols="80" rows="5" wrap="hard" name="wp_get_message wpsms-input" id="wp_get_message"></textarea><br/>
                                            <p class="number wpsms-wrap__account-balance">
                                                <?php echo __('Your account credit', 'wp-sms') . ': ' . $gatewayCredit; ?>
                                            </p>
                                        </td>
                                    </tr>

                                    <tr>
                                        <th><?php _e('Choice MMS media', 'wp-sms'); ?>
                                        </th>
                                        <td>
                                            <?php if ($smsObject->supportMedia) : ?>
                                                <div><a href="#" class="wpsms-upload-button button">Upload image</a>
                                                </div>
                                                <div style="margin-top: 11px;">
                                                    <a href="#" class="wpsms-remove-button button" style="display:none">Remove image</a>
                                                </div><input type="hidden" class="wpsms-mms-image" name="wpsms_mms_image[]" value=""/>
                                            <?php else: ?>
                                                <p><?php echo sprintf(__('This gateway doesn\'t support the MMS, <a href="%s" target="_blank">click here</a> to see which gateways support it.', 'wp-sms'), WP_SMS_SITE . '/gateways'); ?>
                                                </p>
                                            <?php endif; ?>
                                        </td>
                                    </tr>

                                    <?php if ($proIsActive): ?>
                                        <tr id="schedule" valign="top">
                                            <th scope="row">
                                                <label for="schedule_status"><?php _e('Scheduled message?', 'wp-sms'); ?></label>
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
                                                <p><?php echo __("Site's time zone", 'wp-sms') . ': ' . wp_timezone_string(); ?>
                                                </p>
                                            </td>
                                        </tr>
                                        <tr valign="top">
                                            <th scope="row">
                                                <label for="wpsms_repeat_status"><?php _e('Repeat?', 'wp-sms'); ?></label>
                                            </th>
                                            <td>
                                                <input type="checkbox" id="wpsms_repeat_status" name="repeat_status"/>
                                            </td>
                                        </tr>
                                        <tr class="repeat-subfield" valign="top">
                                            <th scope="row">
                                                <label for="repeat-interval"><?php _e('Repeat every', 'wp-sms'); ?></label>
                                            </th>
                                            <td>
                                                <input type="number" name="wpsms_repeat-interval" id="repeat-interval" min=1 value=1>
                                                <select name="wpsms_repeat-interval-unit" id="repeat-interval-unit">
                                                    <option value="day"><?php _e('Day', 'wp-sms') ?>
                                                    </option>
                                                    <option value="week"><?php _e('Week', 'wp-sms') ?>
                                                    </option>
                                                    <option value="month"><?php _e('Month', 'wp-sms') ?>
                                                    </option>
                                                    <option value="year"><?php _e('Year', 'wp-sms') ?>
                                                    </option>
                                                </select>
                                            </td>
                                        </tr>
                                        <tr class="repeat-subfield" valign="top">
                                            <th scope="row">
                                                <label for="repeat-ends_on"><?php _e('End on', 'wp-sms'); ?></label>
                                            </th>
                                            <td>
                                                <input type="text" id="repeat_ends_on" readonly="readonly" name="wpsms_repeat_ends_on">
                                                <input type="checkbox" name="" id="repeat-forever"><label for="repeat-forever"><?php _e('Repeat Forever', 'wp-sms') ?></label>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <tr id=" schedule" valign="top">
                                            <th scope="row">
                                                <label for="datepicker"><?php _e('Scheduled message?', 'wp-sms'); ?></label>
                                            </th>
                                            <td style="padding-top: 10px;">
                                                <input type="checkbox" id="schedule_status" name="schedule_status" disabled="disabled"/>
                                                <p class="wpsms-error-notice" style="padding: 4px 4px;"><?php echo sprintf('Requires <a href="%s" target="_blank">Pro Pack!</a>', WP_SMS_SITE . '/buy'); ?></p>
                                            </td>
                                        </tr>
                                        <tr valign="top">
                                            <th scope="row">
                                                <label for="datepicker"><?php _e('Repeat?', 'wp-sms'); ?></label>
                                            </th>
                                            <td style="padding-top: 10px;">
                                                <input type="checkbox" id="repeat_status" name="repeat_status" disabled="disabled"/>
                                                <p class="wpsms-error-notice" style="padding: 4px 4px;"><?php echo sprintf('Requires <a href="%s" target="_blank">Pro Pack!</a>', WP_SMS_SITE . '/buy'); ?></p>
                                            </td>
                                        </tr>
                                    <?php endif; ?>

                                    <?php if ($smsObject->flash == "enable") { ?>
                                        <tr>
                                            <th><?php _e('Send as a Flash', 'wp-sms'); ?>:
                                            </th>
                                            <td>
                                                <input type="radio" id="flash_yes" name="wp_flash" value="true"/>
                                                <label for="flash_yes"><?php _e('Yes', 'wp-sms'); ?></label>
                                                <input type="radio" id="flash_no" name="wp_flash" value="false" checked="checked"/>
                                                <label for="flash_no"><?php _e('No', 'wp-sms'); ?></label>
                                                <br/>
                                                <p class="description"><?php _e('A message that appears on the recipient\'s mobile screen directly. The recipient does not need to go to the mobile phone inbox to read the message, nor is the message allocated to the SMS inbox.', 'wp-sms'); ?>
                                                </p>
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
            <div class="wpsms-sendsms__preview">
                <div class="preview">
                    <div class="preview__screen">
                        <div class="preview__message">
                            <p class="preview__message__number">0000</p>
                            <div class="preview__message__message-wrapper">
                                <p class="preview__message__message"></p>
                            </div>
                            <p class="preview__message__image">
                                <svg width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <rect width="32" height="32" fill="#F88E40"/>
                                    <path d="M11.4651 20.25C11.189 20.25 10.9651 20.4739 10.9651 20.75C10.9651 21.0262 11.189 21.25 11.4651 21.25V20.25ZM24.6163 20.75V21.25C24.8701 21.25 25.0836 21.0599 25.1129 20.8078L24.6163 20.75ZM25.75 11L26.2467 11.0578C26.2631 10.9161 26.2183 10.7741 26.1234 10.6675C26.0285 10.561 25.8927 10.5 25.75 10.5V11ZM13.2791 11V10.5C13.0768 10.5 12.8945 10.6218 12.8171 10.8087C12.7397 10.9955 12.7825 11.2106 12.9255 11.3536L13.2791 11ZM18.7209 16.4419L18.3674 16.7954C18.5454 16.9734 18.828 16.9913 19.027 16.8372L18.7209 16.4419ZM8.2907 13.4477C8.01456 13.4477 7.7907 13.6715 7.7907 13.9477C7.7907 14.2238 8.01456 14.4477 8.2907 14.4477V13.4477ZM12.5988 14.4477C12.875 14.4477 13.0988 14.2238 13.0988 13.9477C13.0988 13.6715 12.875 13.4477 12.5988 13.4477V14.4477ZM6.25 15.4884C5.97386 15.4884 5.75 15.7122 5.75 15.9884C5.75 16.2645 5.97386 16.4884 6.25 16.4884V15.4884ZM14.6395 16.4884C14.9157 16.4884 15.1395 16.2645 15.1395 15.9884C15.1395 15.7122 14.9157 15.4884 14.6395 15.4884V16.4884ZM16.2267 18.7558C16.5029 18.7558 16.7267 18.532 16.7267 18.2558C16.7267 17.9797 16.5029 17.7558 16.2267 17.7558V18.7558ZM9.42442 17.7558C9.14828 17.7558 8.92442 17.9797 8.92442 18.2558C8.92442 18.532 9.14828 18.7558 9.42442 18.7558V17.7558ZM11.4651 21.25H24.6163V20.25H11.4651V21.25ZM25.1129 20.8078L26.2467 11.0578L25.2533 10.9423L24.1196 20.6923L25.1129 20.8078ZM25.75 10.5H13.2791V11.5H25.75V10.5ZM12.9255 11.3536L18.3674 16.7954L19.0745 16.0883L13.6326 10.6465L12.9255 11.3536ZM19.027 16.8372L26.0561 11.3954L25.4439 10.6047L18.4148 16.0465L19.027 16.8372ZM8.2907 14.4477H12.5988V13.4477H8.2907V14.4477ZM6.25 16.4884H14.6395V15.4884H6.25V16.4884ZM16.2267 17.7558H9.42442V18.7558H16.2267V17.7558Z" fill="white"/>
                                </svg>
                            </p>
                            <p class="preview__message__date"><?php echo current_datetime()->format('m/d  h:i') ?>
                            </p>
                        </div>
                    </div>
                    <div class="preview__button"></div>
                </div>
            </div>
        </div>
    </div>
</div>
