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
            <input id="wpsms-mobile" type="text" placeholder="<?php echo \WP_SMS\Option::getOption( 'mobile_terms_field_place_holder' ); ?>" class="wpsms-input"/>
        </div>

		<?php if ( \WP_SMS\Option::getOption( 'newsletter_form_groups' ) ) { ?>
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
                <input type="radio" name="subscribe_type" id="wpsms-type-subscribe" value="subscribe" checked="checked"/>
				<?php _e( 'Subscribe', 'wp-sms' ); ?>
            </label>

            <label>
                <input type="radio" name="subscribe_type" id="wpsms-type-unsubscribe" value="unsubscribe"/>
				<?php _e( 'Unsubscribe', 'wp-sms' ); ?>
            </label>
        </div>
		<?php if ( \WP_SMS\Option::getOption( 'gdpr_compliance' ) == 1 ) { ?>
            <div class="wpsms-subscribe-form">
                <label><input id="wpsms-gdpr-confirmation" type="checkbox" <?php echo \WP_SMS\Option::getOption( 'newsletter_form_gdpr_confirm_checkbox' ) == 'checked' ? 'checked="checked"' : ''; ?>>
					<?php echo \WP_SMS\Option::getOption( 'newsletter_form_gdpr_text' ) ? \WP_SMS\Option::getOption( 'newsletter_form_gdpr_text' ) : 'GDPR text...'; ?>
                </label>
            </div>
		<?php } ?>

        <button class="wpsms-button" id="wpsms-submit"><?php _e( 'Subscribe', 'wp-sms' ); ?></button>
    </div>
	<?php $disable_style = \WP_SMS\Option::getOption( 'disable_style_in_front' ); if ( empty( $disable_style ) AND ! $disable_style ): ?>
    <div id="wpsms-step-2">
		<?php else: ?>
        <div id="wpsms-step-2" style="display: none;">
			<?php endif; ?>

            <div class="wpsms-subscribe-form">
                <label><?php _e( 'Activation code:', 'wp-sms' ); ?></label>
                <input type="text" id="wpsms-ativation-code" placeholder="<?php _e( 'Activation code:', 'wp-sms' ); ?>" class="wpsms-input"/>
            </div>
            <button class="wpsms-button" id="activation"><?php _e( 'Activation', 'wp-sms' ); ?></button>
        </div>
        <input type="hidden" id="wpsms-widget-id" value="<?php echo $widget_id; ?>">
        <input type="hidden" id="newsletter-form-verify" value="<?php echo \WP_SMS\Option::getOption('newsletter_form_verify');?>">
    </div>