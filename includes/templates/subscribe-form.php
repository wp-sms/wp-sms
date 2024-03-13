<form class="js-wpSmsSubscribeForm">
    <div class="wpsms-subscribe js-wpSmsSubscribeFormContainer">
        <div class="wpsms-subscribe__overlay js-wpSmsSubscribeOverlay" style="display: none;">
            <svg class="wpsms-subscribe__overlay__spinner" xmlns="http://www.w3.org/2000/svg" style="margin:auto;background:0 0" width="10%" height="10%" viewBox="0 0 100 100" preserveAspectRatio="xMidYMid" display="block">
                <circle cx="50" cy="50" fill="none" stroke="#c6c6c6" stroke-width="10" r="35" stroke-dasharray="164.93361431346415 56.97787143782138">
                    <animateTransform attributeName="transform" type="rotate" repeatCount="indefinite" dur="1s" values="0 50 50;360 50 50" keyTimes="0;1"/>
                </circle>
            </svg>
        </div>

        <h2 class="wpsms-subscribe__title"><?php echo isset($attributes['title']) ? esc_html($attributes['title']) : esc_html__('Subscribe SMS', 'wp-sms'); ?></h2>
        <?php if (isset($attributes['description'])) { ?>
            <p><?php echo esc_html($attributes['description']); ?></p>
        <?php } ?>

        <div class="wpsms-subscribe__form">
            <div class="wpsms-form-step-one js-wpSmsSubscribeStepOne">
                <div class="wpsms-subscribe__form__field js-wpSmsSubscribeFormField js-wpSmsSubscriberName">
                    <label><?php esc_html_e('Your Name', 'wp-sms'); ?></label>
                    <input type="text" placeholder="<?php esc_html_e('Full Name', 'wp-sms'); ?>" class="wpsms-subscribe__field__input"/>
                </div>

                <div class="wpsms-subscribe__form__field js-wpSmsSubscribeFormField js-wpSmsSubscriberMobile">
                    <label><?php esc_html_e('Phone Number', 'wp-sms'); ?></label>
                    <?php wp_sms_render_mobile_field(['class' => ['wpsms-subscribe__field__input']]); ?>
                </div>


                <?php if (wp_sms_get_option('newsletter_form_groups')) { ?>
                    <?php if (wp_sms_get_option('newsletter_form_multiple_select')) { ?>
                        <div class="wpsms-subscribe__form__field js-wpSmsSubscribeFormField wpsms-subscribe__form__field--gdpr js-wpSmsSubscriberGroupIds">
                            <label><?php esc_html_e('Select the Groups', 'wp-sms'); ?></label>
                            <div class="wpsms-subscribe__form__field--gdpr__box">
                                <?php foreach ($get_group_result as $items) : ?>
                                    <label>
                                        <input name="group_id_checkbox" type="checkbox" value="<?php echo esc_attr($items->ID); ?>"/><?php echo esc_attr($items->name); ?>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php } else { ?>
                        <div class="wpsms-subscribe__form__field js-wpSmsSubscribeFormField js-wpSmsSubscriberGroupId">
                            <label><?php esc_html_e('Select a Group', 'wp-sms'); ?></label>
                            <select name="group_id_select" id="wpsms-groups" class="wpsms-subscribe__field__input">
                                <option value=""><?php esc_html_e('Please select the group', 'wp-sms'); ?></option>
                                <?php foreach ($get_group_result as $items) : ?>
                                    <option value="<?php echo esc_attr($items->ID); ?>" <?php selected(wp_sms_get_option('newsletter_form_default_group'), $items->ID); ?>><?php echo esc_attr($items->name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="wpsms-subscribe__field__triangle"></div>
                        </div>
                    <?php } ?>
                <?php } ?>

                <?php if (isset($attributes['fields'])) : ?><?php foreach ($attributes['fields'] as $key => $field) : ?>
                    <div class="wpsms-subscribe__form__field js-wpSmsSubscribeFormField js-wpSmsSubscriberCustomFields" data-field-name=<?php echo esc_html(ucfirst($field['label'])); ?>>
                        <label for="wpsms-<?php echo esc_attr($key); ?>"><?php echo esc_html(ucfirst($field['label'])); ?></label>
                        <input id="wpsms-<?php echo esc_attr($key); ?>" name="fields[<?php echo esc_attr($key); ?>]" type="<?php echo esc_attr($field['type']); ?>" placeholder="<?php echo esc_attr($field['description']); ?>" class="wpsms-subscribe__field__input"/>
                    </div>
                <?php endforeach; ?><?php endif; ?>

                <div class="wpsms-subscribe__form__field js-wpSmsSubscribeFormField wpsms-subscribe__form__field--radio">
                    <label>
                        <input type="radio" class="wpsms-subscribe-type__field__input js-wpSmsSubscribeType wpsms-type-subscribe" name="subscribe_type" value="subscribe" checked="checked" data-label="<?php esc_html_e('Subscribe', 'wp-sms'); ?>"/>
                        <?php esc_html_e('Subscribe', 'wp-sms'); ?>
                    </label>

                    <label>
                        <input type="radio" class="wpsms-subscribe-type__field__input js-wpSmsSubscribeType wpsms-type-unsubscribe" name="subscribe_type" value="unsubscribe" data-label="<?php esc_html_e('Unsubscribe', 'wp-sms'); ?>"/>
                        <?php esc_html_e('Unsubscribe', 'wp-sms'); ?>
                    </label>
                </div>
                <?php if ($gdpr_compliance) { ?>
                    <div class="wpsms-subscribe__form__field js-wpSmsSubscribeFormField wpsms-subscribe__form__field--gdpr">
                        <label>
                            <input class="wpsms-gdpr-confirmation js-wpSmsGdprConfirmation" type="checkbox" <?php echo $subscribe_form_gdpr_confirm_checkbox == 'checked' ? 'checked="checked"' : ''; ?>>
                            <?php echo $subscribe_form_gdpr_text ? wp_kses_post($subscribe_form_gdpr_text) : esc_html__('I agree to receive SMS based on my data', 'wp-sms'); ?>
                        </label>
                    </div>
                <?php } ?>
                <button class="wpsms-button wpsms-form-submit js-wpSmsSubmitTypeButton js-wpSmsSubmitButton" <?php if ($gdpr_compliance && $subscribe_form_gdpr_confirm_checkbox == 'unchecked') {
                    echo 'disabled';
                }; ?>>
                    <?php esc_html_e('Subscribe', 'wp-sms'); ?>
                </button>
            </div>

            <div class="wpsms-subscribe__messages js-wpSmsSubscribeMessage"></div>

            <div class="wpsms-form-step-two js-wpSmsSubscribeStepTwo" style="display: none" <?php wp_sms_get_option('disable_style_in_front') ? '' : 'style="display: none;"'; ?>>

                <div class="wpsms-subscribe__form__field js-wpSmsSubscribeFormField">
                    <label><?php esc_html_e('Activation code', 'wp-sms'); ?></label>
                    <input type="text" class="wpsms-activation-code js-wpSmsActivationCode" placeholder="<?php esc_html_e('Activation code', 'wp-sms'); ?>" class="wpsms-subscribe__field__input"/>
                </div>
                <button class="wpsms-button wpsms-activation-submit js-wpSmsSubmitTypeButton js-wpSmsActivationButton"><?php esc_html_e('Activate', 'wp-sms'); ?></button>
                <input type="hidden" class="newsletter-form-verify js-wpSmsMandatoryVerify" value="<?php echo esc_attr(wp_sms_get_option('newsletter_form_verify')); ?>">
            </div>

        </div>
    </div>
</form>