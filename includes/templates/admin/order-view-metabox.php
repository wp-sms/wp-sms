<div class="wpsms-orderSmsMetabox">

    <div class="wpsms-orderSmsMetabox__overlay">
        <svg class="wpsms-orderSmsMetabox__overlay__spinner" xmlns="http://www.w3.org/2000/svg" style="margin:auto;background:0 0" width="200" height="200" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid" display="block">
            <circle cx="50" cy="50" fill="none" stroke="#c6c6c6" stroke-width="10" r="35" stroke-dasharray="164.93361431346415 56.97787143782138">
                <animateTransform attributeName="transform" type="rotate" repeatCount="indefinite" dur="1s" values="0 50 50;360 50 50" keyTimes="0;1"/>
            </circle>
        </svg>
    </div>

    <div class="wpsms-orderSmsMetabox__toField">
        <label for="phone_number"><?php esc_html_e('Receiver', 'wp-sms') ?></label>
        <select name="phone_number" id="phone_number">
            <?php foreach ($numbers as $number): ?>
                <option value="<?php echo esc_attr($number); ?>"><?php echo esc_html($number); ?></option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="wpsms-orderSmsMetabox__messageField">
        <label for="message_content"><?php esc_html_e('Message', 'wp-sms') ?></label>
        <textarea placeholder="<?php esc_html_e('Write your SMS message here ...', 'wp-sms'); ?>" id="message_content" name="message_content" rows="5"></textarea>
    </div>

    <div class="wpsms-orderSmsMetabox__variables">
        <div class="wpsms-orderSmsMetabox__variables__header">
            <label><?php esc_html_e('Variables', 'wp-sms') ?></label>
            <span class="wpsms-orderSmsMetabox__variables__icon"></span>
        </div>

        <div class="wpsms-orderSmsMetabox__variables__shortCodes">
            <?php echo wp_kses_post($variables); ?>
        </div>
    </div>

    <button name="send_sms"><?php esc_html_e('Send SMS', 'wp-sms'); ?></button>
</div>

<!-- results section -->
<div class="wpsms-orderSmsMetabox__result">
    <div class="wpsms-orderSmsMetabox__result__report">
        <span class="wpsms-orderSmsMetabox__result__icon"></span>
        <p></p>
    </div>

    <div class="wpsms-orderSmsMetabox__result__receiver">
        <h6><?php esc_html_e('Receiver', 'wp-sms') ?></h6>
        <p></p>
    </div>

    <div class="wpsms-orderSmsMetabox__result__message">
        <h6><?php esc_html_e('Content', 'wp-sms') ?></h6>
        <p></p>
    </div>

    <button class="wpsms-orderSmsMetabox__result__tryAgain"><?php esc_html_e('Try Again', 'wp-sms'); ?></button>
</div>