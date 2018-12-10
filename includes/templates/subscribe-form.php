<?php if ( ! isset( $instance['description'] ) ) { ?>
    <h2 class="widget-title">Subscribe SMS</h2>
<?php } ?>
<div id="wpsms-subscribe">
    <div id="wpsms-result"></div>
    <div id="wpsms-step-1">
		<?php if ( isset( $instance['description'] ) ) { ?>
            <p><?php echo isset( $instance['description'] ) ? $instance['description'] : ''; ?></p>
		<?php } ?>
        <div class="wpsms-subscribe-form">
            <label><?php _e( 'Your name', 'wp-sms' ); ?>:</label>
            <input id="wpsms-name" type="text" placeholder="<?php _e( 'Your name', 'wp-sms' ); ?>" class="wpsms-input"/>
        </div>

        <div class="wpsms-subscribe-form">
            <label><?php _e( 'Your mobile', 'wp-sms' ); ?>:</label>
            <!-- TODO: This is the original line and we replaced it with \WP_SMS\Option::getOption() Method.
            <input id="wpsms-mobile" type="text"
                   placeholder="<?php echo isset( $wpsms_option['mobile_terms_field_place_holder'] ) ? $wpsms_option['mobile_terms_field_place_holder'] : ''; ?>"-->
            <input id="wpsms-mobile" type="text"
                   placeholder="<?php echo \WP_SMS\Option::getOption('mobile_terms_field_place_holder') ? \WP_SMS\Option::getOption('mobile_terms_field_place_holder') : ''; ?>"
                   class="wpsms-input"/>
        </div>

		<?php if ( isset( $wpsms_option['newsletter_form_groups'] ) AND $wpsms_option['newsletter_form_groups'] ) { ?>
            <div class="wpsms-subscribe-form">
                <label><?php _e( 'Group', 'wp-sms' ); ?>:</label>
                <select id="wpsms-groups" class="wpsms-input">
					<?php foreach ( $get_group_result as $items ): ?>
                        <option value="<?php echo $items->ID; ?>"><?php echo $items->name; ?></option>
					<?php endforeach; ?>
                </select>
            </div>
		<?php } ?>

        <div class="wpsms-subscribe-form">
            <label>
                <input type="radio" name="subscribe_type" id="wpsms-type-subscribe" value="subscribe"
                       checked="checked"/>
				<?php _e( 'Subscribe', 'wp-sms' ); ?>
            </label>

            <label>
                <input type="radio" name="subscribe_type" id="wpsms-type-unsubscribe" value="unsubscribe"/>
				<?php _e( 'Unsubscribe', 'wp-sms' ); ?>
            </label>
        </div>
		<?php if ( isset( $wpsms_option['gdpr_compliance'] ) and $wpsms_option['gdpr_compliance'] == 1 ) { ?>
            <div class="wpsms-subscribe-form">
                <label><input id="wpsms-gdpr-confirmation"
                              type="checkbox" <?php echo isset( $wpsms_option['newsletter_form_gdpr_confirm_checkbox'] ) && $wpsms_option['newsletter_form_gdpr_confirm_checkbox'] == 'checked' ? 'checked="checked"' : ''; ?>>
					<?php echo isset( $wpsms_option['newsletter_form_gdpr_text'] ) && $wpsms_option['newsletter_form_gdpr_text'] ? $wpsms_option['newsletter_form_gdpr_text'] : 'GDPR text...'; ?>
                </label>
            </div>
		<?php } ?>

        <button class="wpsms-button" id="wpsms-submit"><?php _e( 'Subscribe', 'wp-sms' ); ?></button>
    </div>
	<?php if ( empty( $wpsms_option['disable_style_in_front'] ) or ( isset( $wpsms_option['disable_style_in_front'] ) and ! $wpsms_option['disable_style_in_front'] ) ): ?>
    <div id="wpsms-step-2">
		<?php else: ?>
        <div id="wpsms-step-2" style="display: none;">
			<?php endif; ?>

            <div class="wpsms-subscribe-form">
                <label><?php _e( 'Activation code:', 'wp-sms' ); ?></label>
                <input type="text" id="wpsms-ativation-code" placeholder="<?php _e( 'Activation code:', 'wp-sms' ); ?>"
                       class="wpsms-input"/>
            </div>
            <button class="wpsms-button" id="activation"><?php _e( 'Activation', 'wp-sms' ); ?></button>
        </div>
        <input type="hidden" id="wpsms-widget-id" value="<?php echo $widget_id; ?>">
    </div>