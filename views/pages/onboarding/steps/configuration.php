<div class="c-section__title">
    <span class="c-section__step"><?php use WP_SMS\Gateway;

        printf(__('Step %d of 7', 'wp-sms'), $index); ?></span>
    <h1 class="u-m-0"><?php _e('Set Up Your SMS Gateway', 'wp-sms'); ?></h1>
    <p class="u-m-0">
        <?php _e("To get started with sending messages, enter your SMS gateway credentials. This connects your WordPress site directly to the SMS service.", 'wp-sms'); ?>
    </p>
</div>

<div class="c-form c-form--medium u-flex u-content-center u-align-center u-flex--column">
    <form method="post" action="<?php echo esc_url($ctas['next']['url']); ?>">
        <?php
        foreach ($fields as $key => $field): ?>
            <div class="c-form__fieldgroup u-mb-24">
                <label for="<?php echo esc_attr($field['id']); ?>">
                    <?php echo esc_html($field['name']); ?> <span class="u-text-red">*</span>
                </label>
                <input
                    id="<?php echo esc_attr($field['id']); ?>"
                    name="<?php echo esc_attr($field['id']); ?>"
                    placeholder=""
                    type="<?php echo ($key === 'password') ? 'password' : 'text'; ?>"
                />
                <p class="c-form__description">
                    <?php echo esc_html($field['desc']); ?>
                </p>
            </div>
        <?php endforeach; ?>
        <ul class="c-form__result c-form__result--success" style="display: none">
            Connection Status
            <li class="c-form__result-item u-flex u-content-sp u-align-center u-mb-16">
                <span class="c-form__result-title">Status</span>
                <span class="c-form__result-status gateway-status">
                </span>
            </li>
            <li class="c-form__result-item u-flex u-content-sp u-align-center u-mb-16">
                <span class="c-form__result-title">Balance</span>
                <span class="c-form__result-status gateway-balance">
                </span>
            </li>
            <li class="c-form__result-item u-flex u-content-sp u-align-center u-mb-16">
                <span class="c-form__result-title">Incoming message</span>
                <span class="c-form__result-status gateway-incoming">
                </span>
            </li>
            <li class="c-form__result-item u-flex u-content-sp u-align-center u-mb-16">
                <span class="c-form__result-title">Bulk SMS</span>
                <span class="c-form__result-status gateway-bulk">
                </span>
            </li>
            <li class="c-form__result-item u-flex u-content-sp u-align-center">
                <span class="c-form__result-title">Send MMS</span>
                <span class="c-form__result-status gateway-mms">
                </span>
            </li>
        </ul>
        <div class="c-form__footer u-content-sp u-align-center">
            <a class="c-form__footer--last-step" href="<?php echo esc_url($ctas['back']['url']); ?>">
                <?php echo esc_html($ctas['back']['text']); ?>
            </a>
            <button class="c-btn c-btn--primary" id="<?php echo esc_html($ctas['test']['id']); ?>">
                <?php echo esc_html($ctas['test']['text']); ?>
            </button>
        </div>
    </form>
</div>
