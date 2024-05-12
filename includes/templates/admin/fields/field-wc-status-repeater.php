<div class="repeater">
    <div data-repeater-list="wps_pp_settings[<?php echo esc_attr($args['id']); ?>]">
        <?php if (is_array($value) && count($value)) : ?>
            <?php foreach ($value as $data) : ?>
                <?php $order_status = isset($data['order_status']) ? $data['order_status'] : '' ?>
                <?php $notify_status = isset($data['notify_status']) ? $data['notify_status'] : '' ?>
                <?php $message = isset($data['message']) ? $data['message'] : '' ?>

            <div class="repeater-item" data-repeater-item>
                <div style="display: block; width: 100%; margin-bottom: 15px; border-bottom: 1px solid #ccc;">
                    <div class="wp-sms-notify-specific-order">
                        <div>
                            <select name="order_status" style="display: block; width: 100%;">
                                <option value=""><?php esc_html_e('Please Choose', 'wp-sms'); ?></option>
                                <?php foreach ($args['options']['order_statuses'] as $status_key => $status_name) : ?>
                                    <?php $key = str_replace('wc-', '', $status_key) ?>
                                    <option value="<?php echo esc_attr($key) ?>" <?php echo ($order_status == $key) ? 'selected' : '' ?>><?php echo esc_html($status_name) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description"><?php esc_html_e('Please choose an order status', 'wp-sms'); ?></p>
                        </div>
                        <div>
                            <select name="notify_status" style="display: block; width: 100%;">
                                <option value=""><?php esc_html_e('Please Choose', 'wp-sms'); ?></option>
                                <option value="1" <?php echo ($notify_status == '1') ? 'selected' : '' ?>><?php esc_html_e('Enable', 'wp-sms'); ?></option>
                                <option value="2" <?php echo ($notify_status == '2') ? 'selected' : '' ?>><?php esc_html_e('Disable', 'wp-sms'); ?></option>
                            </select>
                            <p class="description"><?php esc_html_e('Please select notify status', 'wp-sms'); ?></p>
                        </div>
                    </div>

                    <div style="display: block; width: 100%; margin-bottom: 15px;">
                        <textarea name="message" rows="3" style="display: block; width: 100%;"><?php echo esc_html($message) ?></textarea>
                        <p class="description"><?php esc_html_e('Enter the contents of the SMS message.', 'wp-sms'); ?></p>
                        <p class="description"><?php echo wp_kses_post($args['options']['variables']); ?></p>
                    </div>
                    <div>
                        <input type="button" value="<?php esc_html_e('Delete', 'wp-sms'); ?>" class="button" style="margin-bottom: 15px;" data-repeater-delete/>
                    </div>
                </div>
            </div>
        <?php endforeach; ?><?php else : ?>
            <div class="repeater-item" data-repeater-item>
                <div style="display: block; width: 100%; margin-bottom: 15px; border-bottom: 1px solid #ccc;">
                    <div style="display: block; width: 48%; float: left; margin-bottom: 15px;">
                        <select name="order_status" style="display: block; width: 100%;">
                            <option value=""><?php esc_html_e('Please Choose', 'wp-sms'); ?></option>
                            <?php foreach ($args['options']['order_statuses'] as $status_key => $status_name) : ?>
                                <?php $key = str_replace('wc-', '', $status_key) ?>
                                <option value="<?php echo esc_attr($key) ?>"><?php echo esc_html($status_name) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description"><?php esc_html_e('Please choose an order status', 'wp-sms'); ?></p>
                    </div>
                    <div style="display: block; width: 48%; float: right; margin-bottom: 15px;">
                        <select name="notify_status" style="display: block; width: 100%;">
                            <option value=""><?php esc_html_e('Please Choose', 'wp-sms'); ?></option>
                            <option value="1"><?php esc_html_e('Enable', 'wp-sms'); ?></option>
                            <option value="2"><?php esc_html_e('Disable', 'wp-sms'); ?></option>
                        </select>
                        <p class="description"><?php esc_html_e('Please select notify status', 'wp-sms'); ?></p>
                    </div>
                    <div style="display: block; width: 100%; margin-bottom: 15px;">
                        <textarea name="message" rows="3" style="display: block; width: 100%;"></textarea>
                        <p class="description"><?php esc_html_e('Enter the contents of the SMS message.', 'wp-sms'); ?></p>
                        <p class="description"><?php echo wp_kses_post($args['options']['variables']); ?></p>
                    </div>
                    <div>
                        <input type="button" value="<?php esc_html_e('Delete', 'wp-sms'); ?>" class="button" style="margin-bottom: 15px;" data-repeater-delete/>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
    <div style="margin: 10px 0;">
        <input type="button" value="<?php esc_html_e('Add another order status', 'wp-sms'); ?>" class="button button-primary" data-repeater-create/>
    </div>
</div>