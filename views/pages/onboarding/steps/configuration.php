<?php if (!defined('ABSPATH')) exit; // Exit if accessed directly ?>
<div class="c-section__title u-mb-10">
    <span class="c-section__step">
        <?php use WP_SMS\Gateway;

        /* translators: 1: current step number 2: total number of steps */
        echo esc_html(sprintf(__('Step %1$d of %2$d', 'wp-sms'), $index, $total_steps));
        ?>
    </span>
    <h1 class="u-m-0">
        <?php esc_html_e('Set Up Your SMS Gateway', 'wp-sms'); ?>
    </h1>
    <p class="u-m-0">
        <?php esc_html_e('Now that you’ve chosen your SMS gateway. This step connects your WordPress site directly to the gateway so you can start sending messages. If you’re unsure where to find these details, check the gateway’s documentation or contact their support.', 'wp-sms'); ?>
    </p>
</div>

<div class="c-form c-form--medium u-flex u-content-center u-align-center u-flex--column">
    <form class="<?php echo esc_attr($slug) . '-step-' . esc_attr($current) ?>" method="post" action="<?php echo esc_url($ctas['next']['url']); ?>">
        <?php
        foreach ($fields as $key => $field): ?>
        <div class="c-form__fieldgroup u-mb-24">
            <label for="<?php echo esc_attr($field['id']); ?>">
                <?php echo esc_html($field['name']); ?> <span class="u-text-red">*</span>
            </label>

            <?php if (isset($field['type']) && $field['type'] === 'select' && isset($field['options'])): ?>
                <select id="<?php echo esc_attr($field['id']); ?>"
                        name="<?php echo esc_attr($field['id']); ?>">
                    <?php
                    $selected_value = \WP_SMS\Option::getOption($field['id']);
                    foreach ($field['options'] as $option_value => $option_label): ?>
                        <option value="<?php echo esc_attr($option_value); ?>"
                            <?php selected($selected_value, $option_value); ?>>
                            <?php echo esc_html($option_label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php else: ?>
                <input
                    id="<?php echo esc_attr($field['id']); ?>"
                    name="<?php echo esc_attr($field['id']); ?>"
                    placeholder="<?php echo isset($field['place_holder']) ? esc_attr($field['place_holder']) : ''; ?>"
                    type="<?php echo ($key === 'password') ? 'password' : 'text'; ?>"
                    value="<?php echo esc_attr(\WP_SMS\Option::getOption($field['id'])); ?>"
                />
            <?php endif; ?>

            <p class="c-form__description">
                <?php echo $field['desc']; ?>
            </p>
        </div>
        <?php endforeach; ?>

        <div class="wpsms-admin-alert wpsms-admin-alert--info">
            <div class="wpsms-admin-alert--content">
                <p>
                    <?php
                    $output = [];

                    if (!empty($doc_url)) {
                        $output[] = sprintf(
                            /* translators: 1: documentation URL 2: link title */
                            __('Need More Details? <a href="%1$s" title="%2$s" target="_blank">%2$s</a>', 'wp-sms'),
                            esc_url($doc_url),
                            esc_html__('View Instructions for This Gateway', 'wp-sms')
                        );
                    }

                    if (!empty($help)) {
                        $guide_title = '<strong>' . esc_html__('Gateway Guide', 'wp-sms') . '</strong>';
                        $output[] = $guide_title . '<br>' . wp_kses_post($help);
                    }

                    if (empty($output)) {
                        $output[] = sprintf(
                            /* translators: %1$s: documentation URL */
                            __('For additional setup instructions and troubleshooting, visit the <a href="%1$s" target="_blank">WP SMS plugin documentation</a>.', 'wp-sms'),
                            esc_url(WP_SMS_SITE . '/documentation')
                        );
                    }

                    echo implode('<br>', $output);
                    ?>
                </p>
            </div>
        </div>

        <div class="gateway-status-container u-mt-38" style="display: none">
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
                    <span class="c-form__result-title"><?php esc_html_e('WhatsApp', 'wp-sms'); ?></span>
                    <span class="c-form__result-status">
                        <span class="gateway-mms-label"></span>
                        <span class="gateway-mms-description c-form__result-description"></span>
                    </span>
                </li>
            </ul>
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