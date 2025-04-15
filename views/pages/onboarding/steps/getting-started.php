<div class="c-section__title u-border-b">
    <span class="c-section__step">
        <?php echo esc_html(sprintf(__('Step %d of 6', 'wp-sms'), $index)); ?>
    </span>
    <h1 class="u-m-0 u-text-orange">
        <?php esc_html_e('Welcome to WP SMS!', 'wp-sms'); ?>
    </h1>
    <p class="u-m-0">
        <?php esc_html_e('Set up SMS functionality for your WordPress site in just a few steps.', 'wp-sms'); ?>
    </p>
</div>
<div class="c-form c-form--medium u-flex u-content-center">
    <form method="post" action="<?php echo esc_url($ctas['next']['url']); ?>">
        <p class="c-form__title">
            <?php esc_html_e('Get Notifications Where You Need Them', 'wp-sms'); ?>
        </p>
        <div class="c-form__fieldgroup u-mb-38">
            <?php
            $current_tel_raw      = \WP_SMS\Option::getOption('admin_mobile_number');
            ?>
            <label for="tel">
                <?php esc_html_e('Admin Mobile Number', 'wp-sms'); ?> <span class="u-text-red">*</span>
            </label>
            <input class="wp-sms-input-iti-tel regular-text" value="<?php echo esc_attr($current_tel_raw); ?>" name="tel" id="tel" type="tel"/>
            <input name="code" id="wp-sms-country-code-field" class="wpsms-hide" type="text"  />
            <p class="c-form__description">
                <?php esc_html_e("Select your country and enter your mobile number. This number will be used for important notifications and alerts, so make sure it’s correct.", 'wp-sms'); ?>
            </p>
        </div>
        <div class="c-form__footer u-flex-end">
            <input class="c-btn c-btn--primary" disabled type="submit" value="<?php echo esc_attr($ctas['next']['text']); ?>"/>
        </div>
    </form>
</div>