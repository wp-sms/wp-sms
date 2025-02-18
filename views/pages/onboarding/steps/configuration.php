<div class="c-section__title">
    <span class="c-section__step"><?php printf(__('Step %d of 7', 'wp-sms'), $index); ?></span>
    <h1 class="u-m-0"><?php _e('Set Up Your SMS Gateway', 'wp-sms'); ?></h1>
    <p class="u-m-0">
        <?php _e("To get started with sending messages, enter your SMS gateway credentials. This connects your WordPress site directly to the SMS service.", 'wp-sms'); ?>
    </p>
</div>

<div class="c-form c-form--medium u-flex u-content-center u-align-center u-flex--column">
    <form method="post" action="<?php echo esc_url($ctas['next']['url']); ?>">
        <div class="c-form__fieldgroup u-mb-24">
            <label for="username"><?php _e('API username', 'wp-sms'); ?> <span class="u-text-red">*</span></label>
            <input id="username" name="username" placeholder="" type="text"/>
            <p class="c-form__description"><?php _e('Enter the username provided by your SMS gateway.', 'wp-sms'); ?></p>
        </div>
        <div class="c-form__fieldgroup u-mb-24">
            <label for="password"><?php _e('API password', 'wp-sms'); ?> <span class="u-text-red">*</span></label>
            <input id="password" name="password" placeholder="" type="password"/>
            <p class="c-form__description"><?php _e('Enter the password associated with your SMS gateway account.', 'wp-sms'); ?></p>
        </div>
        <div class="c-form__fieldgroup u-mb-38">
            <label for="tel"><?php _e('Sender number', 'wp-sms'); ?> <span class="u-text-red">*</span></label>
            <input id="tel" name="tel" placeholder="" type="tel"/>
            <p class="c-form__description"><?php _e('This is the number that will appear on recipients’ devices when they receive your messages.', 'wp-sms'); ?></p>
        </div>
        <div class="c-form__footer u-content-sp u-align-center">
            <a class="c-form__footer--last-step" href="<?php echo esc_url($ctas['back']['url']); ?>"><?php echo esc_html($ctas['back']['text']); ?></a>
            <input class="c-btn c-btn--primary" type="submit" value="<?php echo esc_attr($ctas['next']['text']); ?>"/>
        </div>
    </form>
</div>
