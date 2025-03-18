<div class="c-section__title">
    <span class="c-section__step">
        <?php echo esc_html(sprintf(__('Step %d of 6', 'wp-sms'), $index)); ?>
    </span>
    <h1 class="u-m-0">
        <?php esc_html_e('Test Your Setup', 'wp-sms'); ?>
    </h1>
    <p class="u-m-0">
        <?php
        echo sprintf(
            __('%1$s <b>%2$s</b> %3$s!', 'wp-sms'),
             esc_html__('Before moving forward, let’s make sure your SMS gateway is working correctly. Click', 'wp-sms'),
             esc_html__('Send Test SMS', 'wp-sms'),
            esc_html__('to send a message to the administrator’s phone number you provided. Once you receive it, you’re good to go', 'wp-sms')
        );
        ?>
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
                    <?php esc_html_e('Please check your device to confirm whether you received the message.', 'wp-sms'); ?>
                </p>
            </div>
        </div>
    </div>

    <div class="c-form__footer c-form__footer--step-4 u-content-sp u-align-center">
        <a class="c-form__footer--last-step" href="<?php echo esc_url($ctas['back']['url']); ?>"><?php echo esc_html($ctas['back']['text']); ?></a>
        <div class="u-flex u-align-center">
            <a href="<?php echo esc_url($ctas['back']['url']); ?>" class="c-btn c-btn--primary c-btn--primary-light"><?php echo esc_html($ctas['not_received']['text']); ?></a>
            <input class="c-btn c-btn--primary" type="submit" value="<?php echo esc_attr($ctas['received']['text']); ?>">
        </div>
    </div>
</form>