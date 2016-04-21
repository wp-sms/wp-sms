<p>
	<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e( 'Title', 'wp-sms' ); ?></label> 
	<input class="widefat" id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
</p>

<p>
	<label for="<?php echo $this->get_field_id( 'description' ); ?>"><?php _e( 'Description', 'wp-sms' ); ?></label> 
	<textarea class="widefat" id="<?php echo $this->get_field_id( 'description' ); ?>" name="<?php echo $this->get_field_name( 'description' ); ?>"><?php echo esc_attr( $description ); ?></textarea>
	<p class="description"><?php _e( 'HTML code is valid.', 'wp-sms' ); ?></p>
</p>

<p>
    <label for="<?php echo $this->get_field_id('show_group'); ?>">
        <?php _e('Show Group', 'wp-sms'); ?>:
    </label><br>
    <label for="<?php echo $this->get_field_id('show_group'); ?>">
        <?php _e('Yes', 'wp-sms'); ?>:
        <input class="" id="<?php echo $this->get_field_id('yes'); ?>" name="<?php echo $this->get_field_name('show_group'); ?>" type="radio" value="1" <?php if($show_group){ echo 'checked="checked"'; } ?> />
    </label><br>
    <label for="<?php echo $this->get_field_id('show_group'); ?>">
        <?php _e('No', 'wp-sms'); ?>:
        <input class="" id="<?php echo $this->get_field_id('no'); ?>" name="<?php echo $this->get_field_name('show_group'); ?>" type="radio" value="0" <?php if(!$show_group){ echo 'checked="checked"'; } ?> />
    </label>
    </p>