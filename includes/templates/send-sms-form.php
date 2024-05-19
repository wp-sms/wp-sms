<div class="wpsms-sendSmsForm">

    <?php if (!$visibility): ?>
        <div class="wpsms-sendSmsForm__deactiveBlock">
            <div class="wpsms-sendSmsForm__deactiveBlock__content">
                <h6><?php esc_html_e('Send SMS Messages from Your Website', 'wp-sms'); ?></h6>
                <p><?php esc_html_e('Give your website visitors the power to send SMS messages directly from your site.', 'wp-sms'); ?></p>
                <a target="_blank" href="<?php echo esc_url(WP_SMS_SITE . '/buy?utm_source=wp-sms&utm_medium=link&utm_campaign=send_sms-pro'); ?>"><?php esc_html_e('Upgrade to WP SMS Pro', 'wp-sms'); ?></a>
            </div>
        </div>
    <?php else: ?>

        <!--  Loader Spinner  -->
        <div class="wpsms-sendSmsForm__overlay">
            <svg class="wpsms-sendSmsForm__overlay__spinner" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid">
                <circle cx="50" cy="50" fill="none" stroke="#c6c6c6" stroke-width="10" r="35" stroke-dasharray="164.93361431346415 56.97787143782138">
                    <animateTransform attributeName="transform" type="rotate" repeatCount="indefinite" dur="1s" values="0 50 50;360 50 50" keyTimes="0;1"/>
                </circle>
            </svg>
        </div>

        <h2 class="wpsms-sendSmsForm__title"><?php echo isset($attributes['title']) ? esc_html($attributes['title']) : esc_html__('Send SMS', 'wp-sms'); ?></h2>
        <p class="wpsms-sendSmsForm__description"><?php echo isset($attributes['description']) ? esc_html($attributes['description']) : ''; ?></p>

        <form>
            <div class="wpsms-sendSmsForm__fieldContainer">
                <label><?php esc_html_e('Message', 'wp-sms'); ?></label>
                <textarea data-max="<?php echo esc_html($attributes['maxCharacters']); ?>" placeholder="<?php esc_html_e('Write your message content', 'wp-sms'); ?>" class="wpsms-sendSmsForm__messageField"></textarea>
                <p class="wpsms-sendSmsForm__messageField__alert"><?php esc_html_e('Max remaining characters: ', 'wp-sms'); ?><span></span></p>
            </div>

            <?php if ($attributes['receiver'] == 'numbers'): ?>
                <div class="wpsms-sendSmsForm__fieldContainer">
                    <label><?php esc_html_e('Receiver', 'wp-sms'); ?></label>
                    <?php wp_sms_render_mobile_field(['class' => ['wpsms-sendSmsForm__receiverField']]); ?>
                </div>
            <?php endif; ?>

            <input type="hidden" name="receiver" value="<?php echo esc_html($attributes['receiver']); ?>"/>
            <input type="hidden" name="subscriberGroup" value="<?php echo isset($attributes['subscriberGroup']) ? esc_html($attributes['subscriberGroup']) : ''; ?>"/>
            <input class="wpsms-sendSmsForm__submit" type="submit" value="<?php esc_html_e('Send Message', 'wp-sms'); ?>"/>
        </form>
        <div class="wpsms-sendSmsForm__resultMessage"></div>

    <?php endif; ?>
</div>