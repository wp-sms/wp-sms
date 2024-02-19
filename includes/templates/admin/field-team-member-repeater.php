<div class="repeater">
    <div data-repeater-list="wpsms_settings[<?php echo $args['id'] ?>]">
        <?php if (is_array($value) && count($value)) : ?>
            <?php foreach ($value as $key => $data) : ?>
                <?php 
                    $member_name            = isset($data['member_name']) ? $data['member_name'] : '';
                    $member_role            = isset($data['member_role']) ? $data['member_role'] : '';
                    $member_photo           = isset($data['member_photo']) ? $data['member_photo'] : '';
                    $member_availability    = isset($data['member_availability']) ? 'checked' : '';
                    $member_contact_type    = isset($data['member_contact_type']) ? $data['member_contact_type'] : '';
                    $member_contact_value   = isset($data['member_contact_value']) ? $data['member_contact_value'] : '';
                ?>
                <div class="repeater-item" data-repeater-item>
                    <div style="display: block; width: 100%; margin-bottom: 15px; border-bottom: 1px solid #ccc; overflow: hidden; padding: 5px 0;">
                        <div style="display: block; width: 48%; float: left; margin-bottom: 15px;">
                            <input type="text" placeholder="<?php _e('Name', 'wp-sms') ?>" name="member_name" value="<?php echo esc_attr($member_name) ?>" style="display: block; width: 99%;" />
                            <p class="description"><?php _e('Enter the team member\'s name.', 'wp-sms') ?></p>
                        </div>
                        <div style="display: block; width: 48%; float: right; margin-bottom: 15px;">
                            <input type="text" placeholder="<?php _e('Role', 'wp-sms') ?>" name="member_role" value="<?php echo esc_attr($member_role) ?>" style="display: block; width: 99%;" />
                            <p class="description"><?php _e('Type in the team member\'s role or department.', 'wp-sms') ?></p>
                        </div>
                        <div style="margin-bottom: 15px; display: block; width: 100%; float: left;">
                            <div style="display: block; width: 100%;">
                                <input id="member_photo_field[<?php echo $key ?>]" type="text" class="wpsms_settings_upload_field" placeholder="<?php _e('Photo', 'wp-sms') ?>" value="<?php echo esc_attr($member_photo) ?>" name="member_photo"/>
                                <span><input type="button" data-target="member_photo_field[<?php echo $key ?>]" class="wpsms_settings_upload_button button button-secondary" value="<?php _e('Upload', 'wp-sms') ?>"/></span>
                            </div>
                            <p class="description"><?php _e('Upload a photo for the team member.', 'wp-sms') ?></p>
                        </div>
                        <div style="display: block; width: 100%; float: left; margin-bottom: 15px;">
                            <input id="member-availability[<?php echo $key ?>]" type="checkbox" <?php echo esc_attr($member_availability) ?> name="member_availability"/>
                            <label for="member-availability[<?php echo $key ?>]"><?php _e('Availability', 'wp-sms') ?></label>
                            <p class="description"><?php _e('Indicate when the team member is available to chat.', 'wp-sms') ?></p>
                        </div>
                        <div style="display: block; width: 48%; float: left; margin-bottom: 15px;">
                            <select name="member_contact_type" style="display: block; width: 99%;">
                                <option value=""><?php _e('Contact Type', 'wp-sms') ?></option>
                                <option value="whatsapp" <?php echo ($member_contact_type == 'whatsapp') ? 'selected' : '' ?>><?php _e('WhatsApp', 'wp-sms') ?></option>
                                <option value="call" <?php echo ($member_contact_type == 'call') ? 'selected' : '' ?>><?php _e('Phone Call', 'wp-sms') ?></option>
                                <option value="facebook" <?php echo ($member_contact_type == 'facebook') ? 'selected' : '' ?>><?php _e('Facebook Messenger', 'wp-sms') ?></option>
                                <option value="telegram" <?php echo ($member_contact_type == 'telegram') ? 'selected' : '' ?>><?php _e('Telegram', 'wp-sms') ?></option>
                                <option value="sms" <?php echo ($member_contact_type == 'sms') ? 'selected' : '' ?>><?php _e('SMS', 'wp-sms') ?></option>
                            </select>
                            <p class="description"><?php _e('Choose the team member\'s contact method (WhatsApp, Phone Call, Facebook Messenger, Telegram, SMS).', 'wp-sms') ?></p>
                        </div>
                        <div style="display: block; width: 48%; float: right; margin-bottom: 15px;">
                            <input type="text" placeholder="<?php _e('Contact Info', 'wp-sms') ?>" name="member_contact_value" value="<?php echo esc_attr($member_contact_value) ?>" style="display: block; width: 99%;"/>
                            <p class="description"><?php _e('Provide the team member\'s contact details for the selected method.', 'wp-sms') ?></p>
                        </div>
                        <div>
                            <input type="button" value="<?php _e('Delete', 'wp-sms') ?>" class="button" style="margin-bottom: 15px;" data-repeater-delete/>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="repeater-item" data-repeater-item>
                <div style="display: block; width: 100%; margin-bottom: 15px; border-bottom: 1px solid #ccc; overflow: hidden; padding: 5px 0;">
                    <div style="display: block; width: 48%; float: left; margin-bottom: 15px;">
                        <input type="text" placeholder="<?php _e('Name', 'wp-sms') ?>" name="member_name" style="display: block; width: 99%;" />
                        <p class="description"><?php _e('Enter the team member\'s name.', 'wp-sms') ?></p>
                    </div>
                    <div style="display: block; width: 48%; float: right; margin-bottom: 15px;">
                        <input type="text" placeholder="<?php _e('Role', 'wp-sms') ?>" name="member_role" style="display: block; width: 99%;" />
                        <p class="description"><?php _e('Type in the team member\'s role or department.', 'wp-sms') ?></p>
                    </div>
                    <div style="margin-bottom: 15px; display: block; width: 100%; float: left;">
                        <div style="display: block; width: 100%;">
                            <input id="member_photo_field" type="text" class="wpsms_settings_upload_field" placeholder="<?php _e('Photo', 'wp-sms') ?>" name="member_photo"/>
                            <span><input type="button" data-target="member_photo_field" class="wpsms_settings_upload_button button button-secondary" value="<?php _e('Upload', 'wp-sms') ?>"/></span>
                        </div>
                        <p class="description"><?php _e('Upload a photo for the team member.', 'wp-sms') ?></p>
                    </div>
                    <div style="display: block; width: 100%; float: left; margin-bottom: 15px;">
                        <input id="member-availability" type="checkbox" name="member_availability"/>
                        <label for="member-availability"><?php _e('Availability', 'wp-sms') ?></label>
                        <p class="description"><?php _e('Indicate when the team member is available to chat.', 'wp-sms') ?></p>
                    </div>
                    <div style="display: block; width: 48%; float: left; margin-bottom: 15px;">
                        <select name="member_contact_type" style="display: block; width: 99%;">
                            <option value=""><?php _e('Choose Contact Type', 'wp-sms') ?></option>
                            <option value="whatsapp"><?php _e('WhatsApp', 'wp-sms') ?></option>
                            <option value="call"><?php _e('Phone Call', 'wp-sms') ?></option>
                            <option value="facebook"><?php _e('Facebook Messenger', 'wp-sms') ?></option>
                            <option value="telegram"><?php _e('Telegram', 'wp-sms') ?></option>
                            <option value="sms"><?php _e('SMS', 'wp-sms') ?></option>
                        </select>
                        <p class="description"><?php _e('Choose the team member\'s contact method (WhatsApp, Phone Call, Facebook Messenger, Telegram, SMS).', 'wp-sms') ?></p>
                    </div>
                    <div style="display: block; width: 48%; float: right; margin-bottom: 15px;">
                        <input type="text" placeholder="<?php _e('Contact Info', 'wp-sms') ?>" name="member_contact_value" style="display: block; width: 99%;"/>
                        <p class="description"><?php _e('Provide the team member\'s contact details for the selected method.', 'wp-sms') ?></p>
                    </div>
                    <div>
                        <input type="button" value="<?php _e('Delete', 'wp-sms') ?>" class="button" style="margin-bottom: 15px;" data-repeater-delete/>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
    <div style="margin: 10px 0;">
        <input type="button" value="<?php _e('Add another team member', 'wp-sms') ?>" class="button button-primary" data-repeater-create/>
    </div>
</div>