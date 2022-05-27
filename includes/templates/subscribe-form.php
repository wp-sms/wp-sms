<div class="wpsms-subscribe">
    <div class="wpsms-subscribe__overlay" style="display: none;">
        <svg class="wpsms-subscribe__overlay__spinner" xmlns="http://www.w3.org/2000/svg" style="margin:auto;background:0 0" width="10%" height="10%" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid" display="block">
            <circle cx="50" cy="50" fill="none" stroke="#c6c6c6" stroke-width="10" r="35" stroke-dasharray="164.93361431346415 56.97787143782138">
                <animateTransform attributeName="transform" type="rotate" repeatCount="indefinite" dur="1s" values="0 50 50;360 50 50" keyTimes="0;1"/>
            </circle>
        </svg>
    </div>
    <h2 class="wpsms-subscribe__title"><?php echo isset($attributes['title']) ? $attributes['title'] : __('Subscribe SMS', 'wp-sms'); ?></h2>
    <div id="wpsms-subscribe" class="wpsms-subscribe__form">
        <div id="wpsms-step-1">
            <?php if (isset($attributes['description'])) { ?>
                <p><?php echo isset($attributes['description']) ? $attributes['description'] : ''; ?></p>
            <?php } ?>
            <div class="wpsms-subscribe__form__field">
                <label><?php _e('Your name', 'wp-sms'); ?>:</label>
                <input id="wpsms-name" type="text" placeholder="<?php _e('Your name', 'wp-sms'); ?>" class="wpsms-subscribe__field__input"/>
            </div>

            <div class="wpsms-subscribe__form__field">
                <label><?php _e('Your mobile', 'wp-sms'); ?>:</label>
                <?php wp_sms_render_mobile_field(['class' => ['wpsms-subscribe__field__input']]); ?>
            </div>

            <?php if (wp_sms_get_option('newsletter_form_groups')) { ?>
                <div class="wpsms-subscribe__form__field">
                    <label><?php _e('Group', 'wp-sms'); ?>:</label>
                    <select id="wpsms-groups" class="wpsms-subscribe__field__input">
                        <option value="0"><?php _e('Please select the group', 'wp-sms'); ?></option>
                        <?php foreach ($get_group_result as $items): ?>
                            <option value="<?php echo esc_attr($items->ID); ?>" <?php selected(wp_sms_get_option('newsletter_form_default_group'), $items->ID); ?>><?php echo esc_attr($items->name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php } ?>

            <div class="wpsms-subscribe__form__field wpsms-subscribe__form__field--radio">
                <label>
                    <input type="radio" class="wpsms-subscribe-type__field__input" name="subscribe_type" id="wpsms-type-subscribe" value="subscribe" checked="checked" data-label="<?php _e('Subscribe', 'wp-sms'); ?>"/>
                    <?php _e('Subscribe', 'wp-sms'); ?>
                </label>

                <label>
                    <input type="radio" class="wpsms-subscribe-type__field__input" name="subscribe_type" id="wpsms-type-unsubscribe" value="unsubscribe" data-label="<?php _e('Unsubscribe', 'wp-sms'); ?>"/>
                    <?php _e('Unsubscribe', 'wp-sms'); ?>
                </label>
            </div>
            <?php if ($gdpr_compliance) { ?>
                <div class="wpsms-subscribe__form__field wpsms-subscribe__form__field--gdpr">
                    <label>
                        <input id="wpsms-gdpr-confirmation" type="checkbox" <?php echo $subscribe_form_gdpr_confirm_checkbox == 'checked' ? 'checked="checked"' : ''; ?>>
                        <?php echo $subscribe_form_gdpr_text ? $subscribe_form_gdpr_text : __('I agree to receive SMS based on my data', 'wp-sms'); ?>
                    </label>
                </div>
            <?php } ?>
            <button class="wpsms-button wpsms-form-submit" id="wpsms-submit"><?php _e('Subscribe', 'wp-sms'); ?></button>
        </div>
        <div id="wpsms-result" class="wpsms-subscribe__messages"></div>
        <?php $disable_style = wp_sms_get_option('disable_style_in_front');
        if (empty($disable_style) and !$disable_style): ?>
        <div id="wpsms-step-2">
            <?php else: ?>
            <div id="wpsms-step-2" style="display: none;">
                <?php endif; ?>

                <div class="wpsms-subscribe__form__field">
                    <label><?php _e('Activation code:', 'wp-sms'); ?></label>
                    <input type="text" id="wpsms-ativation-code" placeholder="<?php _e('Activation code:', 'wp-sms'); ?>" class="wpsms-subscribe__field__input"/>
                </div>
                <button class="wpsms-button wpsms-activation-submit" id="activation"><?php _e('Activation', 'wp-sms'); ?></button>
            </div>
            <input type="hidden" id="newsletter-form-verify" value="<?php echo wp_sms_get_option('newsletter_form_verify'); ?>">
        </div>
    </div>

