<div id="wpsms-subscribe">
    <div id="wpsms-result"></div>
    <div id="wpsms-step-1">
        <p><?php echo $instance['description']; ?></p>
        <div class="wpsms-subscribe-form">
            <label><?php _e( 'Your name', 'wp-sms' ); ?>:</label>
            <input id="wpsms-name" type="text" placeholder="<?php _e( 'Your name', 'wp-sms' ); ?>" class="wpsms-input"/>
        </div>

        <div class="wpsms-subscribe-form">
            <label><?php _e( 'Your mobile', 'wp-sms' ); ?>:</label>
            <input id="wpsms-mobile" type="text" placeholder="<?php echo $instance['mobile_field_placeholder']; ?>"
                   class="wpsms-input"/>
        </div>

		<?php if ( $instance['show_group'] ) { ?>
            <div class="wpsms-subscribe-form">
                <label><?php _e( 'Group', 'wp-sms' ); ?>:</label>
                <select id="wpsms-groups" class="wpsms-input">
					<?php foreach ( $get_group as $items ): ?>
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

        <button class="wpsms-button" id="wpsms-submit"><?php _e( 'Subscribe', 'wp-sms' ); ?></button>
    </div>

    <div id="wpsms-step-2">
        <div class="wpsms-subscribe-form">
            <label><?php _e( 'Activation code:', 'wp-sms' ); ?></label>
            <input type="text" id="wpsms-ativation-code" placeholder="<?php _e( 'Activation code:', 'wp-sms' ); ?>"
                   class="wpsms-input"/>
        </div>
        <button class="wpsms-button" id="activation"><?php _e( 'Activation', 'wp-sms' ); ?></button>
    </div>
    <input type="hidden" id="wpsms-widget-id" value="<?php echo $widget_id; ?>">
</div>