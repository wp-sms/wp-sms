<div class="repeater">
    <div data-repeater-list="wps_pp_settings[<?php echo $args['id'] ?>]">
        <?php if (is_array($value) && count($value)) : ?>
            <?php foreach ($value as $data) : ?>
                <?php $order_status = isset($data['order_status']) ? $data['order_status'] : '' ?>
                <?php $notify_status = isset($data['notify_status']) ? $data['notify_status'] : '' ?>
                <?php $message = isset($data['message']) ? $data['message'] : '' ?>

            <div class="repeater-item" data-repeater-item>
                <div style="display: block; width: 100%; margin-bottom: 15px; border-bottom: 1px solid #ccc;">
                    <div style="display: block; width: 48%; float: left; margin-bottom: 15px;">
                        <select name="order_status" style="display: block; width: 100%;">
                            <option value="">- Please Choose -</option>
                            <?php foreach ($order_statuses as $status_key => $status_name) : ?>
                                <?php $key = str_replace('wc-', '', $status_key) ?>
                                <option value="<?php echo $key ?>" <?php echo ($order_status == $key) ? 'selected' : '' ?>><?php echo $status_name ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Please choose an order status</p>
                    </div>
                    <div style="display: block; width: 48%; float: right; margin-bottom: 15px;">
                        <select name="notify_status" style="display: block; width: 100%;">
                            <option value="">- Please Choose -</option>
                            <option value="1" <?php echo ($notify_status == '1') ? 'selected' : '' ?>>Enable</option>
                            <option value="2" <?php echo ($notify_status == '2') ? 'selected' : '' ?>>Disable</option>
                        </select>
                        <p class="description">Please select notify status</p>
                    </div>
                    <div style="display: block; width: 100%; margin-bottom: 15px;">
                        <textarea name="message" rows="3" style="display: block; width: 100%;"><?php echo $message ?></textarea>
                        <p class="description">Enter the contents of the SMS message.</p>
                        <p class="description"><?php echo sprintf(__('Order status: %s, Order Items: %s, Order number: %s, Order Total: %s, Order Total Currency: %s, Order Total Currency Symbol: %s, Customer name: %s, Customer family: %s, Order view URL: %s, Order payment URL: %s, Order Meta: %s', 'wp-sms'), '<code>%status%</code>', '<code>%order_items%</code>', '<code>%order_number%</code>', '<code>%order_total%</code>', '<code>%order_total_currency%</code>', '<code>%order_total_currency_symbol%</code>', '<code>%customer_first_name%</code>', '<code>%customer_last_name%</code>', '<code>%order_view_url%</code>', '<code>%order_pay_url%</code>', '<code>%order_meta_{key-name}%</code>') ?></p>
                    </div>
                    <div>
                        <input type="button" value="Delete" class="button" style="margin-bottom: 15px;" data-repeater-delete/>
                    </div>
                </div>
            </div>
        <?php endforeach; ?><?php else : ?>
            <div class="repeater-item" data-repeater-item>
                <div style="display: block; width: 100%; margin-bottom: 15px; border-bottom: 1px solid #ccc;">
                    <div style="display: block; width: 48%; float: left; margin-bottom: 15px;">
                        <select name="order_status" style="display: block; width: 100%;">
                            <option value="">- Please Choose -</option>
                            <?php foreach ($order_statuses as $status_key => $status_name) : ?>
                                <?php $key = str_replace('wc-', '', $status_key) ?>
                                <option value="<?php echo $key ?>"><?php echo $status_name ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="description">Please choose an order status</p>
                    </div>
                    <div style="display: block; width: 48%; float: right; margin-bottom: 15px;">
                        <select name="notify_status" style="display: block; width: 100%;">
                            <option value="">- Please Choose -</option>
                            <option value="1">Enable</option>
                            <option value="2">Disable</option>
                        </select>
                        <p class="description">Please select notify status</p>
                    </div>
                    <div style="display: block; width: 100%; margin-bottom: 15px;">
                        <textarea name="message" rows="3" style="display: block; width: 100%;"></textarea>
                        <p class="description">Enter the contents of the SMS message.</p>
                        <p class="description"><?php echo sprintf(__('Order status: %s, Order Items: %s, Order number: %s, Order Total: %s, Order Total Currency: %s, Order Total Currency Symbol: %s, Customer name: %s, Customer family: %s, Order view URL: %s, Order payment URL: %s', 'wp-sms'), '<code>%status%</code>', '<code>%order_items%</code>', '<code>%order_number%</code>', '<code>%order_total%</code>', '<code>%order_total_currency%</code>', '<code>%order_total_currency_symbol%</code>', '<code>%customer_first_name%</code>', '<code>%customer_last_name%</code>', '<code>%order_view_url%</code>', '<code>%order_pay_url%</code>') ?></p>
                    </div>
                    <div>
                        <input type="button" value="Delete" class="button" style="margin-bottom: 15px;" data-repeater-delete/>
                    </div>
                </div>
            </div>
        <?php endif ?>
    </div>
    <div style="margin: 10px 0;">
        <input type="button" value="Add another order status" class="button button-primary" data-repeater-create/>
    </div>
</div>