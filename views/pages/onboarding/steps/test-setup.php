<div class="c-section__title">
    <span class="c-section__step">
        <?php echo esc_html(sprintf(__('Step %d of 7', 'wp-sms'), $index)); ?>
    </span>
    <h1 class="u-m-0">
        <?php esc_html_e('Test Your Setup', 'wp-sms'); ?>
    </h1>
    <p class="u-m-0">
        <?php esc_html_e('Send a test SMS to the administrator\'s phone number to confirm everything is working as it should.', 'wp-sms'); ?>
    </p>
</div>
<form method="post" action="<?php echo esc_url($ctas['next']['url']); ?>">
    <div class="c-section__test">
        <div class="wpsms-admin-alert wpsms-admin-alert--success">
            <div class="wpsms-admin-alert--content">
                <p>
                    <?php esc_html_e('A test message will be sent to the phone number you provided earlier. Please check your device for the message.', 'wp-sms'); ?>
                </p>
            </div>
        </div>
        <div class="wpsms-admin-alert wpsms-admin-alert--info">
            <div class="wpsms-admin-alert--content">
                <h2>
                    <?php esc_html_e('Did you receive the test SMS?', 'wp-sms'); ?>
                </h2>
                <p>
                    <?php esc_html_e('If you\'ve received the test SMS, clicking \'Yes, I received it!\' will confirm your setup is correct and take you to the next step. If not, select \'No, I didn\'t receive it.\' for troubleshooting options.', 'wp-sms'); ?>
                </p>
            </div>
        </div>
    </div>
    <div class="c-form__footer u-content-sp u-align-center">
        <a class="c-form__footer--last-step" href="<?php echo esc_url($ctas['back']['url']); ?>">
            <?php echo esc_html($ctas['not_received']['text']); ?>
        </a>
        <input class="c-btn c-btn--primary" type="submit" value="<?php echo esc_attr($ctas['received']['text']); ?>"/>
    </div>
</form>