<p>
    <label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title', 'wp-sms' ); ?></label>
    <input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>"
           name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
</p>

<p>
    <label for="<?php echo $this->get_field_id( 'description' ); ?>"><?php _e( 'Description', 'wp-sms' ); ?></label>
    <textarea class="widefat" id="<?php echo $this->get_field_id( 'description' ); ?>"
              name="<?php echo $this->get_field_name( 'description' ); ?>"><?php echo esc_attr( $description ); ?></textarea>
<p class="description"><?php _e( 'HTML code is valid.', 'wp-sms' ); ?></p>
</p>

<p>
    <input class="checkbox" id="<?php echo $this->get_field_id( 'show_group' ); ?>"
           name="<?php echo $this->get_field_name( 'show_group' ); ?>" type="checkbox"
           value="1" <?php checked( $show_group, 1 ); ?>>
    <label for="<?php echo $this->get_field_id( 'show_group' ); ?>"><?php _e( 'Show Groups', 'wp-sms' ); ?></label>
</p>

<p>
    <input class="checkbox" id="<?php echo $this->get_field_id( 'send_activation_code' ); ?>"
           name="<?php echo $this->get_field_name( 'send_activation_code' ); ?>" type="checkbox"
           value="1" <?php checked( $send_activation_code, 1 ); ?>>
    <label for="<?php echo $this->get_field_id( 'send_activation_code' ); ?>"><?php _e( 'Verified subscribe with the activation code', 'wp-sms' ); ?></label>
</p>

<p>
    <input class="checkbox" id="<?php echo $this->get_field_id( 'send_welcome_sms' ); ?>"
           name="<?php echo $this->get_field_name( 'send_welcome_sms' ); ?>" type="checkbox"
           value="1" <?php checked( $send_welcome_sms, 1 ); ?>>
    <label for="<?php echo $this->get_field_id( 'send_welcome_sms' ); ?>"><?php _e( 'Send welcome SMS', 'wp-sms' ); ?></label>
</p>

<?php if ( $send_welcome_sms ) : ?>
    <p>
        <label for="<?php echo $this->get_field_id( 'welcome_sms_template' ); ?>"><?php _e( 'Welcome sms text', 'wp-sms' ); ?></label>
        <textarea class="widefat" id="<?php echo $this->get_field_id( 'welcome_sms_template' ); ?>"
                  name="<?php echo $this->get_field_name( 'welcome_sms_template' ); ?>"><?php echo esc_attr( $welcome_sms_template ); ?></textarea>
    <p class="description">
		<?php echo sprintf( __( 'Subscribe name: %s, Subscribe mobile: %s', 'wp-sms' ), '<code>%subscribe_name%</code>', '<code>%subscribe_mobile%</code>' ); ?>
    </p>
    </p>
<?php endif; ?>

<p>
    <input class="checkbox" id="<?php echo $this->get_field_id( 'mobile_number_terms' ); ?>"
           name="<?php echo $this->get_field_name( 'mobile_number_terms' ); ?>" type="checkbox"
           value="1" <?php checked( $mobile_number_terms, 1 ); ?>>
    <label for="<?php echo $this->get_field_id( 'mobile_number_terms' ); ?>"><?php _e( 'Mobile Number terms', 'wp-sms' ); ?></label>
</p>

<?php if ( $mobile_number_terms ) : ?>
    <p>
        <label for="<?php echo $this->get_field_id( 'mobile_field_placeholder' ); ?>"><?php _e( 'Mobile field description', 'wp-sms' ); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'mobile_field_placeholder' ); ?>"
               name="<?php echo $this->get_field_name( 'mobile_field_placeholder' ); ?>" type="text"
               value="<?php echo esc_attr( $mobile_field_placeholder ); ?>">
    </p>

    <p>
        <label for="<?php echo $this->get_field_id( 'mobile_field_max' ); ?>"><?php _e( 'Maximum number', 'wp-sms' ); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'mobile_field_max' ); ?>"
               name="<?php echo $this->get_field_name( 'mobile_field_max' ); ?>" type="number"
               value="<?php echo esc_attr( $mobile_field_max ); ?>">
    </p>

    <p>
        <label for="<?php echo $this->get_field_id( 'mobile_field_min' ); ?>"><?php _e( 'Minimum number', 'wp-sms' ); ?></label>
        <input class="widefat" id="<?php echo $this->get_field_id( 'mobile_field_min' ); ?>"
               name="<?php echo $this->get_field_name( 'mobile_field_min' ); ?>" type="number"
               value="<?php echo esc_attr( $mobile_field_min ); ?>">
    </p>
<?php endif; ?>