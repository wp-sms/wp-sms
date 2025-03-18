<div class="c-section__title u-mb-10">
    <span class="c-section__step">
        <?php use WP_SMS\Gateway;

        echo esc_html(sprintf(__('Step %d of 6', 'wp-sms'), $index)); ?>
    </span>
    <h1 class="u-m-0">
        <?php esc_html_e('Set Up Your SMS Gateway', 'wp-sms'); ?>
    </h1>
    <p class="u-m-0">
        <?php esc_html_e('Now that you’ve chosen your SMS gateway. This step connects your WordPress site directly to the gateway so you can start sending messages. If you’re unsure where to find these details, check the gateway’s documentation or contact their support.', 'wp-sms'); ?>
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
                    value="<?php echo esc_attr(\WP_SMS\Option::getOption($field['id'])); ?>"
                />
                <p class="c-form__description">
                    <?php echo $field['desc']; ?>
                </p>
            </div>
        <?php endforeach; ?>
        <div class="gateway-status-container" style="display: none">
            <ul class="c-form__result">
                <span><?php esc_html_e('Connection Status', 'wp-sms'); ?></span>
                <li class="c-form__result-item u-flex u-content-sp u-align-center u-mb-16">
                    <span class="c-form__result-title"><?php esc_html_e('Status', 'wp-sms'); ?></span>
                    <span class="c-form__result-status">
                        <span class="gateway-status-label"></span>
                        <span class="gateway-status-description c-form__result-description"></span>
                    </span>
                </li>
                <li class="c-form__result-item u-flex u-content-sp u-align-center u-mb-16">
                    <span class="c-form__result-title"><?php esc_html_e('Balance', 'wp-sms'); ?></span>
                    <span class="c-form__result-status">
                        <span class="gateway-balance-label"></span>
                        <span class="gateway-balance-description c-form__result-description"></span>
                    </span>
                </li>
                <li class="c-form__result-item u-flex u-content-sp u-align-center u-mb-16">
                    <span class="c-form__result-title"><?php esc_html_e('Incoming message', 'wp-sms'); ?></span>
                    <span class="c-form__result-status">
                        <span class="gateway-incoming-label"></span>
                        <span class="gateway-incoming-description c-form__result-description"></span>
                    </span>
                </li>
                <li class="c-form__result-item u-flex u-content-sp u-align-center u-mb-16">
                    <span class="c-form__result-title"><?php esc_html_e('Bulk SMS', 'wp-sms'); ?></span>
                    <span class="c-form__result-status">
                        <span class="gateway-bulk-label"></span>
                        <span class="gateway-bulk-description c-form__result-description"></span>
                    </span>
                </li>
                <li class="c-form__result-item u-flex u-content-sp u-align-center">
                    <span class="c-form__result-title"><?php esc_html_e('Send MMS', 'wp-sms'); ?></span>
                    <span class="c-form__result-status">
                        <span class="gateway-mms-label"></span>
                        <span class="gateway-mms-description c-form__result-description"></span>
                    </span>
                </li>
            </ul>
        </div>

        <div class="wpsms-admin-alert wpsms-admin-alert--info u-mt-38">
            <div class="wpsms-admin-alert--content">

                <p>
                    <?php
                    echo sprintf(
                    __('<a href="%1$s" title="%2$s" target="_blank">%2$s</a> %3$s.', 'wp-sms'),
                        esc_url(WP_SMS_SITE . '/gateways?utm_source=wp-sms&utm_medium=link&utm_campaign=onboarding'),
                        esc_html__('View dedicated documentation for this gateway', 'wp-sms'),
                        esc_html__('to learn more about specific requirements, supported features, and troubleshooting tips', 'wp-sms')
                    );
                    ?>
                </p>
            </div>
        </div>

        <div class="c-form__footer u-content-sp u-align-center">
            <a class="c-form__footer--last-step" href="<?php echo esc_url($ctas['back']['url']); ?>">
                <?php echo esc_html($ctas['back']['text']); ?>
            </a>
            <button class="c-btn c-btn--primary" id="<?php echo esc_attr($ctas['test']['id']); ?>">
                <?php echo esc_html($ctas['test']['text']); ?>
            </button>
        </div>
    </form>
</div>