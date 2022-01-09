<div class="wpsms-newsletter">
    <h2 class="wpsms-newsletter__title"><?php echo $attributes['title'] ? $attributes['title'] : __( 'Subscribe SMS',
			'wp-sms' ); ?></h2>
    <div id="wpsms-subscribe" class="wpsms-newsletter__form">
        <div id="wpsms-result"></div>
        <div id="wpsms-step-1">
			<?php if ( isset( $attributes['description'] ) ) { ?>
                <p><?php echo isset( $attributes['description'] ) ? $attributes['description'] : ''; ?></p>
			<?php } ?>
            <div class="wpsms-newsletter__form__field">
                <label><?php _e( 'Your name', 'wp-sms' ); ?>:</label>
                <input id="wpsms-name" type="text" placeholder="<?php _e( 'Your name',
					'wp-sms' ); ?>" class="wpsms-newsletter__field__input"/>
            </div>

            <div class="wpsms-newsletter__form__field">
                <label><?php _e( 'Your mobile', 'wp-sms' ); ?>:</label>
                <input id="wpsms-mobile" type="text" placeholder="<?php echo wp_sms_get_option( 'mobile_terms_field_place_holder' ); ?>" class="wpsms-input<?php echo $international_mobile ? " wp-sms-input-mobile" : ""; ?>"/>
            </div>

			<?php if ( wp_sms_get_option( 'newsletter_form_groups' ) ) { ?>
                <div class="wpsms-newsletter__form__field">
                    <label><?php _e( 'Group', 'wp-sms' ); ?>:</label>
                    <select id="wpsms-groups" class="wpsms-newsletter__field__input">
						<?php foreach ( $get_group_result as $items ): var_dump( $get_group_result ); ?>
                            <option value="<?php echo $items->ID; ?>"><?php echo $items->name; ?></option>
						<?php endforeach; ?>
                    </select>
                </div>
			<?php } ?>

            <div class="wpsms-newsletter__form__field wpsms-newsletter__field__input--radio">
                <label>
                    <input type="radio" name="subscribe_type" id="wpsms-type-subscribe" value="subscribe" checked="checked"/>
					<?php _e( 'Subscribe', 'wp-sms' ); ?>
                </label>

                <label>
                    <input type="radio" name="subscribe_type" id="wpsms-type-unsubscribe" value="unsubscribe"/>
					<?php _e( 'Unsubscribe', 'wp-sms' ); ?>
                </label>
            </div>
			<?php if ( $gdpr_compliance ) { ?>
                <div class="wpsms-newsletter__form__field wpsms-newsletter__form__field--gdpr">
                    <label>
                        <input id="wpsms-gdpr-confirmation" type="checkbox" <?php echo $newsletter_form_gdpr_confirm_checkbox == 'checked' ? 'checked="checked"' : ''; ?>>
						<?php echo $newsletter_form_gdpr_text ? $newsletter_form_gdpr_text : 'I agree to receive SMS based on my data'; ?>
                    </label>
                </div>
			<?php } ?>

            <button class="wpsms-button" id="wpsms-submit"><?php _e( 'Subscribe', 'wp-sms' ); ?></button>
        </div>
		<?php $disable_style = wp_sms_get_option( 'disable_style_in_front' );
		if ( empty( $disable_style ) and ! $disable_style ): ?>
        <div id="wpsms-step-2">
			<?php else: ?>
            <div id="wpsms-step-2" style="display: none;">
				<?php endif; ?>

                <div class="wpsms-newsletter__form__field">
                    <label><?php _e( 'Activation code:', 'wp-sms' ); ?></label>
                    <input type="text" id="wpsms-ativation-code" placeholder="<?php _e( 'Activation code:',
						'wp-sms' ); ?>" class="wpsms-newsletter__field__input"/>
                </div>
                <button class="wpsms-button" id="activation"><?php _e( 'Activation', 'wp-sms' ); ?></button>
            </div>
            <input type="hidden" id="wpsms-widget-id" value="<?php echo $widget_id; ?>">
            <input type="hidden" id="newsletter-form-verify" value="<?php echo wp_sms_get_option( 'newsletter_form_verify' ); ?>">
        </div>
    </div>
</div>
