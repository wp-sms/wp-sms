<div class="repeater">
    <div data-repeater-list="wpsms_settings[<?php echo esc_attr($args['id']) ?>]">
        <?php if (is_array($value) && count($value)) : ?>
            <?php foreach ($value as $key => $data) : ?>
                <?php 
                    $member_name            = isset($data['member_name']) ? $data['member_name'] : '';
                    $member_role            = isset($data['member_role']) ? $data['member_role'] : '';
                    $member_photo           = isset($data['member_photo']) ? $data['member_photo'] : '';
                    $member_availability    = isset($data['member_availability']) ? $data['member_availability'] : '';
                    $member_contact_type    = isset($data['member_contact_type']) ? $data['member_contact_type'] : '';
                    $member_contact_value   = isset($data['member_contact_value']) ? $data['member_contact_value'] : '';
                ?>
                <div class="repeater-item" data-repeater-item>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px 40px; border-bottom: 1px solid #ccc; margin-bottom: 15px;">
                        <div>
                            <input type="text" placeholder="<?php esc_html_e('Emily Brown', 'wp-sms') ?>" name="member_name" value="<?php echo esc_attr($member_name) ?>" style="display: block; width: 99%;" />
                            <p class="description"><?php esc_html_e('Team member\'s name, e.g., \'Jane Doe\'', 'wp-sms') ?></p>
                        </div>
                        <div>
                            <input type="text" placeholder="<?php esc_html_e('Marketing Manager', 'wp-sms') ?>" name="member_role" value="<?php echo esc_attr($member_role) ?>" style="display: block; width: 99%;" />
                            <p class="description"><?php esc_html_e('Team member\'s department or role, e.g., \'Customer Support\'', 'wp-sms') ?></p>
                        </div>
                        <div>
                            <div style="display: flex; gap: 5px; justify-content: space-between; width: 100%;">
                                <input id="member_photo_field[<?php echo esc_attr($key) ?>]" type="text" class="wpsms_settings_upload_field" placeholder="<?php esc_html_e('Photo', 'wp-sms') ?>" value="<?php echo esc_attr($member_photo) ?>" name="member_photo" style="width: 80%"/>
                                <span><input type="button" data-target="member_photo_field[<?php echo esc_attr($key) ?>]" class="wpsms_settings_upload_button button button-secondary" value="<?php esc_html_e('Upload', 'wp-sms') ?>"/></span>
                            </div>
                            <p class="description"><?php esc_html_e('Upload team member\'s photo.', 'wp-sms') ?></p>
                        </div>
                        <div>
                            <input type="text" placeholder="<?php esc_html_e('Available 10AM-5PM PST', 'wp-sms') ?>" value="<?php echo esc_attr($member_availability) ?>" name="member_availability" style="width: 100%;" />
                            <p class="description"><?php esc_html_e('State team member\'s chat hours, e.g., \'Available 10AM-5PM PST.\'', 'wp-sms') ?></p>
                        </div>
                        <div>
                            <select name="member_contact_type" style="display: block; min-width: 100%;">
                                <option value=""><?php esc_html_e('Contact Type', 'wp-sms') ?></option>
                                <option value="whatsapp" <?php selected($member_contact_type, 'whatsapp') ?>><?php esc_html_e('WhatsApp', 'wp-sms') ?></option>
                                <option value="call" <?php selected($member_contact_type, 'call') ?>><?php esc_html_e('Phone Call', 'wp-sms') ?></option>
                                <option value="facebook" <?php selected($member_contact_type, 'facebook') ?>><?php esc_html_e('Facebook Messenger', 'wp-sms') ?></option>
                                <option value="telegram" <?php selected($member_contact_type, 'telegram') ?>><?php esc_html_e('Telegram', 'wp-sms') ?></option>
                                <option value="sms" <?php selected($member_contact_type, 'sms') ?>><?php esc_html_e('SMS', 'wp-sms') ?></option>
                                <option value="email" <?php selected($member_contact_type, 'email') ?>><?php esc_html_e('E-mail', 'wp-sms') ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Set visitor communication methods, e.g., WhatsApp, Email.', 'wp-sms') ?></p>
                        </div>
                        <div>
                            <input type="text" placeholder="<?php esc_html_e('+1122334455', 'wp-sms') ?>" name="member_contact_value" value="<?php echo esc_attr($member_contact_value) ?>" style="display: block; width: 99%;"/>
                            <p class="description"><?php esc_html_e('Provide contact details for the chosen method.', 'wp-sms') ?></p>
                        </div>
                        <div>
                            <input type="button" value="<?php esc_html_e('Delete', 'wp-sms') ?>" class="button" style="margin-bottom: 15px;" data-repeater-delete/>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else : ?>
            <div class="repeater-item" data-repeater-item>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px 40px; border-bottom: 1px solid #ccc; margin-bottom: 15px;">
                    <div>
                        <input type="text" placeholder="<?php esc_html_e('Emily Brown', 'wp-sms') ?>" name="member_name" style="display: block; width: 100%;" />
                        <p class="description"><?php esc_html_e('Team member\'s name, e.g., \'Jane Doe\'', 'wp-sms') ?></p>
                    </div>
                    <div>
                        <input type="text" placeholder="<?php esc_html_e('Marketing Manager', 'wp-sms') ?>" name="member_role" style="display: block; width: 100%;" />
                        <p class="description"><?php esc_html_e('Team member\'s department or role, e.g., \'Customer Support\'', 'wp-sms') ?></p>
                    </div>
                    <div>
                        <div style="display: flex; gap: 5px; justify-content: space-between; width: 100%;">
                            <input id="member_photo_field" type="text" class="wpsms_settings_upload_field" placeholder="<?php esc_html_e('Photo', 'wp-sms') ?>" name="member_photo" style="width: 80%"/>
                            <span><input type="button" data-target="member_photo_field" class="wpsms_settings_upload_button button button-secondary" value="<?php esc_html_e('Upload', 'wp-sms') ?>"/></span>
                        </div>
                        <p class="description"><?php esc_html_e('Upload team member\'s photo.', 'wp-sms') ?></p>
                    </div>
                    <div>
                        <input type="text" placeholder="<?php esc_html_e('Available 10AM-5PM PST', 'wp-sms') ?>" name="member_availability" style="width: 100%;" />
                        <p class="description"><?php esc_html_e('State team member\'s chat hours, e.g., \'Available 10AM-5PM PST.\'', 'wp-sms') ?></p>
                    </div>
                    <div>
                        <select name="member_contact_type" style="display: block; min-width: 100%;">
                            <option value=""><?php esc_html_e('Contact Type', 'wp-sms') ?></option>
                            <option value="whatsapp"><?php esc_html_e('WhatsApp', 'wp-sms') ?></option>
                            <option value="call"><?php esc_html_e('Phone Call', 'wp-sms') ?></option>
                            <option value="facebook"><?php esc_html_e('Facebook Messenger', 'wp-sms') ?></option>
                            <option value="telegram"><?php esc_html_e('Telegram', 'wp-sms') ?></option>
                            <option value="sms"><?php esc_html_e('SMS', 'wp-sms') ?></option>
                            <option value="email"><?php esc_html_e('E-mail', 'wp-sms') ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Set visitor communication methods, e.g., WhatsApp, Email.', 'wp-sms') ?></p>
                    </div>
                    <div>
                        <input type="text" placeholder="<?php esc_html_e('+1122334455', 'wp-sms') ?>" name="member_contact_value" style="display: block; width: 100%;"/>
                        <p class="description"><?php esc_html_e('Provide contact details for the chosen method.', 'wp-sms') ?></p>
                    </div>
                    <div>
                        <input type="button" value="<?php esc_html_e('Delete', 'wp-sms') ?>" class="button" style="margin-bottom: 15px;" data-repeater-delete/>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
    <div style="margin: 10px 0;">
        <input type="button" value="<?php esc_html_e('Add another member', 'wp-sms') ?>" class="button button-primary" data-repeater-create/>
    </div>
</div>