<div class="wrap wpsms-wrap">
    <?php echo \WP_SMS\Helper::loadTemplate('header.php'); ?>

    <div class="wpsms-sendsms">

        <div class="wpsms-sendsms__overlay">
            <svg class="wpsms-sendsms__overlay__spinner" xmlns="http://www.w3.org/2000/svg" style="margin:auto;background:0 0" width="200" height="200" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid" display="block">
                <circle cx="50" cy="50" fill="none" stroke="#c6c6c6" stroke-width="10" r="35" stroke-dasharray="164.93361431346415 56.97787143782138">
                    <animateTransform attributeName="transform" type="rotate" repeatCount="indefinite" dur="1s" values="0 50 50;360 50 50" keyTimes="0;1"/>
                </circle>
            </svg>
        </div>

        <div class="sendsms-header">
            <div class="budget"><span class="icon"></span>
                <p><?php echo __('Account Credit', 'wp-sms'); ?>: <span id="wpsms_account_credit"><?php echo $gatewayCredit; ?></span></p>
            </div>
            <a style="<?php echo $proIsActive ? 'display:none;' : '' ?>" target="_blank" href="<?php echo WP_SMS_SITE . '/buy'; ?>" class="pro-button '' ?> "><?php _e('Go Pro', 'wp-sms'); ?><span class="icon"></span></a>
        </div>

        <h1><?php _e('Send SMS', 'wp-sms'); ?></h1>

        <div class="sendsms-tabs">
            <div class="tab active" id="content"><?php _e('Content', 'wp-sms'); ?><span class="icon"></div>
            <div class="tab " id="receiver"><?php _e('Receiver', 'wp-sms'); ?><span class="icon"></div>
            <div class="tab " id="options"><?php _e('Options', 'wp-sms'); ?><span class="icon"></div>
            <div class="tab" id="send"><?php _e('Send', 'wp-sms'); ?><span class="icon"></div>
        </div>
        <div class="sendsms-tabs-line"></div>

        <div class="sendsms-content">
            <form method="post" action="">
                <?php wp_nonce_field('update-options'); ?>

                <div class="from-field">
                    <label for="wp_get_sender"><?php _e('From', 'wp-sms'); ?></label>
                    <input type="text" name="wp_get_sender" id="wp_get_sender" value="<?php echo $smsObject->from; ?>" maxlength="18"/>
                </div>


                <div class="to-field">
                    <label for="select_sender"><?php _e('To', 'wp-sms'); ?></label>

                    <select name="wp_send_to" id="select_sender">
                        <option value="subscribers" id="wp_subscribe_username"><?php _e('Subscribers', 'wp-sms'); ?>
                        </option>
                        <option value="roles" id="wp_roles"><?php _e('WordPress\'s Roles', 'wp-sms'); ?>
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
                        <?php do_action('wp_sms_form_send_to_select_option', $smsObject, $proIsActive); ?>
                        <option value="numbers" id="wp_tellephone"><?php _e('Number(s)', 'wp-sms'); ?>
                        </option>
                    </select>
                </div>


                <div class="wpsms-value wpsms-group wpsms-group-field">
                    <label><?php _e('Select Group', 'wp-sms'); ?></label>
                    <?php if (count($get_group_result)) : ?>
                        <select name="wpsms_groups[]" multiple="true" class="js-wpsms-select2" data-placeholder="<?php _e('Please select the Group', 'wp-sms'); ?>">
                            <?php foreach ($get_group_result as $items): ?>
                                <option value="<?php echo $items->ID; ?>">
                                    <?php echo sprintf(__('Group %s', 'wp-sms'), $items->name); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <p class="field-description wpsms-value wpsms-group" style="display: none;">
                            <?php
                            global $wpdb;
                            $username_active = $wpdb->query("SELECT * FROM {$wpdb->prefix}sms_subscribes WHERE status = '1'");
                            echo sprintf(__('<b>%s</b> Subscribers.', 'wp-sms'), $username_active);
                            ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="wpsms-value wpsms-roles wpsms-users-roles wpsms-users-field">
                    <label for="wpsms_roles"><?php _e('Select The Role', 'wp-sms'); ?></label>
                    <select id="wpsms_roles" name="wpsms_roles[]" multiple="true" class="js-wpsms-select2" data-placeholder="<?php _e('Please select the Role', 'wp-sms'); ?>">
                        <?php foreach ($wpsms_list_of_role as $key_item => $val_item): ?>
                            <option value="<?php echo $key_item; ?>"
                                <?php echo $val_item['count'] < 1 ? " disabled" : ''; ?>><?php _e($val_item['name'], 'wp-sms'); ?>
                                (<?php echo sprintf(__('<b>%s</b> Users have the mobile number.', 'wp-sms'), $val_item['count']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="field-description wpsms-users">
                        <?php echo sprintf(__('<b>%s</b> Users have the mobile number.', 'wp-sms'), count($get_users_mobile)); ?></>
                    </p>
                </div>

                <div class="wpsms-value wpsms-users wpsms-search-user wpsms-search-user-field">
                    <label for="wpsms_search_user"><?php _e('Search User', 'wp-sms'); ?></label>
                    <select id="wpsms_search_user" name="wpsms_users[]" multiple="true" class="js-wpsms-select2" data-placeholder="<?php _e('Please search for specific users', 'wp-sms'); ?>">
                    </select>
                    <p class="field-description wpsms-users">
                        <?php _e('Search for users by their usernames.', 'wp-sms'); ?>
                    </p>
                </div>

                <p class="field-description wpsms-value wpsms-wc-users" style="display: none;">
                    <?php echo sprintf(__('<b>%s</b> Customers have the mobile number.', 'wp-sms'), count($woocommerceCustomers)); ?>
                </p>

                <p class="field-description wpsms-value wpsms-bp-users" style="display: none;">
                    <span><?php echo sprintf(__('<b>%s</b> Users have the mobile number in their profile.', 'wp-sms'), count($buddyPressMobileNumbers)); ?></span>
                </p>

                <?php do_action('wp_sms_form_send_to_value', $smsObject, $proIsActive); ?>

                <div class="wpsms-value wpsms-numbers wpsms-numbers-field">
                    <label for="wp_get_number"><?php _e('Write Numbers', 'wp-sms'); ?></label>
                    <div class="clearfix"></div>
                    <textarea cols="80" rows="5" style="direction:ltr;margin-top: 10px;" id="wp_get_number" name="wp_get_number"></textarea>
                    <div class="clearfix"></div>
                    <div style="font-size: 14px"><?php _e('Separate the numbers with comma (,) or enter in each lines.', 'wp-sms'); ?>
                    </div>
                    <?php if ($smsObject->validateNumber) : ?>
                        <div style="margin-top: 10px"><?php echo sprintf(__('Gateway description: <code>%s</code>', 'wp-sms'), $smsObject->validateNumber); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="bulk-field">
                    <?php if (!$smsObject->bulk_send) : ?>
                        <?php _e('This gateway doesn\'t support the bulk SMS and will use the first number while sending a group of numbers.', 'wp-sms'); ?>
                    <?php endif; ?>
                </div>

                <div class="content-field">
                    <label for="wp_get_message"><?php _e('Message', 'wp-sms'); ?></label>
                    <textarea rows="5" name="wp_get_message wpsms-input" id="wp_get_message" placeholder="<?php _e('Write your SMS message here ...', 'wp-sms'); ?>"></textarea>
                </div>

                <div class="mms-media-field">
                    <label><?php _e('Choice MMS media', 'wp-sms'); ?></label>
                    <div>
                        <?php if ($smsObject->supportMedia) : ?>
                            <div><a href="#" class="wpsms-upload-button button">Upload image</a>
                            </div>
                            <div style="margin-top: 11px;">
                                <a href="#" class="wpsms-remove-button button" style="display:none">Remove image</a>
                            </div><input type="hidden" class="wpsms-mms-image" name="wpsms_mms_image[]" value=""/>
                        <?php else: ?>
                            <p><?php echo sprintf(__('This gateway doesn\'t support the MMS, <a href="%s" target="_blank">click here</a> to see which gateways support it.', 'wp-sms'), WP_SMS_SITE . '/gateways'); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="schedule-field pro-field">
                    <div class="field">
                        <input type="checkbox" id="schedule_status" name="schedule_status" <?php echo !$proIsActive ? 'disabled' : ''; ?> />
                        <label for="schedule_status"><?php _e('Scheduled message?', 'wp-sms'); ?></label>
                    </div>
                    <a target="_blank" href="<?php echo WP_SMS_SITE . '/buy'; ?>" style="<?php echo $proIsActive ? 'display:none;' : ''; ?>" class="pro not-pro"><span class="icon"></span>Go PRO</a>
                </div>

                <div class="set-date-field">
                    <label for="datepicker"><?php _e('Set date', 'wp-sms'); ?></label>
                    <input type="text" id="datepicker" readonly="readonly" name="wpsms_scheduled"/>
                    <p class="field-description"><?php echo __("Site's time zone", 'wp-sms') . ': ' . wp_timezone_string(); ?></p>
                </div>

                <div class="repeat-field">
                    <input type="checkbox" id="wpsms_repeat_status" name="repeat_status" <?php echo !$proIsActive ? 'disabled' : ''; ?> />
                    <label for="wpsms_repeat_status"><?php _e('Repeat?', 'wp-sms'); ?></label>
                </div>

                <div class="repeat-every-field">
                    <label for="repeat-interval"><?php _e('Repeat every', 'wp-sms'); ?></label>
                    <div>
                        <input type="number" name="wpsms_repeat-interval" id="repeat-interval" min=1 value=1>
                        <select name="wpsms_repeat-interval-unit" id="repeat-interval-unit">
                            <option value="day"><?php _e('Day', 'wp-sms') ?></option>
                            <option value="week"><?php _e('Week', 'wp-sms') ?></option>
                            <option value="month"><?php _e('Month', 'wp-sms') ?></option>
                            <option value="year"><?php _e('Year', 'wp-sms') ?></option>
                        </select>
                    </div>
                </div>

                <div class="repeat-end-field">
                    <div class="date-picker">
                        <label for="repeat_ends_on"><?php _e('End on', 'wp-sms'); ?></label>
                        <input type="text" id="repeat_ends_on" readonly="readonly" name="wpsms_repeat_ends_on">
                    </div>
                    <div class="repeat-forever">
                        <input type="checkbox" name="repeat-forever" id="repeat-forever">
                        <label for="repeat-forever"><?php _e('Repeat Forever', 'wp-sms') ?></label>
                    </div>
                </div>

                <?php if ($smsObject->flash == "enable") : ?>
                    <div class="flash-field">
                        <label><?php _e('Send as a Flash', 'wp-sms'); ?></label>

                        <div class="radio-options">
                            <input type="radio" id="flash_yes" name="wp_flash" value="true"/>
                            <label for="flash_yes"><?php _e('Yes', 'wp-sms'); ?></label>

                            <input type="radio" id="flash_no" name="wp_flash" value="false" checked="checked"/>
                            <label for="flash_no"><?php _e('No', 'wp-sms'); ?></label>
                        </div>

                        <p class="field-description">
                            <?php _e('A message that appears on the recipient\'s mobile screen directly. The recipient does not need to go to the mobile phone inbox to read the message, nor is the message allocated to the SMS inbox.', 'wp-sms'); ?>
                        </p>
                    </div>
                <?php endif; ?>

                <div class="summary">
                    <!-- results section -->
                    <div class="wpsms-sendsms-result success">
                        <span class="icon"></span>
                        <p></p>
                    </div>

                    <div>
                        <h4><?php _e('From', 'wp-sms') ?></h4>
                        <p class="preview__message__number"></p>
                    </div>
                    <div>
                        <h4><?php _e('Content', 'wp-sms') ?></h4>
                        <p class="preview__message__message">
                            <span class="icon"></span>
                            <span class="empty-content"><?php _e('Sill empty!', 'wp-sms') ?></span>
                        </p>
                    </div>
                    <div>
                        <h4><?php _e('Receivers', 'wp-sms') ?></h4>
                        <p class="preview__message__receiver"></p>
                    </div>
                </div>

                <a class="sendsms-again-button" id="SendSMSAgain"><?php _e('Send Again', 'wp-sms'); ?></a>
                <button type="submit" class="sendsms-button" name="SendSMS"><?php _e('Send SMS', 'wp-sms'); ?></button>
            </form>

            <div class="previous-button"><span></span><?php _e('Prev', 'wp-sms') ?></div>
            <div class="next-button"><?php _e('Next', 'wp-sms') ?><span></span></div>
        </div>
    </div>
</div>


